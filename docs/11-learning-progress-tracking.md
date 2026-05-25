# Learning Progress Tracking

Dokumen ini mendeskripsikan fitur **pelacakan penyelesaian pembelajaran**
per peserta didik: durasi akses material, progres pengerjaan tugas &
ujian, agregasi progres belajar per mata pelajaran, **dan dukungan
data penelitian**.

> Status: **Proposal v5** — belum diimplementasi. Lihat
> [changelog](./changelog-11-learning-progress-tracking.md) untuk
> riwayat revisi. Setelah implementasi selesai,
> [02-database-schema.md](./02-database-schema.md) wajib di-update.

---

## 1. Tujuan & Use Case

### 1.1 Tujuan

Memberikan data **objektif** untuk:

1. Mengukur **engagement** siswa terhadap konten pembelajaran
   (bukan sekadar "submit/tidak submit" — tapi seberapa lama mereka
   benar-benar mengakses).
2. Mendeteksi siswa yang **berisiko tertinggal** (rendah waktu akses
   material, banyak tugas pending, ujian dikerjakan terburu-buru).
3. Menyediakan **dataset penelitian** dengan definisi operasional
   variabel yang reproducible (lihat [§13](#13-research-spec)).

### 1.2 Use Case Utama

| Peran | Use case |
|------|----------|
| Guru pengampu | Lihat siapa saja yang sudah buka materi minggu ini, durasinya, dan siapa yang belum |
| Guru pengampu | Lihat tabel ringkas: progres per siswa per mapel (% material, tugas, ujian) |
| Guru pengampu | Drill-down ke timeline 1 siswa: kapan buka apa, berapa lama |
| Super admin / peneliti | Audit lintas kelas, deteksi anomali |
| Super admin / peneliti | Export data tracking — opsi raw atau anonim — untuk SPSS/R/Python |

### 1.3 Non-Goals

- **Tracking lintas device/tab** dengan presisi detik penuh (heartbeat fixed 20 detik, lihat [§5.1](#51-heartbeat-endpoint)).
- **Anti-cheat fingerprinting** (tab blur ujian dikerjakan terpisah di [10-development-phases.md §Fase 6 §4.3](./10-development-phases.md#fase-6--polish--nice-to-have)).
- **Self-view siswa** di dashboard (bisa nyusul fase lanjutan).
- **Real-time live dashboard** ("siapa sedang online sekarang").
- **Tracking aktivitas offline** (siswa download PDF dan baca offline — lihat [§13.5](#135-keterbatasan-pengukuran)).

---

## 2. Arsitektur Singkat

```
┌─────────────────────┐         ┌──────────────────────────┐
│  Student Frontend   │         │  Server / Laravel        │
│  (Inertia + React)  │         │                          │
│                     │         │                          │
│  MaterialDetail     │ ──POST──► /student/.../heartbeat  │
│  AssignmentDetail   │  every  │                          │
│  ExamTake           │  20s    │ RecordLearningProgress   │
│                     │ +beacon │   ├─ validate + insert   │
│                     │ on hide │   └─ upsert session      │
└─────────────────────┘         │                          │
                                │ Scheduler (daily 02:00)  │
                                │   └─ RollupDailyProgress │
                                │ Scheduler (5m)           │
                                │   └─ CloseStaleSessions  │
                                │ Scheduler (weekly)       │
                                │   └─ PruneOldEvents      │
                                └──────────────────────────┘

                                Filament (Pengajaran group)
                                  ├─ Course Progress (per mapel)
                                  ├─ Student Activity (per siswa)
                                  └─ Material Engagement (per material)
```

Komponen utama:

- **Event collector** — endpoint heartbeat menerima ping ringan dari frontend.
- **Session aggregator** — meng-upsert satu row per `(student, resource, session_id)`.
- **Daily rollup** — job harian merangkum total durasi per `(student, classroom_subject, date)`.
- **Retention job** — purge raw event > N hari (wajib, bukan opsional).
- **Filament views** — guru pengampu (scoped) & super admin/peneliti (global).

### 2.1 Design decision: kenapa simpan raw events (bukan hanya sessions)?

Alternatif yang dipertimbangkan: frontend hitung `active_seconds`
lokal, kirim hanya **delta** ke endpoint, server upsert sessions —
tanpa tabel events sama sekali.

| Aspek | Keep events (pilihan) | Sessions-only (alternatif) |
|-------|--------------------------|----------------------------|
| Storage stable-state | ~170 MB (retention 90 hari) | ~12 MB |
| Re-compute kalau formula berubah | ✅ bisa, sampai 90 hari | ❌ permanen tidak bisa |
| Audit/anti-fraud (deteksi pola anomali) | ✅ ada raw stream | ❌ tidak ada |
| Trust client | rendah (server otoritatif) | tinggi (client otoritatif) |
| Privacy footprint | lebih besar | lebih kecil |
| Kompleksitas implementasi | menengah | rendah |

**Pilihan saat ini = keep events** karena:
1. Riset menuntut server sebagai otoritas (`occurred_at` client tetap
   dipakai, tapi server bisa cross-check via `received_at`).
2. Formula `active_seconds` ([§4.3](#43-formula-active_seconds--idle_seconds))
   mungkin direvisi peneliti setelah data awal masuk; re-compute
   dengan raw events memungkinkan tanpa kehilangan data.
3. Storage 170 MB stable-state masih jauh di bawah budget
   ([§15](#15-storage--perf-budget)).

Trade-off privacy ditebus dengan retention 90 hari + opt-out
([§8](#8-privasi-consent--etika)).

---

## 3. Skema Database

Konvensi global ([01-overview.md](./01-overview.md#global-conventions))
diikuti **dengan pengecualian eksplisit** untuk tabel volume tinggi.

> **Pengecualian konvensi (ringkas):**
> - 3 tabel di sini **tidak pakai `SoftDeletes`** (konvensi global default).
>   Alasan per tabel di sub-section masing-masing.
> - `learning_progress_events.id` = `bigIncrements` (bukan UUID).
> - Storage timestamp tetap UTC; konversi Asia/Jakarta hanya untuk
>   grouping date di rollup.

### 3.1 `learning_progress_events`

Event-level raw stream. **Append-only**, no soft delete, no UUID PK.

> **Pengecualian konvensi:** tabel ini pakai `bigIncrements` (bukan UUID)
> karena insert volume tinggi (≈1.9 juta row/tahun worst-case, lihat
> [§15.1](#151-estimasi-volume)). UUID random sebagai PK menyebabkan
> B-tree fragmentation; tidak layak di sini. Tidak ada tabel lain yang
> FK ke tabel ini, jadi tidak ada masalah portabilitas.

| Column | Type | Notes |
|--------|------|-------|
| id | bigIncrements | PK |
| student_id | uuid | FK → students.id, indexed |
| trackable_type | string | morph: Material / Assignment / Exam |
| trackable_id | uuid | morph id |
| classroom_subject_id | uuid | **snapshot** dari trackable saat insert, FK ke classroom_subjects (lihat [§3.4](#34-snapshot-policy)) |
| session_id | uuid | client-generated, mengelompokkan event 1 sesi akses |
| event | enum | `open`, `focus`, `blur`, `heartbeat`, `idle`, `close` |
| occurred_at | timestamp | client clock (UTC, dikonversi dari ISO 8601 input) |
| received_at | timestamp(precision=3) | server clock saat insert |
| duration_ms | unsignedInteger | nullable; range 0–`session.max_active_gap_ms` (default 60_000), di-clamp server-side ([§5.4](#54-payload-validation)) |
| meta | json | nullable; hanya field whitelist (lihat [§5.4](#54-payload-validation)) |
| created_at | timestamp | server insert time |

**Index:**
- `(student_id, trackable_type, trackable_id, occurred_at)` — query timeline siswa
- `(classroom_subject_id, occurred_at)` — scoping guru pengampu
- `session_id` — agregasi
- `received_at` — retention sweep & monitoring

**Retention:** raw event dihapus setelah **90 hari** via job mingguan
(lihat [§9 Fase A](#fase-a--backend-tracking)). Rollup adalah sumber
data jangka panjang.

### 3.2 `learning_progress_sessions`

Aggregate per sesi akses. **Tanpa soft delete** — koreksi via update
langsung + activity_log. Satu row per `(student_id, trackable, session_id)`.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| student_id | uuid | FK → students.id |
| trackable_type | string | morph |
| trackable_id | uuid | morph |
| classroom_subject_id | uuid | **snapshot** ([§3.4](#34-snapshot-policy)) |
| session_id | uuid | client-generated, unique bersama `(student_id, trackable_type, trackable_id)` |
| started_at | timestamp | event `open` pertama (server time) |
| last_seen_at | timestamp | event terakhir |
| ended_at | timestamp | nullable; diisi saat `close` atau session timeout |
| active_seconds | unsignedInteger | lihat [§4.3](#43-formula-active_seconds--idle_seconds) |
| idle_seconds | unsignedInteger | sda |
| end_reason | enum | `closed` (event `close` diterima), `timeout` (sweeper), nullable saat sesi masih live |
| created_at / updated_at | timestamps | |

**Index:**
- `(student_id, classroom_subject_id, started_at)`
- `(trackable_type, trackable_id, started_at)`
- `last_seen_at` (untuk sweeper)

**Session timeout:** jika `last_seen_at < now() − config('learning_progress.session.timeout_minutes')`
(default 5 menit) dan `ended_at IS NULL`, sweeper menutup sesi
(`ended_at = last_seen_at`, `end_reason = 'timeout'`).

**Session comeback (event masuk untuk session yang sudah `ended`).**
Skenario: siswa idle > timeout → sweeper close sesi → siswa kembali
aktif di tab yang sama (`session_id` masih ada di `sessionStorage`)
→ frontend kirim event lagi. Perilaku server:

- **Insert event tetap ke tabel `learning_progress_events`** (raw log
  utuh, tidak boleh hilang).
- **JANGAN reopen** session row lama. Sebaliknya, action
  `RecordLearningProgress` **auto-spawn session row baru** dengan
  `session_id` baru server-generated (UUID v4), simpan mapping
  `original_client_session_id` di field `meta` event pertama untuk
  audit.
- Response 204 normal (frontend tidak perlu tahu).
- Frontend tetap pakai `session_id` lama sampai user reload — tidak
  apa-apa, server akan terus spawn session baru tiap kali ada gap >
  timeout. Setelah reload, `sessionStorage` reset, session_id fresh.

Alasan: reopen sesi melanggar konsep "1 sesi = 1 periode kontinyu";
nilai `active_seconds` lama jadi tidak well-defined. Spawn sesi baru
menjaga semantik konsisten + tidak kehilangan data.

**Audit trail:** model `LearningProgressSession` apply trait
`Spatie\Activitylog\Traits\LogsActivity` (pola sama dengan
`AssignmentSubmission`/`ExamSession` existing). Hanya log event
`updated`/`deleted` saat ada **koreksi manual** super admin (mis. via
tinker / fix data), bukan setiap upsert dari heartbeat. Implementasi:
`getActivitylogOptions()` return `LogOptions::defaults()->logOnly([...])
->dontSubmitEmptyLogs()->logOnlyDirty()` + di-disable saat insert dari
action heartbeat (`activity()->disableLogging()` di `RecordLearningProgress`).

### 3.3 `learning_progress_daily_rollups`

Aggregat harian per siswa per mapel. **Sumber kebenaran query dashboard**.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| student_id | uuid | FK → students.id |
| classroom_subject_id | uuid | FK → classroom_subjects.id |
| date | date | **tanggal lokal Asia/Jakarta** ([§3.5](#35-timezone-rule)) |
| material_seconds | unsignedInteger | total active time di material hari itu |
| assignment_seconds | unsignedInteger | total active time di assignment detail (sebelum submit) |
| exam_seconds | unsignedInteger | total active time di **halaman detail exam** (sebelum klik mulai dan sesudah submit). **TIDAK** mencakup waktu mengerjakan — itu sudah ada di `exam_sessions` (started_at→submitted_at). |
| materials_opened | unsignedInteger | jumlah material unik dibuka |
| assignments_worked | unsignedInteger | jumlah assignment unik dibuka |
| exams_attempted | unsignedInteger | jumlah exam unik dibuka |
| computed_at | timestamp | saat rollup terakhir dijalankan untuk row ini |
| created_at / updated_at | timestamps | |

**Unique:** `(student_id, classroom_subject_id, date)`. Idempotent
upsert pada re-run.

> **Tanpa soft delete.** Row sepenuhnya derived dari sessions; re-run
> command kapan saja akan rekonstruksi ulang. Tidak ada nilai dari
> menyimpan tombstone.

### 3.4 Snapshot policy

`classroom_subject_id` di-**snapshot saat insert event/session**, **tidak**
ditarik via relasi `trackable->classroomSubject` saat query.

- Alasan: kalau `classroom_subjects.teacher_id` ganti pertengahan
  semester, scoping data lama harus tetap mengikuti guru pengampu lama.
- Resolusi: di action `RecordLearningProgress`, resolve
  `classroom_subject_id` dari trackable saat insert, lalu pin.
- Jika trackable adalah `Assignment`/`Exam`, ambil dari
  `assignment.classroom_subject_id` / `exam.classroom_subject_id`
  saat itu.

### 3.5 Timezone rule

Standar tunggal:

- **Storage:** semua timestamp di-store sebagai **UTC** (default Laravel).
- **Basis rollup harian:** `received_at` (server clock, lebih
  trustworthy daripada `occurred_at` client) dikonversi ke
  **Asia/Jakarta** untuk grouping `date`.
- **`occurred_at`** tetap disimpan untuk audit timeline siswa (tampilan
  jam lokal), tapi **tidak** dipakai untuk rollup/scoping date.

### 3.6 Reuse data existing & relasi dengan `activity_log`

| Sumber | Dipakai untuk |
|--------|---------------|
| [assignment_submissions](./02-database-schema.md#assignment_submissions) | Status submit / late / score |
| [exam_sessions](./02-database-schema.md#exam_sessions) | Durasi & status ujian |
| [activity_log](../database/migrations/2026_05_19_081109_create_activity_log_table.php) | Audit trail aksi diskrit (lihat **kebijakan** di bawah) |

**Kebijakan pemisahan (penting untuk peneliti):**

| Kebutuhan | Sumber authoritative |
|-----------|----------------------|
| Durasi/engagement (active_seconds, idle_seconds) | `learning_progress_*` |
| Aksi diskrit non-durasi (siapa edit apa kapan; password reset; download file) | `activity_log` |
| "Material X dibuka oleh siapa, kapan terakhir" | `learning_progress_sessions` (bukan activity_log) |

**Migrasi log existing:** event `log_name = 'material_view'` di
[MaterialController.php:26](../app/Http/Controllers/Student/MaterialController.php#L26)
**di-hapus** saat Fase B live (sudah subsumed oleh
`learning_progress_events.event = 'open'`). Data lama di activity_log
tetap, tapi tidak di-backfill ke sessions (peneliti tidak boleh
mencampur kedua sumber untuk metric durasi).

---

## 4. Model & Relasi

File baru di [app/Models/](../app/Models/):

- `LearningProgressEvent`
- `LearningProgressSession`
- `LearningProgressDailyRollup`

### 4.1 Trait `HasLearningProgress`

```php
trait HasLearningProgress
{
    public function progressEvents()   { return $this->morphMany(LearningProgressEvent::class, 'trackable'); }
    public function progressSessions() { return $this->morphMany(LearningProgressSession::class, 'trackable'); }
}
```

Apply di [Material.php](../app/Models/Material.php),
[Assignment.php](../app/Models/Assignment.php),
[Exam.php](../app/Models/Exam.php).

### 4.2 Enum event

`app/Models/Enums/LearningProgressEventType.php`:

```php
enum LearningProgressEventType: string {
    case Open      = 'open';
    case Focus     = 'focus';
    case Blur      = 'blur';
    case Heartbeat = 'heartbeat';
    case Idle      = 'idle';
    case Close     = 'close';
}
```

### 4.3 Formula `active_seconds` / `idle_seconds`

Definisi operasional (untuk konsistensi riset).

**Prasyarat komputasi:**

1. Server **wajib sort** event di-batch + event existing untuk session
   tersebut `ORDER BY occurred_at ASC` sebelum apply formula. Network
   reorder & beacon flush dapat mengirim event out-of-order.
2. Komputasi dijalankan **inkremental** di action `RecordLearningProgress`:
   ambil last event sebelumnya untuk session ini, gabung dengan batch
   baru, sort, hitung delta, lalu update `active_seconds` & `idle_seconds`
   di sessions row.
3. Sumber waktu = `occurred_at` (client). `received_at` hanya untuk
   audit/anti-fraud, bukan komputasi durasi.

```
Let MAX_GAP_MS = config('learning_progress.session.max_active_gap_ms')

Per pasangan event (e_prev, e_curr) dalam 1 session, urut ASC by occurred_at:

  delta_ms = MIN(MAX_GAP_MS, (occurred_at(e_curr) − occurred_at(e_prev)) in ms)
  IF state(e_prev) == ACTIVE  → active_ms += delta_ms
  IF state(e_prev) == IDLE    → idle_ms   += delta_ms

State transitions:
  open       → ACTIVE
  focus      → ACTIVE
  heartbeat  → tetap (renew last-seen)
  blur       → IDLE
  idle       → IDLE
  close      → terminal

Output:
  active_seconds = floor(active_ms / 1000)
  idle_seconds   = floor(idle_ms   / 1000)
```

**Cap `MAX_GAP_MS`** (default `60_000` di config) mencegah gap besar
(siswa tinggal tab tanpa close) dihitung sebagai active. Kalau heartbeat
hilang > nilai cap, sisanya hilang — dianggap idle implisit dan sweeper
tutup sesi.

---

## 5. Endpoint & Aksi

### 5.1 Heartbeat endpoint

```
POST /student/progress/heartbeat
```

Body:

```json
{
  "session_id": "<uuid v4 client>",
  "trackable_type": "material|assignment|exam",
  "trackable_id": "<uuid>",
  "events": [
    {"event": "open",      "occurred_at": "2026-05-25T08:00:00+07:00"},
    {"event": "heartbeat", "occurred_at": "2026-05-25T08:00:20+07:00"},
    {"event": "blur",      "occurred_at": "2026-05-25T08:00:25+07:00"}
  ]
}
```

- Endpoint **batched** — frontend kumpulkan event lokal lalu kirim
  tiap ~20 detik atau saat tab blur/close (`navigator.sendBeacon`).
- Throttle: `throttle:120,1` (mirip save-answer ujian di [routes/web.php](../routes/web.php)).
- Action: `App\Actions\Student\RecordLearningProgress`.

**Response shape:**

| Status | Kondisi | Body |
|--------|---------|------|
| `204 No Content` | sukses, event accepted | (kosong) |
| `204 No Content` | siswa `tracking_opt_out = true` | (kosong, silent skip) |
| `422 Unprocessable Entity` | validation gagal (drift, enum, schema) | `{"errors": {<field>: [...]}}` Laravel default |
| `403 Forbidden` | trackable bukan milik kelas siswa | `{"message": "forbidden"}` |
| `429 Too Many Requests` | throttle exceeded | Laravel default |

Frontend **tidak retry otomatis** pada 4xx (data tracking opsional —
hilang sebagian OK). Pada `5xx`/network error: buffer di `sessionStorage`,
retry pada heartbeat berikutnya, **drop kalau buffer > 50 event** (sesuai
batas batch).

### 5.2 Daily rollup command

```
php artisan progress:rollup-daily {--date=YYYY-MM-DD}
```

- Default: proses tanggal kemarin (Asia/Jakarta).
- Idempotent: replace row existing.
- Schedule di [routes/console.php](../routes/console.php):

  ```php
  Schedule::command('progress:rollup-daily')
      ->dailyAt('02:00')
      ->timezone('Asia/Jakarta');
  ```

### 5.3 Session sweeper & retention

```
php artisan progress:close-stale-sessions     # every 5 minutes
php artisan progress:prune-old-events         # weekly
```

- Sweeper: tutup sesi `last_seen_at < now() − config('session.timeout_minutes')`
  & `ended_at IS NULL` (lihat [§3.2](#32-learning_progress_sessions) session timeout).
- Pruner: hapus `learning_progress_events` umur > `config('retention.events_days')`
  (default 90 hari, lihat [§13.4](#134-retention--anonimisasi)).

### 5.4 Payload validation

Server-side validation di action `RecordLearningProgress`:

| Field | Aturan |
|-------|--------|
| `session_id` | UUID v4 format |
| `trackable_type` | `in:material,assignment,exam` |
| `trackable_id` | UUID; trackable harus ada & milik kelas siswa |
| `events` | array, max `validation.max_events_per_request` item/request (default 50) |
| `events.*.event` | enum |
| `events.*.occurred_at` | ISO 8601, **\|occurred_at − received_at\|** ≤ `validation.max_clock_drift_minutes` (default 10); reject seluruh batch kalau drift terlampaui pada event apapun |
| `events.*.duration_ms` | integer 0–`session.max_active_gap_ms` (default 60_000), di-clamp (bukan reject) |
| `meta` | object; whitelist key: `scroll_depth` (0–1), `viewport` (string max 32 char). Selain itu di-strip. |

**Multi-tab:** `session_id` di-generate per-tab (`sessionStorage`,
bukan `localStorage`). 2 tab → 2 session row terpisah, **tidak**
dijumlahkan saat rollup material `materials_opened` (pakai `DISTINCT
trackable_id`); tapi `material_seconds` **dijumlah** (over-count
sengaja, dianggap representasi total exposure — keterbatasan
didokumentasi di [§13.5](#135-keterbatasan-pengukuran)).

**Batas payload:** request body max `validation.max_payload_kb` (default
32 KB). Frontend split batch kalau melewati (mengakomodasi limit
`sendBeacon` di Safari).

---

## 6. UI — Filament (Guru & Super Admin)

Grup navigasi: **Pengajaran**. Scoping konsisten dengan memory
`feedback_filament_role_scoping`: guru hanya `classroom_subjects.teacher_id = me`.

### 6.1 Resource baru

| Resource | Pages | Scope |
|----------|-------|-------|
| `CourseProgressResource` | List per `classroom_subject`, View → detail per siswa | Guru: own. Super admin: all. |
| `StudentActivityResource` | List siswa di kelas guru, View → timeline | Sama |
| `MaterialEngagementResource` *(Fase D)* | Per material: berapa siswa buka, distribusi durasi | Sama |

> Konvensi Filament wajib (memory `feedback_filament_resource_conventions`):
> View page wajib, Edit di-disable (read-only), redirect ke list setelah
> action.

### 6.2 Tampilan utama

**Course Progress (List):**

| Kolom | Sumber |
|-------|--------|
| Siswa | `students.full_name` |
| % Material dibuka | `materials_opened_distinct / total_published_materials` |
| Total durasi material | `SUM(rollup.material_seconds)` formatted (`2j 14m`) |
| Tugas selesai | `submitted / total_published_assignments` |
| Tugas terlambat | `COUNT(is_late = true)` |
| Ujian dikerjakan | `attempted / total_published_exams` |
| Rata-rata nilai | `AVG(exam total_score, assignment score)` |
| Status risiko | Badge (lihat [§7.2](#72-status-risiko)) |

**Student Activity (View):**

Tab: Identitas → Material → Tugas → Ujian → Timeline (14 hari terakhir,
sumber `learning_progress_sessions`).

### 6.3 Export

Action **Export to Excel** di `CourseProgressResource` — kolom &
format dispesifikasi di [§14 Data Dictionary](#14-data-dictionary-export).
Sejalan dengan [10-development-phases.md §Fase 5](./10-development-phases.md#fase-5--data-export-penelitian).

### 6.4 Toggle `tracking_opt_out` di StudentResource

Penambahan ke [StudentResource](../app/Filament/Resources/StudentResource.php)
existing (bukan resource baru):

- **Form (Edit):** `Toggle::make('tracking_opt_out')` — visible hanya
  untuk `super_admin` (pakai `->visible(fn () => auth()->user()->hasRole('super_admin'))`).
- **Table column:** `IconColumn::make('tracking_opt_out')->boolean()`,
  toggle-able dari row action super admin.
- **Activity log:** perubahan flag ini sudah otomatis ter-log via trait
  `LogsActivity` existing di [Student.php](../app/Models/Student.php).
- **Effect:** mutasi flag berlaku langsung untuk request berikutnya;
  data historis sebelum opt-out tidak dihapus (gunakan retention
  90-hari atau manual purge admin kalau diminta siswa).

---

## 7. Aturan "Selesai" & Risiko (Config-Driven)

Semua threshold di file `config/learning_progress.php` — **bukan hardcoded
di kode**. Alasan: reproducibility riset (analisis ulang dengan threshold
beda tanpa redeploy).

```php
// config/learning_progress.php
return [
    'material_completion' => [
        'words_per_minute'    => 210,
        'minimum_seconds'     => 60,
        'active_ratio'        => 0.80,   // 80% × estimated_read_seconds
        'file_counts_as_done' => true,   // download = selesai
    ],
    'risk_thresholds' => [
        'window'                  => 'rolling_7d', // atau 'iso_week'
        'class_avg_low_pct'       => 50,           // < 50% rata-rata = pantau
        'class_avg_critical_pct'  => 25,           // < 25% rata-rata = berisiko
        'overdue_critical_count'  => 2,
    ],
    'retention' => [
        'events_days' => 90,
    ],
    'session' => [
        'timeout_minutes' => 5,
        'max_active_gap_ms' => 60_000,
    ],
    'validation' => [
        'max_clock_drift_minutes' => 10,
        'max_events_per_request'  => 50,
        'max_payload_kb'          => 32,
    ],
    'export' => [
        'pseudo_secret_env' => 'LEARNING_PROGRESS_PSEUDO_SECRET',
    ],
];
```

### 7.1 Material dianggap "selesai"

Schema `materials` aktual tidak punya kolom `type` eksplisit
([create_materials_table](../database/migrations/2026_05_07_100020_create_materials_table.php)) —
klasifikasi material derived dari konten:

| Klasifikasi | Aturan deteksi |
|-------------|----------------|
| `text` | `content IS NOT NULL` AND tidak ada media `material_files` AND `link_url IS NULL` |
| `file` | ada media di koleksi `material_files` (`$material->getMedia('material_files')->isNotEmpty()`) |
| `link` | `link_url IS NOT NULL` |

> Material bisa kombinasi (mis. text + file). Untuk aturan "selesai",
> evaluasi dengan **prioritas file > link > text** — jika ada file
> attachment, gunakan kriteria file dulu.

Salah satu terpenuhi (default config di atas):

- **text**: `active_seconds` ≥ `active_ratio × estimated_read_seconds`
  di mana `estimated_read_seconds = max(minimum_seconds, word_count(content) / (words_per_minute/60))`.
- **file**: ada event `download` (jika `file_counts_as_done = true`).
- **link**: ada session dengan `active_seconds ≥ minimum_seconds`.

**Event `download` — implementasi:**

Tidak ada `download` di enum [§4.2](#42-enum-event) (event itu untuk
lifecycle tab di frontend). Sebagai gantinya:

- Tambah route `GET /student/materials/{material}/files/{media}/download`
  yang stream media via Spatie Media Library (sudah tersedia, koleksi
  `material_files` di [Material.php:54-56](../app/Models/Material.php#L54-L56)).
- Di controller download, log activity terpisah:
  ```php
  activity('material_download')
      ->performedOn($material)
      ->log('downloaded');
  ```
- Query "material selesai" untuk `type=file` cek
  `Activity::where('log_name', 'material_download')
  ->where('subject_id', $material->id)
  ->where('causer_id', $student->id)->exists()`.

Pilihan ini menjaga schema `learning_progress_events` ramping (hanya
lifecycle tab) dan reuse `activity_log` untuk aksi diskrit
([§3.6](#36-reuse-data-existing--relasi-dengan-activity_log) kebijakan
pemisahan).

### 7.2 Status risiko

Per siswa per `classroom_subject`, window dari config (`rolling_7d` default):

| Status | Aturan |
|--------|--------|
| 🔴 berisiko | `overdue_count ≥ overdue_critical_count` AND `material_seconds < class_avg_critical_pct% × class_avg` |
| ⚠️ pantau | `overdue_count ≥ 1` OR `material_seconds < class_avg_low_pct% × class_avg` |
| ✅ aman | sisanya |

**`class_avg`** = rata-rata dari siswa **aktif** di kelas
(`students.is_active = true` AND terdaftar di classroom). Computation
cached 15 menit per `(classroom_subject_id, window)`.

---

## 8. Privasi, Consent & Etika

### 8.1 Disclosure & consent

- **Banner login siswa** sekali tampil: "Aktivitas belajar Anda
  dicatat untuk evaluasi dan penelitian." Dismiss tersimpan di
  `users.tracking_disclosure_seen_at` (kolom baru).
- **Consent dokumen**: persetujuan wali dikumpulkan **di luar sistem**
  (form kertas / digital school-managed) sebelum siswa diaktifkan.
  Sistem tidak memvalidasi consent per request — asumsi: semua siswa
  aktif sudah consent (tanggung jawab admin sekolah).
- **Opt-out**: super admin bisa set `students.tracking_opt_out = true`
  (kolom baru, default false). Action `RecordLearningProgress` skip
  insert untuk siswa dengan flag ini. Rollup juga skip.

### 8.2 PII di payload

- Forbid menyimpan isi materi, jawaban, atau teks tugas di `meta` event.
- `meta` whitelist key (lihat [§5.4](#54-payload-validation)).

### 8.3 Akses

- Tracking views dibatasi `super_admin` + guru pengampu (scoped).
- Wali kelas **tidak** diberi akses default. Bisa ditambah nanti via
  permission terpisah kalau penelitian membutuhkan.

### 8.4 Anonimisasi export

Export untuk peneliti punya 2 mode (lihat [§14.3](#143-mode-anonim)):
- **raw**: nama siswa + identitas lengkap.
- **anonim**: `student_pseudo_id` = `HMAC-SHA256(student_id, secret)`,
  nama distrip, kelas tetap (untuk grouping).

---

## 9. Roadmap Implementasi

### Fase A — Backend Tracking (4–5 hari)

**Entry:** [Fase 3 roadmap utama](./10-development-phases.md#fase-3--audit-trail--security) selesai.

- [ ] Migrasi:
      - `learning_progress_events` (bigint PK, no soft delete)
      - `learning_progress_sessions`
      - `learning_progress_daily_rollups`
      - `users.tracking_disclosure_seen_at` & `students.tracking_opt_out`
- [ ] Config `config/learning_progress.php` ([§7](#7-aturan-selesai--risiko-config-driven)).
- [ ] Tambah env var `LEARNING_PROGRESS_PSEUDO_SECRET` (random 64 char)
      di `.env` production + `.env.example` (placeholder kosong). Dibutuhkan
      mode anonim export ([§14.3](#143-mode-anonim)).
- [ ] Route download material (`GET /student/materials/{material}/files/{media}/download`)
      + log `activity('material_download')` ([§7.1](#71-material-dianggap-selesai)).
- [ ] Model + trait `HasLearningProgress` apply ke Material/Assignment/Exam.
- [ ] Action `RecordLearningProgress` + endpoint heartbeat
      (validation lengkap [§5.4](#54-payload-validation), snapshot
      `classroom_subject_id`, skip `tracking_opt_out`).
- [ ] Command:
      - `progress:rollup-daily` (idempotent, `date` arg)
      - `progress:close-stale-sessions` (every 5m)
      - `progress:prune-old-events` (weekly, retention config-driven)
- [ ] **Monitoring:** log Prometheus-style counter di `storage/logs/progress-metrics.log`
      — events_inserted_total, sessions_active_gauge per 1 menit. Min
      alert: kalau events_inserted_total = 0 selama 30 menit di jam
      kerja → log warning.
- [ ] Unit/feature test:
      - Batch 5 event → 1 session row dengan `active_seconds` sesuai formula [§4.3](#43-formula-active_seconds--idle_seconds).
      - Event di-batch tidak urut (occurred_at descending) → komputasi tetap benar (sort prerequisite).
      - Sweeper: session tanpa close > 5m → `ended_at` + `end_reason='timeout'`.
      - Rollup: 2 session di hari sama (boundary Asia/Jakarta) → 1 rollup row dengan SUM benar.
      - Validation: drift > config → reject 422; drift tepat di batas → accept.
      - Validation: `duration_ms` 99_999_999 → di-clamp ke `max_active_gap_ms`, tidak reject.
      - Multi-tab: 2 session_id berbeda → `materials_opened_distinct = 1`.
      - Opt-out: siswa flagged → 204 tanpa insert (assert query count = 0).
      - **Bulk insert:** batch 10 event → assert `DB::getQueryLog()` punya ≤ 2 INSERT/UPDATE query.
      - **LogsActivity disabled saat heartbeat:** 100 heartbeat insert → `activity_log` table tidak bertambah.
      - **Material file completion:** GET `/student/materials/{m}/files/{f}/download` → `activity_log`
        punya row `log_name='material_download'`; query "selesai" untuk material tsb return true.
      - Force-recompute: re-run pruner setelah edit formula → sessions tidak terganggu (events sudah purge dianggap final).
      - **Session comeback:** kirim event ke `session_id` yang `ended_at IS NOT NULL` → row session baru ter-spawn (bukan reopen lama); event terinsert dengan `meta.original_client_session_id` di-set.

**Exit:** Hit endpoint dari curl 5 kali, query session → 1 row sesuai
formula. Rollup manual → row muncul. Pruner dummy data 91 hari → terhapus.

### Fase B — Frontend Probe (2–3 hari)

**Entry:** Fase A selesai.

- [ ] Composable React `useLearningProgress(trackableType, trackableId)`:
      - Generate `session_id` UUID v4 di `sessionStorage` (per-tab).
      - Listen `visibilitychange`, `beforeunload`, `pagehide`.
      - Heartbeat interval 20s saat tab aktif (`document.visibilityState = 'visible'`).
      - Idle detector: 60s tanpa `pointermove`/`keydown`/`scroll` → emit `idle`.
      - Flush via `navigator.sendBeacon` di `pagehide`; split batch jika > 32KB.
- [ ] Inject probe di:
      - [resources/js/Pages/Material/MaterialDetail.tsx](../resources/js/Pages/Material/MaterialDetail.tsx)
      - halaman detail assignment
      - [resources/js/Pages/Exam/ExamTake.tsx](../resources/js/Pages/Exam/ExamTake.tsx) (paralel dengan auto-save, jangan ganggu)
- [ ] Banner privasi di [resources/js/Layouts/StudentLayout.tsx](../resources/js/Layouts/StudentLayout.tsx) (dismiss → POST update `tracking_disclosure_seen_at`).

**Exit:** Buka material 1 menit → row session `active_seconds ≈ 60`.
Blur 30s → `idle_seconds` naik, `active_seconds` tidak.

### Fase C — Filament Views (3–4 hari)

**Entry:** Fase A selesai (B tidak strict blocker).

- [ ] `CourseProgressResource` (Pengajaran, scoped).
- [ ] `StudentActivityResource` (tab Identitas/Material/Tugas/Ujian/Timeline).
- [ ] Action **Export to Excel** sesuai [§14](#14-data-dictionary-export) — mode raw + anonim.
- [ ] Widget [AtRiskStudentsWidget](../app/Filament/Widgets/) (top-N siswa berisiko di kelas guru).
- [ ] Smoke test: login guru → tabel siswa, angka konsisten dengan seeder.

**Exit:** Guru → Pengajaran → Course Progress → tabel siswa muncul
dengan kolom durasi terisi. Super admin lihat semua mapel + bisa
export anonim.

### Fase D — Polish (1–2 hari, opsional)

- [ ] `MaterialEngagementResource` (per-material drill-down).
- [ ] Heatmap mingguan di widget admin.
- [ ] Bulk export command `progress:export-all` (zip CSV semua tabel).
- [ ] Tunaikan threshold final dari peneliti di `config/learning_progress.php`.

---

## 10. Dependensi Antar Fase

```
[Roadmap utama Fase 3] ──► Fase A (backend, retention wajib) ──► Fase B ──┐
                                  │                                        ├──► Fase D
                                  └─────────────► Fase C ──────────────────┘
```

Fase B & C paralel. Tanpa B, view C tetap kepasang tapi kolom durasi
**kosong** (frontend belum kirim event apa pun ke `learning_progress_*`).
Catatan: `activity_log` `material_view` lama akan dihapus saat Fase B
selesai ([§3.6](#36-reuse-data-existing--relasi-dengan-activity_log)),
jadi Fase C **tidak** boleh mengandalkan log lama itu sebagai fallback
permanen.

---

## 11. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| Volume event meledak | Storage penuh, query lambat | Batched heartbeat, retention 90 hari, query dashboard ke rollup |
| Clock client salah | `occurred_at` melenceng | Reject batch drift > `validation.max_clock_drift_minutes`; rollup pakai `received_at` |
| Heartbeat hilang saat siswa tutup tab paksa | Sesi ngegantung | Sweeper `session.timeout_minutes`, `end_reason='timeout'` |
| Guru salah interpretasi "durasi" | Bias riset | Tooltip kolom, data dictionary [§14](#14-data-dictionary-export), §13.5 keterbatasan eksplisit |
| Safari mobile mis-deteksi blur | Idle undercount | Page Visibility API + fallback; didokumentasi sebagai limitasi |
| Siswa "ngegantung tab" | Engagement palsu | **Frontend idle detector** (60s tanpa interaksi → emit `idle`) + **server-side cap** `session.max_active_gap_ms` di formula (dua mekanisme independen); didokumentasi sebagai limitasi |
| Multi-tab over-count `material_seconds` | Inflated total exposure | `materials_opened` pakai DISTINCT; over-count durasi disengaja, didokumentasi |
| Material punya file attachment (PDF/dokumen) → siswa baca offline | Tracking buta | Track event `download` (route khusus, §7.1) sebagai proxy completion; limitasi didokumentasi |
| Threshold riset berubah saat analisis | Re-deploy code | Threshold pindah ke config-driven [§7](#7-aturan-selesai--risiko-config-driven) |
| Heartbeat endpoint jadi bottleneck | Latency naik | Insert sync OK untuk 80 user; >200 user pindah ke queued insert |

---

## 12. Open Questions

1. Threshold "selesai" material default OK, atau peneliti punya angka spesifik?
2. Window risiko default `rolling_7d` — peneliti mau `iso_week`?
3. Siswa boleh self-view progres? (saat ini non-goal)
4. Retention 90 hari OK, atau perlu lebih (mis. 1 semester = 180 hari)?
5. Material `type=link` perlu juga di-track click + return time?
6. Apakah peneliti = super_admin, atau perlu role baru `researcher` dengan akses lebih ketat?

---

## 13. Research Spec

**Bagian ini wajib dibaca oleh siapa pun yang akan analisis data hasil tracking.**

### 13.1 Posisi penelitian

Sistem ini menyediakan **data observasional**. Bukan eksperimen.
Tidak ada kontrol acak; semua siswa dapat treatment yang sama
(LMS standar). Karena itu, analisis yang valid:

- Deskriptif (distribusi durasi, korelasi engagement vs nilai).
- Kuasi-eksperimen kalau peneliti membandingkan periode/kelas dengan
  variasi treatment di luar sistem (mis. metode mengajar berbeda).

### 13.2 Variabel & definisi operasional

| Variabel | Definisi | Sumber | Satuan |
|----------|----------|--------|--------|
| `material_active_seconds_total` | Total `active_seconds` di session trackable=Material | `learning_progress_sessions` | detik |
| `material_completion_rate` | Σ material "selesai" ([§7.1](#71-material-dianggap-selesai)) / Σ material published | derived | rasio 0–1 |
| `assignment_submit_rate` | Σ submitted / Σ published assignment | `assignment_submissions` | rasio 0–1 |
| `assignment_late_rate` | Σ `is_late=true` / Σ submitted | sda | rasio 0–1 |
| `exam_attempt_rate` | Σ exam dengan `exam_sessions.submitted_at IS NOT NULL` / Σ published exam | `exam_sessions` | rasio 0–1 |
| `exam_avg_score` | Mean `exam_sessions.total_score` per siswa | sda | skala max_score (umumnya 100) |
| `assignment_avg_score` | Mean `assignment_submissions.score` per siswa | `assignment_submissions` | skala max_score |
| `daily_engagement_seconds` | Σ `material_seconds + assignment_seconds + exam_seconds` per hari | `learning_progress_daily_rollups` | detik/hari |
| `risk_status` | enum 🔴/⚠️/✅ ([§7.2](#72-status-risiko)) | derived | nominal |

### 13.3 Unit analisis

Standar yang direkomendasikan:

- **Per siswa per classroom_subject per periode** (mis. minggu). Granularitas
  ini balance antara N besar dan signal yang stabil.
- Alternatif: per siswa per material (kalau hipotesis ada di level konten).

### 13.4 Retention & anonimisasi

- Raw event di-purge **90 hari** (default config). Setelah itu hanya
  sessions + rollup yang tersisa.
- **Implikasi presisi:**
  - **Tetap utuh setelah purge:** identitas sesi (kapan dibuka, kapan
    ditutup, total active/idle seconds, urutan sesi per resource).
    Sumber: `learning_progress_sessions`.
  - **Hilang setelah purge:** rekonstruksi *intra-session* (urutan
    blur/focus dalam 1 sesi, micro-pattern interaksi). Tidak ada
    metric riset standar yang bergantung pada granularitas ini —
    yang dipakai analisis di [§13.2](#132-variabel--definisi-operasional)
    semuanya derivable dari sessions/rollup.
- Sessions & rollup disimpan **selama project**.
- Export anonim ([§14.3](#143-mode-anonim)) memakai HMAC dengan secret
  yang disimpan di `.env` (`LEARNING_PROGRESS_PSEUDO_SECRET`). Secret
  yang sama → mapping siswa konsisten antar export (memungkinkan
  longitudinal). Secret diganti → mapping baru (re-identifikasi
  perlu admin).

### 13.5 Keterbatasan pengukuran (wajib disebut di laporan)

1. **Tab nganggur tetap dicatat hingga 60 detik.** `max_active_gap_ms`
   = 60s. Siswa yang tinggal tab tanpa interaksi maksimal menyumbang
   60s "active" sebelum diklasifikasi idle.
2. **Multi-tab same material over-count durasi** (lihat [§5.4](#54-payload-validation)).
3. **Material dengan file attachment (PDF/dokumen)**: yang tercatat
   hanya waktu di halaman detail + event `download`; pembacaan offline
   setelah download buta total.
4. **Material dengan `link_url` (eksternal)**: hanya waktu di halaman
   LMS sebelum klik; waktu di situs eksternal tidak ter-track.
5. **Browser support `Page Visibility API`**: Safari mobile lama
   bisa mis-deteksi; bias arah idle undercount.
6. **Clock drift client**: event dengan drift > `validation.max_clock_drift_minutes`
   (default 10) di-reject — bisa menyebabkan **missing data** kalau
   device siswa salah jam.
7. **Siswa tidak login sama sekali**: tidak ada row rollup → di
   export muncul sebagai siswa terdaftar dengan semua kolom durasi
   = 0 (eksplisit, bukan NULL). Lihat [§14.4](#144-missing-data).

### 13.6 Window observasi standar

- **Harian:** rollup, default reporting.
- **Mingguan:** `rolling_7d` (7×24 jam terakhir) ATAU `iso_week`
  (Senin 00:00 — Minggu 23:59 Asia/Jakarta) — pilih satu, konsisten
  di seluruh analisis. Default sistem: `rolling_7d` (config).
- **Semester:** `academic_year + semester` dari `classroom_subjects`.

### 13.7 Reproducibility checklist (untuk peneliti)

Sebelum publish hasil, cek:

- [ ] Versi `config/learning_progress.php` saat data dikumpul ter-arsip
      (commit hash + tanggal).
- [ ] Definisi window (§13.6) terdokumentasi.
- [ ] Mode export (raw/anonim) terdokumentasi.
- [ ] Keterbatasan §13.5 dicantumkan di limitasi paper.
- [ ] Threshold "selesai" + "risiko" yang dipakai di-disclose.

---

## 14. Data Dictionary Export

### 14.1 File yang di-export

Action **Export to Excel** di `CourseProgressResource` menghasilkan
1 workbook dengan 4 sheet:

1. `students` — daftar siswa di classroom_subject + atribut.
2. `daily_rollups` — 1 row per (siswa × tanggal).
3. `submissions` — 1 row per assignment submission.
4. `exam_sessions` — 1 row per exam session.

### 14.2 Kolom per sheet

**Sheet `students`:**

| Kolom | Tipe | Format | Null policy |
|-------|------|--------|-------------|
| student_id ATAU student_pseudo_id | string | UUID atau HMAC hex | never null |
| full_name | string | UTF-8 | empty string di anonim |
| nisn | string | digits | empty kalau tidak ada |
| classroom_name | string | mis. "X IPA 1" | never null |
| classroom_subject_id | string | UUID | never null |
| subject_name | string | UTF-8 | never null |
| academic_year | string | "2025/2026" | never null |
| semester | int | 1 atau 2 | never null |
| is_active | bool | TRUE/FALSE | never null |
| tracking_opt_out | bool | TRUE/FALSE | never null |

**Sheet `daily_rollups`:**

| Kolom | Tipe | Format | Null policy |
|-------|------|--------|-------------|
| student_id / student_pseudo_id | string | sda | never null |
| classroom_subject_id | string | UUID | never null |
| date | string | `YYYY-MM-DD` (Asia/Jakarta) | never null |
| material_seconds | int | detik | 0 kalau tidak ada akses |
| assignment_seconds | int | detik | 0 |
| exam_seconds | int | detik | 0 |
| materials_opened | int | distinct count | 0 |
| assignments_worked | int | distinct count | 0 |
| exams_attempted | int | distinct count | 0 |

**Sheet `submissions`:**

| Kolom | Tipe | Format | Null policy |
|-------|------|--------|-------------|
| submission_id | string | UUID | never null |
| student_id / student_pseudo_id | string | sda | never null |
| assignment_id | string | UUID | never null |
| assignment_title | string | UTF-8 | never null |
| deadline | string | `YYYY-MM-DD HH:mm` (Asia/Jakarta) | never null |
| submitted_at | string | sda atau empty | empty = belum submit |
| is_late | bool | TRUE/FALSE | never null (FALSE kalau belum submit) |
| score | decimal | 2 desimal | empty = belum dinilai |
| max_score | decimal | 2 desimal | never null |
| time_on_page_seconds | int | dari `learning_progress_sessions` | 0 kalau tidak ada |

**Sheet `exam_sessions`:**

| Kolom | Tipe | Format | Null policy |
|-------|------|--------|-------------|
| session_id | string | UUID | never null |
| student_id / student_pseudo_id | string | sda | never null |
| exam_id | string | UUID | never null |
| exam_title | string | UTF-8 | never null |
| starts_at | string | `YYYY-MM-DD HH:mm` (Asia/Jakarta) | never null |
| started_at | string | sda | empty = belum mulai |
| submitted_at | string | sda | empty = belum submit |
| submission_reason | string | `manual` / `auto_timeout` | empty = belum submit |
| duration_minutes_configured | int | dari exam | never null |
| duration_seconds_actual | int | `submitted_at − started_at` | 0 kalau belum submit |
| total_score | decimal | 2 desimal | empty = belum dinilai |
| max_total_score | decimal | 2 desimal | never null |

### 14.3 Mode anonim

Filter "Export mode" di action:
- **Raw**: kolom `student_id` + `full_name` + `nisn` apa adanya.
- **Anonim**: kolom `student_pseudo_id` (HMAC), `full_name` empty,
  `nisn` empty. Mapping stabil selama secret `.env` tidak berubah.

### 14.4 Missing data

- Siswa terdaftar tapi tidak ada rollup row di tanggal X → di sheet
  `daily_rollups` row tetap dihasilkan dengan semua kolom durasi = 0
  (bukan NULL). Mempermudah pivot/aggregate di Excel.
- Submission/exam session yang belum diisi → kolom tanggal/skor =
  empty string (bukan "0" atau "—"), supaya parser deteksi missing.
- Konsistensi format **wajib**: tanggal `YYYY-MM-DD`, waktu
  `YYYY-MM-DD HH:mm`, timezone selalu Asia/Jakarta.

### 14.5 Manifest

Setiap export disertai sheet `_manifest`:

| Field | Nilai |
|-------|-------|
| exported_at | timestamp Asia/Jakarta |
| exported_by | user_id (super_admin atau guru) |
| mode | `raw` / `anonim` |
| config_hash | SHA-256 isi `config/learning_progress.php` |
| app_commit | git short SHA (`exec git rev-parse --short HEAD`) |
| date_range | "YYYY-MM-DD .. YYYY-MM-DD" |
| classroom_subject_id | UUID |

`config_hash` + `app_commit` mendukung [§13.7](#137-reproducibility-checklist-untuk-peneliti).

---

## 15. Storage & Perf Budget

### 15.1 Estimasi volume

**Asumsi explicit:**
- 80 siswa aktif.
- 5 hari belajar/minggu.
- 30 menit active total/siswa/hari (dijumlah dari semua trackable:
  material + assignment + exam).
- Heartbeat tiap 20 detik saat tab aktif = 3 event/menit.
- Tambahan ~3 event non-heartbeat/sesi (open/focus/blur/close).
- ~2 sesi/siswa/hari → ~6 event non-heartbeat/siswa/hari.

**Per siswa/hari:** 30 menit × 3 = 90 heartbeat + 6 lifecycle = **~96 event**.

**Total cluster:**

| Tabel | Row/hari aktif | Row/tahun (250 hari) | Bytes/row | Ukuran/tahun |
|-------|----------------|----------------------|-----------|--------------|
| `learning_progress_events` | 80 × 96 ≈ **7,700** | ≈ 1.9 juta | ~250 B | **≈ 480 MB** |
| `learning_progress_sessions` | 80 × 2 ≈ **160** | ≈ 40k | ~300 B | ≈ 12 MB |
| `learning_progress_daily_rollups` | 80 × ~3 mapel ≈ **240** | ≈ 60k | ~200 B | ≈ 12 MB |

> Kalibrasi: angka di atas worst-case aktif penuh. Real-world biasanya
> 50–70% dari ini. Ukur ulang setelah 2 minggu data riil sebelum
> mengandalkan estimasi.

**Dengan retention 90 hari pada `events`,** footprint events stabil di
**≈ 170 MB** (rolling window 90/365 × 480 MB).

Total tracking footprint stable-state ≤ **200 MB**, jauh di bawah 50 GB
allocation [10-development-phases.md Fase 0](./10-development-phases.md#fase-0--pre-flight--infrastruktur).

### 15.2 Target performa

| Endpoint / Job | Target | Catatan |
|----------------|--------|---------|
| `POST /student/progress/heartbeat` | p95 ≤ 80ms | **Bulk insert wajib** (`DB::table('learning_progress_events')->insert($rows)` untuk seluruh batch dalam 1 query). Insert per-event akan melebihi target. Upsert session row terpisah (1 query). Total target ≤ 2 query/request. >200 user pindah ke queued. |
| `progress:rollup-daily` | < 30 detik untuk 1 hari × 80 siswa | Run di 02:00 saat traffic minim |
| `progress:close-stale-sessions` | < 5 detik | Setiap 5 menit |
| `progress:prune-old-events` | < 60 detik | Mingguan, batched delete `chunk(1000)` |
| Filament Course Progress list | p95 ≤ 500ms | Query ke `daily_rollups`, eager-load relasi |

### 15.3 Monitoring (wajib di Fase A)

- Log counter ke `storage/logs/progress-metrics.log` tiap 1 menit:
  `events_inserted_total`, `sessions_open_gauge`.
- Alert manual harian: cek log; kalau `events_inserted_total = 0`
  selama jam 07:00–17:00 hari kerja → bug frontend kemungkinan besar.

---

## 16. Catatan Penutup

Setelah doc ini disetujui:

1. Update [02-database-schema.md](./02-database-schema.md) dengan 3
   tabel baru + 2 kolom di `users`/`students`. Sekalian sinkronkan
   schema `materials` di doc 02 dengan migration aktual (kolom `type`
   sudah tidak ada — sumber: [create_materials_table](../database/migrations/2026_05_07_100020_create_materials_table.php)).
2. Tambah referensi sebagai **Fase 7** di [10-development-phases.md](./10-development-phases.md)
   (atau jadikan roadmap terpisah).
3. **Reproducibility riset:** saat fitur masuk production, buat **git
   tag `learning-progress-v1.0`** menandai commit di mana
   `config/learning_progress.php` pertama kali stabil. Manifest export
   ([§14.5](#145-manifest)) menyertakan `app_commit` sehingga peneliti
   bisa rekonstruksi config exact via `git show <commit>:config/learning_progress.php`.

*Doc ini turunan dari kebutuhan riset + observasi roadmap utama.
Konflik dengan §02 schema akan diselesaikan oleh §02 sebagai
authoritative selama belum di-merge.*
