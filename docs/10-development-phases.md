# Rencana Pengembangan Per Fase

Dokumen ini menerjemahkan punch list di
[09-improvement-recommendations.md](./09-improvement-recommendations.md)
menjadi **fase pengembangan berurutan** yang bisa dieksekusi satu demi
satu. Setiap fase punya entry condition, deliverable konkret, dan exit
criteria. Pengerjaan tidak harus selesai semua sekaligus — go-live
penelitian valid setelah **Fase 0–3** selesai.

> **Catatan baca:** dokumen ini turunan dari dokumen audit (§09).
> Detail teknis, file path, dan rasional setiap item ada di sana.
> Dokumen ini fokus ke **urutan kerja**, **dependensi antar fase**, dan
> **checklist eksekusi**.

---

## Ringkasan Fase

| Fase | Nama | Durasi (1 dev) | Status go-live | Blocking dependency |
|------|------|----------------|----------------|---------------------|
| 0 | Pre-flight & Infrastruktur | 0.5–1 hari | **wajib** | — |
| 1 | Critical Data Integrity | 1–1.5 hari | **wajib** | Fase 0 |
| 2 | Notifikasi & Dashboard Siswa | 3–4 hari | **wajib** | Fase 1 |
| 3 | Audit Trail & Security | 1.5–2 hari | **wajib** | Fase 1 |
| 4 | Production Scaling | 1.5–2 hari | disarankan untuk 80+ user | Fase 2 |
| 5 | Data Export Penelitian | 1–1.5 hari | wajib sebelum analisis | Fase 3 |
| 6 | Polish & Nice-to-Have | 1–2 hari | opsional | — |

**Total estimasi Fase 0–5: 8–12 hari kerja** (sejalan dengan §15 di doc
audit). Distribusi spesifik per fase ada di section masing-masing.

---

## Fase 0 — Pre-flight & Infrastruktur

**Tujuan:** Pastikan environment (lokal + staging + production) siap
sebelum nulis baris kode baru. Fase ini ringan tapi **blocker** — kalau
timezone salah, semua deadline yang di-set di Fase 1+ akan melenceng 7
jam.

**Entry condition:** Repo bisa di-clone, `composer install` & `npm install`
sukses, `php artisan migrate:fresh --seed` bersih.

### Checklist

- [ ] **Verifikasi PHP extension di server target** (`php -m`):
      `pdo_mysql`, `mbstring`, `bcmath`, `gd` atau `imagick`, `zip`,
      `xml`, `fileinfo`. Referensi: §FAQ stack (point 1).
- [ ] **MySQL ≥ 8.0** atau MariaDB ≥ 10.5 (butuh JSON native untuk
      `notifications` & `activity_log`).
- [ ] **Disk space ≥ 30–50 GB** di partisi storage (worst-case 80 siswa
      × 3 mapel × 6 tugas × 10 MB ≈ 14 GB + materi guru + backup).
- [ ] **HTTPS aktif** (Let's Encrypt / nginx reverse proxy). Password
      siswa default = tanggal lahir, sniffing risk di jaringan sekolah.
- [ ] **`APP_ENV=production`, `APP_DEBUG=false`** di `.env` production.
- [ ] **Timezone fix** (§10.0):
      - Edit [config/app.php:68](../config/app.php#L68):
        `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),`
      - Tambah `APP_TIMEZONE=Asia/Jakarta` di `.env` & `.env.example`.
      - **Test:** input deadline "23:59" via Filament guru, reload,
        verifikasi tetap "23:59" (bukan "06:59" hari berikutnya).
- [ ] **`php artisan storage:link`** di server target.
- [ ] Verifikasi seeder dasar jalan: `php artisan migrate:fresh --seed`
      menghasilkan 3 guru + 6 siswa + 2 kelas + 3 mapel (§9.1, sudah
      selesai).

### Deliverable

- Server staging/production yang lulus semua checklist di atas.
- Catatan ringkas di README (atau internal note) berisi versi PHP/MySQL
  & path storage yang dipakai.

### Exit criteria

Login sebagai guru & siswa di server target sukses, deadline form
Filament tersimpan dengan timezone benar, file upload tersimpan & bisa
diakses kembali.

---

## Fase 1 — Critical Data Integrity

**Tujuan:** Pastikan **tidak ada data ujian/tugas yang hilang** akibat
edge case. Ini fase yang paling tidak bisa di-skip — kalau auto-submit
tidak jalan, data penelitian bias.

**Entry condition:** Fase 0 selesai (terutama timezone).

### 1.1 Auto-submit ExamSession yang lewat waktu (§1.2) 🔴

- [ ] Migrasi: tambah kolom `submission_reason` di tabel `exam_sessions`
      (default `'manual'`).
- [ ] Buat `app/Console/Commands/AutoSubmitExpiredExamSessionsCommand.php`:
      query session `submitted_at IS NULL`, filter PHP-side berdasarkan
      `started_at + duration_minutes`, panggil
      [ExamGrader::grade()](../app/Services/ExamGrader.php) inline, set
      `submission_reason = 'auto_timeout'`.
- [ ] Daftarkan di [routes/console.php](../routes/console.php):
      ```php
      Schedule::command('exam:auto-submit-expired')
          ->everyTwoMinutes()
          ->withoutOverlapping()
          ->onOneServer();
      ```
- [ ] Pasang cron OS di server:
      `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

### 1.2 Late submission flag (§5.1) 🟠

- [ ] Migrasi: tambah kolom `is_late` boolean di `assignment_submissions`.
- [ ] Update [SubmitStudentAssignment.php](../app/Actions/Student/SubmitStudentAssignment.php):
      jangan throw saat deadline lewat; set `is_late = submitted_at > deadline`.
- [ ] Filament submission list/detail: tampilkan badge "Terlambat" +
      `submitted_at->diffForHumans(deadline)`.

### 1.3 Backup harian (§13.1) 🟠

- [ ] Buat `~/.my.cnf` (chmod 600) berisi credentials MySQL.
- [ ] Crontab entry harian (jam 01:00):
      `0 1 * * * cd /path/to/lms-app && mysqldump dbname > storage/backups/db-$(date +\%F).sql && tar -czf storage/backups/files-$(date +\%F).tar.gz storage/app/public`
- [ ] Sediakan folder `storage/backups/` (gitignored).
- [ ] **Test restore di staging** dengan `db-YYYY-MM-DD.sql` terbaru —
      jangan tunggu insiden untuk tahu backup rusak.
- [ ] Rsync manual mingguan ke disk eksternal/cloud.

### Deliverable

- Command `php artisan exam:auto-submit-expired` jalan tanpa error di
  staging dengan dummy session expired.
- Submission lewat deadline ter-flag `is_late = true`.
- File backup harian muncul di `storage/backups/` + restore test sukses.

### Exit criteria

Skenario test manual:
1. Mulai ExamSession, biarkan timer habis tanpa submit → setelah ≤2
   menit, `submitted_at` & `total_score` terisi otomatis dengan
   `submission_reason = 'auto_timeout'`.
2. Submit assignment lewat deadline → record tersimpan dengan `is_late = true`,
   bukan error.
3. Hapus database staging → restore dari backup terakhir → data utuh.

---

## Fase 2 — Notifikasi & Dashboard Siswa

**Tujuan:** Tutup gap UX siswa. Setelah fase ini siswa punya inbox,
dashboard yang informatif, dan menerima notifikasi event penting.

**Entry condition:** Fase 1 selesai. Auth siswa jalan.

### 2.1 Backend notifikasi siswa (§2.1) 🟠

- [ ] `app/Notifications/StudentAssignmentPublished.php`.
- [ ] `app/Notifications/StudentExamPublished.php`.
- [ ] `app/Notifications/StudentAssignmentGraded.php`.
- [ ] Trigger via `booted()` hook (konsisten dengan codebase, **bukan**
      observer folder):
      - [Assignment.php](../app/Models/Assignment.php) — dispatch saat
        `is_published` 0→1.
      - [Exam.php](../app/Models/Exam.php) — sama.
      - [AssignmentSubmission.php](../app/Models/AssignmentSubmission.php#L56-L63)
        — extend hook `saving` existing yang sudah set `graded_at` untuk
        dispatch `StudentAssignmentGraded` saat `score` null→non-null.

### 2.2 UI inbox / dropdown notifikasi (§2.2) 🟠

- [ ] Komponen `NotificationsDropdown.tsx` di
      [resources/js/Layouts/StudentLayout.tsx](../resources/js/Layouts/StudentLayout.tsx).
- [ ] Endpoint `GET /student/notifications` (list, paginated).
- [ ] Endpoint `POST /student/notifications/{id}/read` (mark as read).
- [ ] Polling 30 detik via `router.reload({ only: ['notifications'] })`
      — pattern sudah dipakai di
      [ExamTake.tsx](../resources/js/Pages/Exam/ExamTake.tsx).

### 2.3 Identitas siswa di dashboard (§3.1) 🟠

- [ ] Backend [GetStudentDashboard.php](../app/Actions/Student/GetStudentDashboard.php):
      tambah `homeroom_teacher_name` di `meta`
      (`$primaryClassroom?->teacher?->full_name`).
- [ ] Frontend [resources/js/Pages/Dashboard/](../resources/js/Pages/Dashboard/):
      kartu top berisi nama siswa, kelas, wali kelas, tahun ajaran +
      semester aktif. Fallback avatar = initial dari nama.

### 2.4 Section To-Do di dashboard (§3.2) 🟠

- [ ] Backend `GetStudentDashboard`: tambah array `todo` dengan filter:
      - **Tugas pending:** `is_published = true` AND `available_from <= now()`
        AND (`available_until IS NULL` OR `available_until >= now()`) AND
        siswa belum submit.
      - **Tugas overdue:** sama + `deadline < now()` → render merah.
      - **Ujian mendekat:** `is_published = true` AND `starts_at
        BETWEEN now() AND now()+7d` AND siswa belum submitted.
      - **Ujian available sekarang:** `available_from <= now() AND
        available_until >= now()` AND belum dikerjakan.
- [ ] Frontend: section "To-Do" dengan link langsung ke item.

### Deliverable

- Siswa login → header punya bell notifikasi yang berfungsi.
- Guru publish assignment → siswa di kelas terkait dapat notif & lihat
  di inbox.
- Guru grade submission → siswa pemilik dapat notif.
- Dashboard siswa menampilkan kartu identitas + to-do list.

### Exit criteria

Walk-through end-to-end: login siswa baru, lihat dashboard berisi kelas
& wali kelas, klik bell (kosong), tunggu guru publish assignment di
panel lain, ≤30 detik kemudian notifikasi muncul, klik → masuk halaman
assignment.

---

## Fase 3 — Audit Trail & Security

**Tujuan:** Lengkapi data yang dibutuhkan analisis penelitian +
hardening dasar yang ringan tapi penting.

**Entry condition:** Fase 1 selesai (model assignment_submission sudah
punya `is_late`).

### 3.1 Activity log (§11.1) 🟠

- [ ] Verifikasi `AssignmentSubmission::getActivitylogOptions()`
      mencakup `submitted_at`, `score`, `graded_at`, `feedback`.
      (`ExamSession` sudah optimal — tidak diubah.)
- [ ] Tambah trait `LogsActivity` di
      [ExamAnswer.php](../app/Models/ExamAnswer.php) +
      `getActivitylogOptions()` log `created`/`updated`.
- [ ] Trigger manual log di endpoint Material show & download (§6.1):
      ```php
      activity('material_view')
          ->performedOn($material)
          ->causedBy($student->user)
          ->log('viewed');
      ```

### 3.2 Auth siswa (§7.1, §7.2) 🟠

- [ ] **Last login:** update `User::last_login_at` di
      [AuthenticateStudent.php](../app/Actions/Student/AuthenticateStudent.php)
      setelah sukses authenticate.
- [ ] **Reset password siswa:** header action di
      [StudentResource](../app/Filament/Resources/StudentResource.php)
      Edit page → generate `Str::random(8)`, simpan, tampilkan sekali
      via Filament notification, log di activity log.

### 3.3 Rate limiting endpoint student (§7.5) 🟠

- [ ] Tambah `->middleware('throttle:60,1')` di student auth group di
      [routes/web.php](../routes/web.php).
- [ ] Save-answer endpoint khusus boleh `throttle:120,1` (auto-save
      bisa frequent).

### 3.4 Storage cleanup verification (§11.3) 🟠

- [ ] Cek apakah `AssignmentSubmission` & `Student` pakai `SoftDeletes`.
- [ ] Test manual: hapus 1 submission dari Filament, verifikasi row
      `media` ikut hilang + file di `storage/app` terhapus.
- [ ] Kalau orphan: override `shouldDeletePreservingMedia()` atau hook
      `deleting` event.

### Deliverable

- Activity log siswa view material, jawab soal, edit submission
  tercatat.
- `last_login_at` siswa ter-update tiap login.
- Reset password siswa bisa dilakukan guru via Filament dengan log.
- Endpoint student protected throttle.

### Exit criteria

Query `Activity::where('log_name', 'material_view')->count()` > 0
setelah simulasi siswa buka beberapa material. Endpoint `POST
/student/exams/sessions/{id}/answer` kena throttle 429 setelah ≥120
request/menit dari 1 user.

---

## Fase 4 — Production Scaling (untuk 80+ user)

**Tujuan:** Item yang **tidak perlu** untuk 6 siswa dev/test, tapi
**worth-it** untuk production 85 user (5 guru + 80 siswa). Skip kalau
penelitian benar-benar pakai dataset seeder (6 siswa).

**Entry condition:** Fase 2 selesai (notifikasi siswa sudah ada — fase
ini membuat dispatch-nya non-blocking).

### 4.1 Queue worker via supervisor (§FAQ stack)

- [ ] Install supervisor di server (`apt install supervisor` atau
      equivalent).
- [ ] Buat config `/etc/supervisor/conf.d/lms-worker.conf`:
      ```ini
      [program:lms-worker]
      process_name=%(program_name)s_%(process_num)02d
      command=php /path/to/app/artisan queue:work --queue=default --tries=3 --max-time=3600
      autostart=true
      autorestart=true
      user=www-data
      numprocs=1
      redirect_stderr=true
      stdout_logfile=/path/to/app/storage/logs/worker.log
      stopwaitsecs=3600
      ```
- [ ] `supervisorctl reread && supervisorctl update`.
- [ ] Verifikasi `QUEUE_CONNECTION=database` di `.env` production.

### 4.2 `ShouldQueue` untuk notifikasi batch (§1.4)

- [ ] Tambah `implements ShouldQueue` di:
      - `StudentAssignmentPublished`
      - `StudentExamPublished`
      (Saat guru publish ke kelas 40 siswa, 40 insert tidak block
      request.)
- [ ] **Jangan** tambah `ShouldQueue` di `TeacherSubmissionAlert` —
      1 row insert sync OK (§1.4).

### 4.3 Email channel (§2.3)

- [ ] Pilih provider: Resend free tier (3000/bulan) cukup untuk 80
      siswa × 1–2 email/hari.
- [ ] Set `MAIL_MAILER=smtp` + credentials di `.env` production.
- [ ] Tambah `'mail'` di array `via()` HANYA untuk:
      - `StudentAssignmentGraded` (notif nilai keluar).
      - `StudentDeadlineReminder` (kalau §4.4 dikerjakan).
- [ ] **Jangan** kirim email untuk `Student*Published` — bakal spam
      inbox.

### 4.4 Reminder deadline (§1.3) 🟡

- [ ] `app/Jobs/SendAssignmentDeadlineReminders.php` — query Assignment
      dengan `deadline BETWEEN now() AND now()+24h`, kirim ke siswa
      yang belum submit.
- [ ] `app/Jobs/SendExamStartReminders.php` — query Exam dengan
      `starts_at BETWEEN now() AND now()+1h`.
- [ ] Schedule di [routes/console.php](../routes/console.php):
      ```php
      Schedule::job(new SendAssignmentDeadlineReminders)->dailyAt('07:00');
      Schedule::job(new SendExamStartReminders)->everyFifteenMinutes();
      ```
- [ ] `app/Notifications/StudentDeadlineReminder.php` (database + mail).

### 4.5 Dummy submission seeder untuk load test (§9.2) 🟡

- [ ] Buat seeder yang **diperluas** ke production-scale: 80 siswa,
      generate dummy ExamSession + AssignmentSubmission.
- [ ] **Hanya jalankan di staging** — bukan production.
- [ ] Test: ExamGrader cepat untuk 80 session sekaligus? Filament
      listing tahan 80 row?

### Deliverable

- Queue worker jalan continuous di production, log di `storage/logs/worker.log`.
- Email reminder terkirim ke 1 siswa uji coba.
- Load test di staging dengan 80 siswa sukses tanpa timeout.

### Exit criteria

Publish assignment ke kelas 40 siswa di staging: request guru selesai
<300ms (sebelumnya bisa 1–2 detik kalau sync). Worker log menunjukkan
40 notification job processed sukses.

---

## Fase 5 — Data Export Penelitian

**Tujuan:** Setelah penelitian jalan & data terkumpul, peneliti butuh
ekspor dalam format CSV/Excel untuk analisis di SPSS/Excel/Python.

**Entry condition:** Fase 3 selesai (activity log lengkap).

### 5.1 Export per resource (§11.2) 🟠

- [ ] Action "Export ke Excel" di
      [ExamResource](../app/Filament/Resources/ExamResource.php) —
      per exam, 1 baris per siswa, kolom = soal/atribut + nilai.
      (`maatwebsite/excel` sudah terpasang.)
- [ ] Action "Export ke Excel" di
      [AssignmentResource](../app/Filament/Resources/AssignmentResource.php)
      — per assignment, 1 baris per siswa, kolom = nilai, on-time/late,
      feedback.

### 5.2 Activity log export (opsional)

- [ ] Filament `ActivityLogResource` dengan filter
      (log_name, causer, date range) + export.

### 5.3 Bulk export command (opsional)

- [ ] `php artisan research:export-all` → generate satu zip berisi
      semua CSV (exam sessions, submissions, activity log, students,
      classrooms).
- [ ] Output ke `storage/exports/research-YYYY-MM-DD.zip`.

### Deliverable

- Peneliti bisa klik 1 button di Filament dan dapat Excel siap analisis
  untuk tiap ujian & tugas.

### Exit criteria

Dump 1 exam → buka di Excel → kolom konsisten, tidak ada cell kosong
yang seharusnya berisi data, tanggal & waktu dalam format yang bisa
di-parse (mis. ISO 8601 atau `YYYY-MM-DD HH:mm`).

---

## Fase 6 — Polish & Nice-to-Have

**Tujuan:** Item MEDIUM/LOW yang bisa dikerjakan kapan saja tanpa
mengganggu go-live.

**Entry condition:** Tidak ada — bisa diselipkan paralel.

### Checklist (pilih sesuai prioritas saat itu)

- [ ] **§3.3** Countdown ujian di dashboard (komponen `UpcomingExamCard.tsx`).
- [ ] **§3.4** Quick stats personal siswa (action `GetStudentStats`).
- [ ] **§4.3** Log `tab_blur` saat ujian (tabel `exam_session_events`).
- [ ] **§4.4** Toggle "Rilis hasil ujian" (`exams.results_released_at`).
- [ ] **§5.2** Audit trail resubmit per versi.
- [ ] **§5.3** Differentiate first submit vs resubmit di notifikasi guru.
- [ ] **§6.2** Material metadata visibility (created_at + teacher name).
- [ ] **§7.3** UI throttle login siswa (tampilkan "too many attempts").
- [ ] **§8.2** Cek storage symlink di deployment guide.
- [ ] **§9.3** `php artisan research:reset` (truncate submission + re-seed).
- [ ] **§10.1** Helper text "akan terlihat siswa pada tanggal X" di
      form Filament Assignment/Exam.
- [ ] **§12.2** Empty state UX pass di tiap halaman student.
- [ ] **§13.2** Edit README.md — research-deployment checklist
      (saat ini masih default Laravel boilerplate).

### Deliverable

Tidak ada deliverable mengikat — fase ini menyerap improvement
incremental berdasarkan feedback pemakaian.

---

## Dependensi Antar Fase (Diagram)

```
Fase 0 (Pre-flight)
   │
   ├──► Fase 1 (Critical Data Integrity)
   │       │
   │       ├──► Fase 2 (Notifikasi & Dashboard)
   │       │       │
   │       │       └──► Fase 4 (Production Scaling)
   │       │
   │       └──► Fase 3 (Audit & Security)
   │               │
   │               └──► Fase 5 (Data Export)
   │
   └──► Fase 6 (Polish) — paralel, no dependency
```

**Critical path go-live:** 0 → 1 → 2 → 3. Fase 4 & 5 boleh dikerjakan
paralel setelah 2 & 3 selesai.

---

## Minimum Viable Go-Live (jika waktu sangat terbatas)

Kalau benar-benar mepet, **minimum yang harus selesai**:

1. **Fase 0** (semua checklist).
2. **Fase 1.1** (auto-submit) + **1.3** (backup).
3. **Fase 2.1** (notifikasi backend) + **2.3** (identitas siswa di dashboard).
4. **Fase 3.2** (last login + reset password) + **3.3** (throttle).

Lewatkan dulu (kerjakan saat penelitian jalan):
- Fase 1.2 (`is_late`) — guru bisa cek deadline manual untuk minggu
  pertama.
- Fase 2.2 (UI inbox) — siswa cek dashboard manual.
- Fase 2.4 (To-Do dashboard) — ada di Fase 6.
- Fase 3.1 (activity log lengkap) — `ExamSession` sudah optimal,
  `ExamAnswer` bisa nyusul.
- Fase 4 & 5 — kerjakan minggu kedua.

Estimasi minimum: **3–4 hari kerja**.

---

## Tracking Progress

Tiap fase punya checklist. Saran workflow:

1. Buat issue/ticket per **section** (mis. "Fase 1.1 — auto-submit
   command"), bukan per fase utuh.
2. Cantumkan reference ke section dokumen audit (mis. `closes §1.2`)
   supaya konteks tidak hilang.
3. Setelah PR merged, centang checkbox di dokumen ini & di
   [09-improvement-recommendations.md §14](./09-improvement-recommendations.md#14-punch-list-prioritas-ringkas).
4. Update commit/PR template dengan link ke fase yang sedang dikerjakan.

---

*Dokumen ini ditulis sebagai turunan dari §09. Kalau ada konflik antara
kedua dokumen (mis. setelah revisi §09), §09 yang authoritative —
dokumen ini hanya struktur eksekusi.*
