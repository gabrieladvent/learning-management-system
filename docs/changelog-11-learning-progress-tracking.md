# Changelog — Learning Progress Tracking

Riwayat revisi untuk [11-learning-progress-tracking.md](./11-learning-progress-tracking.md).

Format mengikuti gaya [Keep a Changelog](https://keepachangelog.com/),
disesuaikan untuk dokumen spesifikasi (bukan software release).

---

## [implementation-fase-a] — 2026-05-25

**Bukan revisi spec** — log implementasi Fase A. Spec body (§§1–8, 13–15)
tidak berubah; §9 (roadmap) ditandai status & deviasi minor didokumentasi.

### Implemented

- 4 migrasi (`learning_progress_events`, `learning_progress_sessions`,
  `learning_progress_daily_rollups`, kolom `users.tracking_disclosure_seen_at`
  + `students.tracking_opt_out`).
- `config/learning_progress.php` lengkap dengan semua section spec.
- Enum `LearningProgressEventType` + trait `HasLearningProgress` (apply ke
  Material/Assignment/Exam).
- Action `RecordLearningProgress`: validasi full, sort batch, snapshot
  `classroom_subject_id`, opt-out skip, session comeback spawn dengan
  `meta.original_client_session_id`, `activity()->disableLogging()` saat
  heartbeat.
- Route `POST /student/progress/heartbeat` + `POST /student/progress/disclosure-seen`
  + `GET /student/materials/{material}/files/{media}/download` (proxy completion).
- 4 artisan commands: `progress:rollup-daily`, `progress:close-stale-sessions`,
  `progress:prune-old-events`, `progress:monitor-metrics` (schedule di
  `routes/console.php`).
- 25 feature test pass — formula §4.3, sort prereq, drift boundary, duration
  clamp, multi-tab, opt-out, ≤2 INSERT/UPDATE per request, LogsActivity
  silence, session comeback, snapshot policy, scope enforcement, sweeper,
  rollup idempotency, download proxy log, HTTP integration.

### Deviasi minor (didokumentasi di §9 Fase A)

- `LogOptions::dontSubmitEmptyLogs()` → `dontLogEmptyChanges()` (method spec
  tidak ada di Spatie ActivityLog v5.x; behavior equivalen).
- Timestamp storage = `Asia/Jakarta` (mengikuti `APP_TIMEZONE`), bukan UTC
  murni. Sweeper/pruner pakai `now()` app-tz. Rollup tetap konversi ke
  Asia/Jakarta untuk grouping date — semantik §3.5 terjaga. Switch ke UTC
  murni tertunda menunggu konfirmasi peneliti.
- `LearningProgressDailyRollup.date` cast pakai `date:Y-m-d` (bukan default
  `date`) supaya `updateOrCreate` idempotent.

### Open items setelah Fase A

- **TODO doc 02:** [02-database-schema.md](./02-database-schema.md) perlu
  ditambah 3 tabel baru + 2 kolom (sesuai §16.1 spec).
- **Fase B (frontend probe)** & **Fase C (Filament views)** belum mulai.
  Tanpa Fase B, kolom durasi di Filament view (Fase C) akan kosong karena
  belum ada event ter-ingest dari production traffic.

---

## [v5] — 2026-05-25

Revisi micro berbasis self-critique v4. Fokus: **stale references**
yang lolos saat threshold pindah ke config di v4, dan **1 edge case
nyata** (session comeback) yang tidak dispec sama sekali.

> **Catatan diminishing returns.** Ronde ini berisi mostly perubahan
> 1–2 baris (kecuali §3.2 comeback). Setelah v5, ronde berikutnya
> kemungkinan besar tidak menemukan issue substantif lagi tanpa
> input dari implementor / peneliti. Doc sudah cukup matang sebagai
> blueprint Fase A.

### Fixed (stale references setelah config-driven di v4)

- **§3.1 kolom `duration_ms`** — "range 0–60_000" hardcoded di tabel
  → diganti referensi `session.max_active_gap_ms` dengan default.
- **§3.2 session timeout** — "now()-5m" hardcoded → reference
  `session.timeout_minutes`.
- **§5.3 sweeper & pruner** — sama, hardcoded "5m" / "90 hari" →
  reference config.
- **§11 risiko tabel** — drift "10 menit" & sweeper "5 menit"
  hardcoded → reference config.
- **§13.5 limitasi #6** — drift "10 menit" hardcoded → reference config.
- **§2.1 design decision** — label "pilihan v3" → "pilihan saat ini"
  (label versi rentan stale tiap iterasi changelog).

### Clarified

- **§11 baris "Siswa ngegantung tab"** — bahasa lama menyamakan
  *frontend idle detector* (60s tanpa interaksi → emit event) dengan
  *server `max_active_gap_ms`* (cap delta di formula). Padahal dua
  mekanisme independen yang **kebetulan** keduanya 60s. Dipisah
  eksplisit.

### Added (edge case spec)

- **§3.2 "Session comeback"** — sub-section baru. Skenario: siswa
  idle > timeout → sweeper close → siswa kembali aktif dengan
  `session_id` lama di `sessionStorage`. Server **tidak** reopen
  session lama; **spawn session baru** server-side dengan
  `session_id` baru, simpan `original_client_session_id` di `meta`
  event pertama untuk audit. Alasan: reopen melanggar konsep "1 sesi
  = 1 periode kontinyu" dan bikin `active_seconds` tidak well-defined.
- **§9 Fase A test plan** — case baru: session comeback assertion.

### Not addressed (intentionally — diminishing returns)

Dua issue minor di critique v5 sengaja tidak di-patch supaya iterasi
ngga infinite:

- **§6.4 wording "IconColumn toggle-able dari row action"** —
  ambigu (IconColumn read-only di Filament). Implementor bisa pilih
  via Edit form atau RowAction terpisah saat coding; bukan blocker.
- **§14.5 `exec git rev-parse --short HEAD`** — production tanpa
  `.git` (deploy via rsync/zip) tidak bisa jalan. Implementor harus
  set commit hash via env var (`APP_COMMIT_SHA`) saat deploy. Catat
  saat Fase D bikin export bulk.

---

## [v4] — 2026-05-25

Revisi berbasis self-critique v3. Fokus: **menyelaraskan doc dengan
schema aktual** (Material `type` ternyata tidak ada di migration),
**konsistensi config-driven** (semua threshold pindah ke config),
dan **test coverage gap**.

### Fixed (factual error / kontradiksi internal)

- **Schema Material `type` tidak ada di migration aktual.** Doc 02
  masih menyebut enum `type=text|file|link`, tapi
  [create_materials_table](../database/migrations/2026_05_07_100020_create_materials_table.php)
  hanya punya `content` + `link_url` + media library. Diganti dengan
  derivation rule:
  - `text` = `content IS NOT NULL` AND tidak ada media AND `link_url IS NULL`
  - `file` = ada media di koleksi `material_files`
  - `link` = `link_url IS NOT NULL`
  Affected: §7.1, §11 (risiko PDF), §13.5 (limitasi). §16 ditambah
  catatan untuk sync doc 02.
- **§3.1 "≈8 juta row/tahun" → "≈1.9 juta row/tahun".** Sisa angka v2
  yang tidak ikut update saat §15.1 dikoreksi di v3.
- **§1.3 "heartbeat 15–30 detik cukup" → "fixed 20 detik".** v3
  changelog claim "finalisasi 20s" tapi line di §1.3 tidak ikut update.

### Changed (config-driven consistency)

- **§4.3 formula** — `MIN(60_000, ...)` → `MIN(MAX_GAP_MS, ...)` dengan
  `MAX_GAP_MS = config('learning_progress.session.max_active_gap_ms')`.
  Formula reproducible dari config tanpa lihat source code.
- **§5.4 validation thresholds** — `10 menit` drift, `50` events/req,
  `32 KB` payload semua dipindah ke `config.validation.*`. Konsisten
  dengan policy "semua threshold riset di config".
- **§7 config block** — tambah block `validation` (3 key) + `export`
  (1 key referensi env var pseudo secret).

### Resolved (ambiguity)

- **§3.2 `end_reason='merged'`** — enum value tanpa use case, dihapus.
  Sekarang hanya `closed` / `timeout` / nullable.
- **§3.3 `exam_seconds` definisi** — diperjelas: hanya waktu di
  **halaman detail exam** sebelum klik mulai dan sesudah submit; waktu
  mengerjakan ujian tidak include (sudah ada di `exam_sessions.started_at→submitted_at`).

### Added

- **§9 Fase A** — checklist baru:
  - Env var `LEARNING_PROGRESS_PSEUDO_SECRET` setup (.env + .env.example).
  - Route download material + log `activity('material_download')`.
- **§9 Fase A unit test** — 5 test case baru:
  - Event out-of-order → sort handling.
  - `duration_ms` over-cap → clamp (bukan reject).
  - Bulk insert assertion (≤ 2 query/request).
  - LogsActivity disabled saat heartbeat (100 insert → 0 activity_log row).
  - Material file completion via download route → query "selesai" return true.
- **§16 Reproducibility step** — instruksi tag git `learning-progress-v1.0`
  + cara peneliti rekonstruksi config via `git show <commit>:config/...`.

### Clarified

- **§10 Dependensi** — bahasa "fall back ke `activity_log` lama"
  diperjelas: Fase C tidak boleh mengandalkan log lama sebagai fallback
  permanen karena log itu dihapus saat Fase B selesai.
- **§16 step 3** — "arsipkan config di git" → instruksi konkret git tag
  + workflow rekonstruksi untuk peneliti.

### Open questions (tetap dari v3)

1. Threshold "selesai" material default OK atau ada angka spesifik?
2. Window risiko `rolling_7d` vs `iso_week`?
3. Siswa boleh self-view?
4. Retention 90 hari OK atau perlu 180 hari?
5. Material dengan `link_url` perlu tracking click + return time?
6. Peneliti = super_admin atau role baru `researcher`?

---

## [v3] — 2026-05-25

Revisi berbasis self-critique v2. Fokus: **menutup gap implementasi**
(response shape, file download spec, opt-out UI, bulk insert, audit
trail) dan **mengoreksi kesalahan faktual** (volume math).

### Added

- **§2.1 Design decision: events vs sessions-only** — section baru
  mendokumentasikan trade-off antara menyimpan raw events vs hanya
  sessions. Pilihan v3 = keep events, dengan justifikasi (re-compute
  formula, server authority, audit anti-fraud) dan biayanya (storage
  ~170 MB, privacy footprint lebih besar — ditebus retention 90 hari).
- **§3 header — daftar pengecualian konvensi terkonsolidasi**
  (bigIncrements PK, no soft delete, timezone rule).
- **§3.2 LogsActivity policy** — model `LearningProgressSession`
  apply trait Activitylog tapi **disabled saat heartbeat insert**;
  hanya log koreksi manual super admin. Mencegah polusi
  `activity_log` table.
- **§3.3 No-soft-delete note untuk rollup** — eksplisit, sebelumnya
  diam (pembaca berasumsi default konvensi global).
- **§3.6 Kebijakan pemisahan `activity_log` vs `learning_progress_*`** —
  tabel matrik authoritative source per kebutuhan + migrasi
  `material_view` log existing (dihapus saat Fase B live, tidak
  di-backfill ke sessions).
- **§4.3 Prasyarat komputasi formula** — server wajib sort event
  `ORDER BY occurred_at ASC`, komputasi inkremental ambil last event
  + batch baru.
- **§5.1 Response shape heartbeat** — tabel status code lengkap
  (204/422/403/429), retry policy frontend (no retry on 4xx, buffer
  pada 5xx max 50 event).
- **§6.4 Toggle `tracking_opt_out` di StudentResource** — spec UI
  konkret: Toggle field di Edit, IconColumn di table, scoped super
  admin, log otomatis.
- **§7.1 Spec event `download` untuk material `type=file`** — bukan
  via `learning_progress_events`, tapi via route download terpisah +
  `activity('material_download')`. Menjaga schema events ramping.
- **§15.2 Bulk insert requirement** — target p95 80ms hanya feasible
  dengan `DB::table()->insert($rows)` bulk, bukan loop per-event.
  Eksplisit "≤ 2 query/request".

### Changed

- **§15.1 Estimasi volume dikoreksi.** v2 menyebut 21,600 event/hari
  (≈ 7.9 juta/tahun, 2 GB/tahun); berdasarkan asumsi yang sama,
  hitung ulang menghasilkan **~7,700 event/hari aktif** (≈ 1.9
  juta/250 hari aktif, **~480 MB/tahun raw**, **~170 MB stable-state**
  dengan retention 90 hari). Total footprint stable-state turun dari
  "≤ 700 MB" → **"≤ 200 MB"**.
- **§13.4 Retention impact diperjelas.** v2 menyebut "rekonstruksi
  timeline detik-per-detik tidak mungkin > 90 hari" — bahasa ini
  mengesankan kehilangan banyak. Realitanya: yang hilang hanya
  intra-session detail (urutan blur/focus dalam 1 sesi). Identitas
  sesi (start/end/active/idle total) tetap utuh, dan **semua metric
  di §13.2 tetap derivable** dari sessions/rollup.
- **Status doc** v2 → v3.

### Fixed

- Inkonsistensi soft-delete (rollup tidak dieksplisitkan di v2).
- Volume math salah 3× di §15.1.
- Ambiguitas activity_log vs learning_progress_events (duplikasi
  semantik tidak di-resolve di v2).
- Formula §4.3 non-deterministik karena event sort tidak di-mandate.
- Material `type=file` "selesai" tidak punya event source (janji palsu
  di v2 §7.1).
- `tracking_opt_out` flag tidak punya UI (mustahil di-toggle di v2).
- API response shape hilang (implementor frontend buta).
- LogsActivity trait pada sessions tidak disebut (klaim audit kosong).
- Bulk insert requirement implicit (target perf tidak achievable
  tanpa ini).

### Migration impact

Tetap tidak ada (proposal). Tambahan minor sejak v2:

- Route baru `GET /student/materials/{material}/files/{media}/download`
  (Fase A).
- Update [MaterialController.php:26](../app/Http/Controllers/Student/MaterialController.php#L26):
  hapus `activity('material_view')` call saat Fase B live.

### Open questions (tetap dari v2, belum di-resolve)

1. Threshold "selesai" default OK atau ada angka spesifik peneliti?
2. Window risiko `rolling_7d` vs `iso_week`?
3. Siswa boleh self-view? (saat ini non-goal)
4. Retention 90 hari OK atau perlu 180 hari?
5. Material `type=link` perlu tracking click + return time?
6. Peneliti = super_admin atau role baru `researcher`?

---

## [v2] — 2026-05-25

Revisi besar berdasarkan critique internal. Fokus utama: **menutup gap
penelitian** dan **memperbaiki keputusan teknis** yang akan menyulitkan
implementasi atau analisis data.

### Added

- **§13 Research Spec** — bagian baru: posisi penelitian (observasional),
  definisi operasional 9 variabel, unit analisis, window observasi
  standar, keterbatasan pengukuran eksplisit, reproducibility checklist
  untuk peneliti.
- **§14 Data Dictionary Export** — bagian baru: kolom per sheet (4 sheet:
  students, daily_rollups, submissions, exam_sessions), tipe & format,
  null policy, mode anonim (HMAC pseudo-id), missing-data convention,
  sheet `_manifest` (config_hash + app_commit) untuk reproducibility.
- **§15 Storage & Perf Budget** — bagian baru: estimasi volume (≈ 8 juta
  event/tahun, 700 MB/tahun footprint), target performa per endpoint/job,
  monitoring counter & alert plan.
- **§3.4 Snapshot policy** — `classroom_subject_id` di-snapshot saat
  insert event/session, tidak dinamis via relasi (menutup risiko bias
  saat guru pengampu ganti pertengahan semester).
- **§3.5 Timezone rule** — eksplisit: storage UTC, basis rollup
  `received_at` (bukan `occurred_at` client), grouping date dikonversi
  ke Asia/Jakarta.
- **§4.3 Formula `active_seconds` / `idle_seconds`** — pseudocode definisi
  operasional yang reproducible.
- **§5.4 Payload validation** — tabel aturan per field, cap
  `duration_ms` 0–60_000 (clamp, bukan reject), batas array event 50/req,
  batas payload 32 KB (mengakomodasi `sendBeacon` Safari), multi-tab
  policy.
- **§7 Config-driven thresholds** — file `config/learning_progress.php`
  dengan threshold completion, risk, retention, session timeout. Tidak
  ada lagi angka hardcoded di kode.
- **§8.1 Consent flow** — kolom `users.tracking_disclosure_seen_at`,
  `students.tracking_opt_out`. Banner sekali tampil + opt-out admin.
- **§8.4 Anonimisasi export** — HMAC-SHA256 dengan secret di `.env`,
  mapping konsisten selama secret tetap.
- **§11 Risiko & Mitigasi** — 4 baris baru: multi-tab, PDF download,
  threshold berubah, heartbeat bottleneck.
- **§15.3 Monitoring** — counter log + alert plan, wajib di Fase A
  (sebelumnya tidak ada sama sekali).

### Changed

- **§3.1 `learning_progress_events` PK** — `uuid` → `bigIncrements`.
  Alasan: tabel volume tinggi (~8 juta row/tahun), UUID random
  menyebabkan B-tree fragmentation. Pengecualian dari konvensi global
  UUID didokumentasi eksplisit.
- **§3.2 `learning_progress_sessions`** — **hapus soft delete**. Row
  ini di-upsert ribuan kali; soft delete tidak menambah nilai. Koreksi
  via update + activity_log.
- **§3.2** — kolom baru `end_reason` (`closed` / `timeout` / `merged`)
  untuk audit.
- **§3.3 `daily_rollups`** — kolom baru `computed_at` (tracking kapan
  row terakhir di-rollup).
- **§7 Aturan "Selesai" & Risiko** — semua threshold dipindah ke config.
  Risk window dipilih antara `rolling_7d` (default) atau `iso_week`
  via config.
- **§7.2 `class_avg` definition** — eksplisit: rata-rata siswa aktif
  (`is_active=true`) di kelas, cached 15 menit.
- **§9 Fase A** — retention job (`progress:prune-old-events`) dipindah
  dari Fase D (opsional) ke **Fase A (wajib)**. Tanpa retention,
  storage akan jebol.
- **§9 Fase A** — monitoring counter ditambahkan sebagai item wajib.
- **§9 Fase A** — durasi naik dari "3–4 hari" → **"4–5 hari"** karena
  scope bertambah (config, validation lengkap, monitoring, opt-out).
- **§9 Fase D** — `progress:export-all` bulk command dipindah dari
  Fase C ke Fase D (Fase C cukup Excel export per resource).
- **§4.2** — enum class di-rename `LearningProgressEvent` →
  `LearningProgressEventType` untuk menghindari bentrok nama model.
- **§5.1** — heartbeat interval di-finalisasi 20 detik (sebelumnya
  "15–30 detik" — ambigu untuk implementor).
- **§11** — baris "Heartbeat hilang" mitigasi diperjelas
  (`end_reason='timeout'`).

### Removed

- Tidak ada section yang dihapus, hanya re-organisasi.

### Fixed

- Inkonsistensi UUID PK di tabel volume tinggi (§3.1).
- Tidak ada definisi formal `active_seconds` (§4.3 baru).
- Threshold "selesai" hardcoded — sumber bias riset (§7 config).
- Multi-tab over-counting belum dibahas (§5.4 + §13.5).
- Material `type=file` blindspot tidak disebut (§13.5 + §11).
- Window risiko ambigu ("minggu ini" tanpa definisi) — sekarang
  `rolling_7d` / `iso_week` (§13.6).
- Server-side validation hilang (§5.4).
- `sendBeacon` payload limit tidak diakui (§5.4 32 KB cap).
- Tidak ada anonimisasi untuk peneliti (§8.4 + §14.3).
- Tidak ada data dictionary konkret untuk export — sekarang lengkap
  per sheet (§14).
- Tidak ada estimasi storage/perf — sekarang ada angka konkret (§15).

### Migration impact

Tidak ada (proposal — belum di-implementasi). Tapi kalau v1 sudah
sempat di-prototype:

- Migrasi `learning_progress_events` perlu drop & recreate (PK
  type change `uuid` → `bigIncrements` tidak in-place).
- Migrasi `learning_progress_sessions` perlu drop kolom `deleted_at`
  + add kolom `end_reason`.
- Migrasi `learning_progress_daily_rollups` perlu add kolom
  `computed_at`.
- Migrasi baru di `users` & `students` (kolom consent/opt-out).
- File baru `config/learning_progress.php`.

### Open questions (carried over to v2)

Belum di-resolve, masih perlu input peneliti / stakeholder:

1. Threshold default OK atau ada angka spesifik dari peneliti?
2. Window risiko `rolling_7d` vs `iso_week`?
3. Siswa boleh self-view? (saat ini non-goal)
4. Retention 90 hari OK atau perlu 180 hari (1 semester)?
5. Material `type=link` perlu tracking click + return time?
6. Peneliti = super_admin atau role baru `researcher`?

---

## [v1] — 2026-05-25

Draft awal. Mendefinisikan konsep dasar:

- 3 tabel: events, sessions, daily_rollups.
- Trait `HasLearningProgress`.
- Endpoint heartbeat batched.
- Filament resource Pengajaran (scoped guru pengampu).
- Roadmap 4 fase (A backend, B frontend, C Filament, D polish).

Kelemahan utama yang ter-identifikasi di review (di-resolve di v2):

- UUID PK untuk tabel volume tinggi.
- Soft delete pada tabel high-churn.
- Threshold riset hardcoded.
- Tidak ada definisi formal `active_seconds`.
- Tidak ada data dictionary export.
- Cakupan penelitian dangkal (tidak ada research spec).
- Retention job ditaruh di "opsional".
- Tidak ada estimasi storage / target perf.
- Multi-tab & material `type=file` tidak dibahas.
