# Rekomendasi Perbaikan & Peningkatan LMS

Dokumen audit menyeluruh untuk LMS yang akan dipakai pada **penelitian di sekolah**.

**Skala penelitian (production):** ±5 guru, 2 kelas, ±80 siswa, 3 mata
pelajaran inti. **Total ~85 user.** Aktivitas: upload materi, pengerjaan
ujian online, pengumpulan tugas, grading.

**Skala seeder (dev/test):** 3 guru, 2 kelas, 6 siswa, 3 mapel. Ini
sengaja kecil untuk smoke test lokal — **bukan** representasi load
production. Beberapa rekomendasi di putaran rev sebelumnya keliru
asumsikan production = scale seeder.

Tujuan dokumen ini: memberi *punch list* perbaikan dan peningkatan sebelum
website dipakai live untuk pengumpulan data penelitian. Skala kecil → tidak
perlu over-engineering, tapi **stabilitas, audit trail, dan pengalaman siswa**
harus rapi karena data yang dikumpulkan akan jadi data penelitian.

### Untuk siapa dokumen ini & cara baca

Dokumen ±1150 baris, ditulis gaya **dev-to-dev**. Saran rute baca per
audience:

- **Peneliti (non-developer):** baca TL;DR, §0 Ringkasan Eksekutif, dan
  §14 Punch List. Lewati selebihnya. Total ±50 baris.
- **Developer pendamping:** mulai dari §14 Punch List, lalu jump ke
  section detail per item yang dikerjakan. Lampiran A & B sebagai
  checklist.
- **Reviewer/skripsi advisor:** §0 + §14 cukup untuk skala perubahan.

Yang **bukan** scope dokumen ini:
- Spek implementasi (gunakan tiket/issue per section).
- Justifikasi metode penelitian — itu di proposal skripsi.
- Definisi "audit trail" yang diterima ethics committee — peneliti perlu
  konfirmasi sendiri ke advisor.

## TL;DR — koreksi dari revisi awal

Dokumen ini sudah beberapa putaran direvisi. Hal-hal penting yang saya
keliru di awal & sekarang sudah dikoreksi inline:

- **Skala production: ±85 user (5 guru + 80 siswa, 2 kelas, 3 mapel).**
  Seeder yang berisi 6 siswa hanya untuk dev/smoke test — bukan acuan
  load.
- Stack: **Laravel 12** (bukan 11). Default L12 sudah punya migrasi
  `jobs`, `sessions`, `cache`, `password_reset_tokens`, dan
  `routes/console.php` — tidak perlu dibuat ulang. Detail di §1.0.
- Beberapa job/schedule yang saya rekomendasikan awalnya **overshoot
  untuk dev (6 siswa)** tapi **sebenarnya relevan untuk production (85
  user)** — sudah dikoreksi.
- `ExamSession::getActivitylogOptions()` sudah optimal, `max_file_size_mb`
  sudah default 10 di form Filament, attempt limit ujian sudah ada via
  unique constraint + idempotent action. Detail di §11.1, §8.1, §4.2.

Section "Stack & infrastruktur (FAQ)" di bawah masih relevan — biarkan,
tapi numbers (concurrent user, request/detik) sudah disesuaikan ke skala
production.

### Konteks deployment (dikonfirmasi)

Berdasarkan diskusi:

- **Pola pemakaian:** campuran — ujian serentak per kelas (40 siswa
  bersamaan + auto-save) + tugas asinkron (low traffic).
- **Server:** VPS sendiri (DigitalOcean / Hetzner / etc) — full control,
  bisa setup supervisor, cron, tuning storage.
- **Email provider:** free tier (mis. Resend 3000/bulan). Cukup untuk 80
  siswa × 1–2 reminder/hari.

Implikasi penting:

1. Peak concurrent ~40 siswa × auto-save tiap 5 detik = ~80 req/detik di
   puncak ujian. Database queue + 1 worker supervisor cukup. Tidak butuh
   Redis.
2. Email reminder workable tanpa biaya, jadi §2.3 layak diaktifkan.
3. VPS bisa di-tuning: jalankan Supervisor (`queue:work`), cron OS
   (schedule:run + backup), nginx HTTPS, MySQL local. Pattern standar
   Laravel.

---

## Stack & infrastruktur — apa yang TIDAK perlu di-install (FAQ)

> Section ini awalnya hanya menjawab "apakah perlu Redis?" — tapi
> kemudian mencakup juga Horizon, Pulse, Reverb, Octane, S3, CDN, Sentry,
> dll. Lebih cocok jadi FAQ stack secara umum.

Stack saat ini (terkonfirmasi):

- Laravel 12, PHP ^8.2, MySQL.
- `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`.
- `BROADCAST_CONNECTION=log` (tidak ada WebSocket/Pusher).
- `MAIL_MAILER=log` (email belum benar-benar dikirim).
- Tidak ada Horizon / Pulse / Reverb / Telescope / Octane / Pusher /
  Predis di [composer.json](../composer.json). Konfigurasi Redis di
  `.env` ada tapi default — server tidak harus punya Redis terpasang.

### Apakah perlu Redis?

**Jawaban singkat: Tidak (asal scope tetap 1 sekolah).** Untuk skala ~85
user concurrent saat puncak (misal ujian serentak satu kelas 40 siswa +
auto-save tiap 5–10 detik), peak load di kisaran **100–250 request/detik**.
MySQL + PHP-FPM tuned standar masih comfortable di angka ini. Redis baru
*menarik* mulai ~500 req/detik sustained atau kalau ada multi-server.

Catatan penting: **`QUEUE_CONNECTION=sync` yang saya rekomendasikan di
putaran sebelumnya kurang tepat untuk production 85 user.** Lihat tabel
bawah.

### Per komponen

| Komponen | Driver saat ini | Untuk ~85 user production | Redis worth-it kalau… |
|----------|----------------|----------------------------|------------------------|
| `CACHE_STORE` | `database` | biarkan `database`, atau `file` | hit rate >1000/detik, dashboard analytics berat. |
| `SESSION_DRIVER` | `database` | biarkan `database` | multi-server load-balanced. |
| `QUEUE_CONNECTION` | `database` | **biarkan `database`** + jalankan `queue:work` via supervisor. Untuk notif batch ke 40 siswa per kelas, sync bisa block request 1–2 detik. | job throughput sustained >10/detik. |
| `BROADCAST_CONNECTION` | `log` | biarkan `log` | ada feature real-time. Belum ada. |
| `MAIL_MAILER` | `log` | **`smtp` kalau §2.3 dikerjakan** (untuk 80 siswa, reminder email lebih praktis daripada minta semua selalu buka web). | — |

### Penjelasan per driver

**Cache — `database` aman.** Filament panel pakai cache untuk permission
lookup, view rendering. Tabel `cache` query lewat primary key, sub-ms.
Alternatif `file` lebih cepat lagi (no DB roundtrip), trade-off: file
cache numpuk, butuh `cache:prune-stale-tags` sesekali. Redis berlebihan.

**Session — `database` aman.** Setiap request authenticated UPDATE row.
Untuk 85 user dengan peak ~100 write/detik saat awal sesi, masih nyaman.
`file` driver juga oke kalau single server (tidak ada sticky session
concern).

**Queue — `database` + supervisor worker (KOREKSI dari rev sebelumnya).**
Putaran lalu saya rekomendasikan `sync` karena asumsi 6 siswa. Untuk 85
user production:
- Notifikasi batch `StudentAssignmentPublished` ke 40 siswa dalam satu
  request sync = +200–800ms latensi yang dirasakan guru saat klik
  Publish. Annoying.
- Auto-submit ujian: command artisan ($1.2) **tetap cukup di-jalankan
  inline** dari `schedule:run` (per 2 menit, maks ~40 session aktif).
- Notifikasi siswa per-event: queue lebih ramah.

**Rekomendasi production:**
```
QUEUE_CONNECTION=database
+ supervisor jalankan: php artisan queue:work --queue=default --tries=3 --max-time=3600
+ cron OS: * * * * * php artisan schedule:run
```

Redis baru perlu kalau Anda ingin Horizon dashboard / sustained
throughput tinggi. Untuk 85 user, supervisor + database queue masih
cukup.

**Broadcasting — tidak relevan.** Tidak ada fitur real-time. Untuk
dropdown notifikasi siswa (§2.2), cukup **polling tiap 30 detik** via
Inertia partial reload. WebSocket / Reverb / Pusher tidak perlu.

### Stack/tooling lain yang sering ditanyakan — semua skip

| Tool | Kegunaan | Skip karena |
|------|----------|-------------|
| Laravel Horizon | Dashboard Redis queue | Tidak pakai Redis queue. |
| Laravel Pulse | APM dashboard | Berguna mulai ~100 user. ~85 user → log + activitylog cukup. |
| Laravel Telescope | Local debug tool | OK untuk dev, **jangan** ke production. |
| Laravel Octane / FrankenPHP | Per-request speedup | Selisih <5ms di skala ini. |
| Laravel Reverb | WebSocket native | Tidak ada feature real-time. |
| Predis / ext-redis | Client Redis | Tidak pakai Redis. |
| CDN (Cloudflare, BunnyCDN) | Asset global delivery | 1 sekolah, traffic regional/LAN. |
| S3 / object storage | File storage | Local disk + backup harian cukup. |
| Sentry / Bugsnag | Error tracking remote | Laravel log + `tail -f` cukup. Tambahkan kalau ada budget. |
| Meilisearch / Algolia | Full-text search | Tidak ada UI search yang berat. |
| MySQL replication | Read replica | Single DB cukup. |

### Yang justru WAJIB diverifikasi di stack saat ini

1. **PHP extension** di server: `pdo_mysql`, `mbstring`, `bcmath`, `gd`
   atau `imagick` (untuk Spatie MediaLibrary thumbnail), `zip`, `xml`,
   `fileinfo`. Cek dengan `php -m` di server target.
2. **Versi MySQL ≥ 8.0** (kolom `JSON` di `notifications` &
   `activity_log` butuh JSON native). MariaDB 10.5+ juga oke.
3. **Disk space.** Estimasi worst-case upload untuk production 80 siswa:
   3 mapel × ~6 tugas × 80 siswa × 10 MB = **~14 GB**. Plus materi guru
   ~1–2 GB. Plus DB + activity log ~500 MB. **Sediakan 30–50 GB partisi
   storage** supaya nyaman dengan buffer backup lokal.
4. **`php artisan storage:link`** di server target (sering terlupa, lihat §8.2).
5. **HTTPS.** Karena password siswa default = tanggal lahir, login tanpa
   HTTPS di jaringan sekolah rawan sniffing. Pakai Let's Encrypt /
   reverse proxy nginx dengan TLS.
6. **`APP_ENV=production`, `APP_DEBUG=false`** sebelum penelitian
   dijalankan, supaya stacktrace tidak bocor ke siswa kalau ada error.
7. **`APP_TIMEZONE=Asia/Jakarta`** (atau zona lain yang sesuai). Default
   Laravel 12 = `UTC`. Kalau ini tidak di-set, deadline yang diinput guru
   "23:59" akan disimpan & ditampilkan ke siswa sebagai 06:59 WIB
   keesokan harinya. **Untuk penelitian, deadline yang melenceng 7 jam
   bisa membatalkan data**. Cek [config/app.php](../config/app.php) +
   `.env`.

### Kalau peneliti sudah punya Redis terpasang di server

Boleh dipakai (ubah env variable satu-satunya saja, no code change). Tapi
**jangan install Redis khusus untuk app ini** kalau server belum punya —
overhead operasional (monitoring memory, restart, OOM-kill, persistence
config) lebih mahal daripada manfaatnya di skala ~85 user.

### Kesimpulan ringkas

- Stack saat ini (`database` untuk semua, `log` untuk mail/broadcast) =
  **fine, tidak perlu diubah**.
- Kalau mau lebih simpel: `file` untuk session+cache, `sync` untuk queue.
- Redis, Horizon, Pulse, Reverb, Octane, Telescope, S3, CDN: **semua skip**.
- Yang justru perlu diverifikasi: PHP extension, MySQL version, HTTPS,
  storage symlink, disk space, `APP_DEBUG=false`.

---

## Konvensi prioritas

- 🔴 **CRITICAL** — risiko data hilang / penelitian gagal / siswa tidak bisa kerja. Wajib sebelum go-live.
- 🟠 **HIGH** — pengalaman siswa/guru jelek, atau audit trail tidak cukup untuk penelitian. Sebaiknya selesai sebelum go-live.
- 🟡 **MEDIUM** — quality-of-life. Boleh ditunda, tapi sebaiknya dikerjakan saat ada waktu.
- 🟢 **LOW** — nice-to-have, tidak relevan untuk skala penelitian kecil.

---

## 0. Ringkasan Eksekutif

Fitur inti sudah ada dan jalan (guru upload materi, buat tugas/ujian,
grading; siswa lihat kelas, mengerjakan, melihat nilai). Yang masih lemah
hanya beberapa titik konkret — bukan "banyak infrastruktur" seperti yang
dikira di revisi awal:

1. **Backend auto-submit ujian belum ada** — satu-satunya job + schedule
   yang benar-benar wajib. Risiko data hilang kalau siswa tutup tab di
   detik akhir. Detail lengkap di §1.
2. **Siswa tidak punya inbox notifikasi** — guru sudah dapat notif via
   `TeacherSubmissionAlert`; siswa nol. Cukup database channel + dropdown
   di header. Detail di §2.
3. **Dashboard siswa terlalu kosong** — tidak ada info kelas yang menonjol
   atau to-do tugas pending. Detail di §3.
4. **Late submission flag** belum ada (kolom `is_late` di
   `assignment_submissions`). Penting untuk audit trail penelitian.
   Catatan: **attempt limit ujian** sudah ada secara struktural via DB
   unique constraint + action idempotent — bukan blocker. Detail di §4,
   §5.
5. **Password reset siswa belum ada** — guard `student` belum punya broker
   di [config/auth.php](../config/auth.php). Untuk 80 siswa, tombol reset
   di panel guru masih scalable (estimasi 5–10 reset selama periode
   penelitian). Detail di §7.
6. **Seeder dataset penelitian** sudah selesai (2 kelas × 3 siswa, 3 guru,
   3 mapel inti, lengkap materi/tugas/ujian). Lihat §9.

Yang **tidak perlu** untuk skala 85 user: Redis/Horizon/Pulse, Reverb /
WebSocket, Octane, CDN, S3, anti-cheat berat (proctoring/webcam),
localization (sudah Indonesian native), 2FA, antivirus upload,
`academic_years` entity.

Yang **perlu** untuk production 85 user (yang sebelumnya saya kira tidak
perlu karena asumsi 6 siswa): supervisor + `queue:work`, `ShouldQueue`
untuk notifikasi batch, email channel (opsional tapi worth-it),
storage 30–50 GB, backup harian.

Yang **harus diverifikasi tapi mudah** sebelum go-live (hal-hal yang
sering luput): `APP_TIMEZONE=Asia/Jakarta` (default UTC bisa bikin
deadline salah jam), HTTPS aktif (password siswa default = tanggal lahir),
`APP_DEBUG=false`, dan storage symlink. Detail di Catatan Revisi 2.

---

## 1. Job, Queue, & Schedule — apa yang dibutuhkan dan untuk apa

### 1.0 Status infrastruktur saat ini

Yang **sudah ada by default** (lihat TL;DR di atas untuk daftar lengkap):
tabel `jobs`/`failed_jobs`/`job_batches`/`sessions`/`cache`,
`routes/console.php`, `TeacherSubmissionAlert` sync.

Yang **belum ada**:
- Folder `app/Jobs/` & `app/Console/Commands/` (di L12 opsional).
- Definisi schedule di `routes/console.php` (cuma `inspire` default).
- Job/command spesifik untuk auto-submit ujian (§1.2).

Driver `.env`: `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`,
`MAIL_MAILER=log`. Tidak perlu diubah (lihat FAQ stack di atas).

### 1.1 Matriks: fitur mana butuh job/queue/schedule?

Pertanyaan kuncinya: **fitur ini punya trigger user, atau harus jalan
sendiri di waktu tertentu?** Kalau ada trigger user (mis. siswa klik
submit), cukup sync. Yang butuh schedule hanya yang **tidak punya trigger
user** atau **harus jalan di waktu spesifik**.

| Fitur | Butuh job dispatch? | Butuh schedule (cron)? | Prioritas | Penjelasan |
|-------|---------------------|------------------------|-----------|------------|
| **Auto-submit ExamSession yang lewat waktu** | ✅ ya | ✅ tiap 1–2 menit | 🔴 wajib | Tidak ada trigger user. Kalau siswa tutup tab di detik akhir, session bertahan `submitted_at = null` selamanya. Lihat §1.2. |
| Notifikasi guru saat siswa submit | ❌ sync OK | ❌ | sudah ada | 1 row insert per submit, sync sub-100ms. |
| Notifikasi siswa saat assignment/exam dipublish | ❌ sync OK | ❌ | 🟠 (kontennya, bukan jobnya) | Trigger: `Assignment::saved()` saat `is_published` 0→1. Dispatch sync ke seluruh siswa di kelas — maksimal 3 row insert. |
| Notifikasi siswa saat nilai keluar | ❌ sync OK | ❌ | 🟠 | Trigger: `AssignmentSubmission::saved()` saat `score` null→ada. Sync. |
| Reminder deadline tugas H-1 ke siswa | ✅ ya | ✅ daily pagi (mis. 07:00) | 🟠 worth-it | Tidak ada trigger user — butuh cron. Untuk 80 siswa, ping manual via WA tidak scalable. Lihat §1.3. |
| Reminder ujian akan dimulai (H-1 atau 1 jam sebelum) | ✅ ya | ✅ tiap 5–15 menit | 🟡 opsional | Sama seperti di atas — skip kalau penelitian pendek. |
| Auto-publish material/assignment yang `available_from` lewat | ❌ tidak | ❌ tidak | — | Filtering sudah di-handle di query (`where available_from <= now() AND is_published = true`). Tidak butuh job. Cek [GetStudentCourse.php](../app/Actions/Student/GetStudentCourse.php) & co. |
| Auto-close exam saat `available_until` lewat | ❌ tidak | ❌ tidak | — | Validasi di [StartExamSession.php](../app/Actions/Student/StartExamSession.php) (cek `available_until` saat siswa mulai). Tidak perlu job penutup. |
| Email reminder (kalau pakai SMTP) | ✅ ya (queued) | ✅ ikut reminder di atas | 🟡 pertimbangkan | Untuk 80 siswa, email reminder lebih scalable daripada minta semua selalu cek dashboard. Lihat §2.3. |
| Backup database harian | ❌ tidak (atau ya) | ✅ daily | 🟠 wajib | Bisa Laravel scheduler atau cron OS langsung. Lihat §13. |

Ringkasnya — yang **benar-benar perlu** dibuat sebagai job + schedule:

- 🔴 `AutoSubmitExpiredExamSessions` (wajib, §1.2)
- 🟡 `SendAssignmentDeadlineReminders` (opsional, §1.3)
- 🟡 `SendExamStartReminders` (opsional, §1.3)
- 🟠 Backup (boleh job Laravel atau cron OS, §13.1)

Tidak butuh: queue worker dedicated, `ShouldQueue` di notifikasi
existing, `SendDailyDigest`, job auto-publish/auto-close.

### 🔴 1.2 Wajib: artisan command `exam:auto-submit-expired`

**Masalah:** Timer ujian hanya di frontend
([resources/js/Pages/Exam/ExamTake.tsx](../resources/js/Pages/Exam/ExamTake.tsx)).
Kalau siswa tutup tab / internet putus / browser crash sebelum auto-submit
frontend jalan, `ExamSession.submitted_at` selamanya null. Untuk
penelitian: data ujian tidak final, jawaban parsial tidak ter-grade, hasil
penelitian bias.

**Catatan tentang signature.** `SubmitExamSession::handle(Student, sessionId)`
butuh Student object (untuk authorization + ownership check). Job
background tidak punya user context. Solusi: bikin **artisan command**
yang loop session expired dan inline submit, **tanpa** lewat
`SubmitExamSession`. Pattern ini juga lebih clear di scheduler.

**Implementasi:**

1. Tambah migrasi:
   ```php
   $table->string('submission_reason')->default('manual')->after('submitted_at');
   ```
   Ini jadi audit trail penelitian: bedakan submit manual vs timeout.
2. Buat artisan command
   `app/Console/Commands/AutoSubmitExpiredExamSessionsCommand.php`:
   - Query (portable — testable di SQLite, fine di skala 6 siswa):
     ```php
     ExamSession::query()
         ->whereNull('submitted_at')
         ->with('exam.questions')
         ->get()
         ->filter(fn ($s) => $s->started_at
             ->addMinutes($s->exam->duration_minutes)
             ->isPast());
     ```
   - Untuk tiap session: set `submitted_at = now()`,
     `submission_reason = 'auto_timeout'`, panggil
     [ExamGrader::grade()](../app/Services/ExamGrader.php) inline (sama
     seperti yg dilakukan `SubmitExamSession`).

   **Catatan:** filter PHP-side dipilih karena
   [phpunit.xml](../phpunit.xml) pakai SQLite (`DATE_ADD` MySQL-only). Di
   skala 85 user production, max ~80 session aktif berbarengan (kalau dua
   kelas ujian bersamaan) — load semua lalu filter Collection masih cukup
   cepat. Kalau ke depan skala naik, baru pakai raw SQL MySQL atau
   `Carbon::createFromTimestamp` via subquery.
3. Register di [routes/console.php](../routes/console.php):
   ```php
   Schedule::command('exam:auto-submit-expired')
       ->everyTwoMinutes()
       ->withoutOverlapping()
       ->onOneServer();
   ```
4. **Eksekusi schedule:** cron OS satu baris di server:
   ```cron
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```
   `Schedule::command` ini jalan **inline** di proses cron — tidak butuh
   queue worker, tidak butuh supervisor. `QUEUE_CONNECTION` bisa
   tetap `database` (tidak relevan, karena command tidak masuk queue).

### 🟡 1.3 Opsional: Reminder deadline

**Trigger:** Tidak ada user action — perlu cron.

Hanya layak dikerjakan kalau:
- Periode penelitian > 2 minggu (kalau cuma 1 minggu, guru reminder manual
  via WA cukup).
- Atau bagian dari variabel yang diteliti (efek reminder otomatis terhadap
  on-time submission).

**Implementasi (kalau dibuat):**
- `app/Jobs/SendAssignmentDeadlineReminders.php`:
  query Assignment dengan `deadline BETWEEN now() AND now()+24h`, kirim
  notifikasi ke siswa yang belum submit.
- `app/Jobs/SendExamStartReminders.php`:
  query Exam dengan `starts_at BETWEEN now() AND now()+1h`, kirim ke siswa
  di kelas terkait yang belum punya ExamSession submitted.
- Schedule di [routes/console.php](../routes/console.php):
  ```php
  Schedule::job(new SendAssignmentDeadlineReminders)->dailyAt('07:00');
  Schedule::job(new SendExamStartReminders)->everyFifteenMinutes();
  ```

### 1.4 Apa yang tidak perlu dijadikan job

Supaya jelas bahwa tidak semua dibuat job:

- **TeacherSubmissionAlert** — bisa tetap sync (1 insert per submit, tidak
  block). Pertimbangkan `ShouldQueue` hanya kalau ada notifikasi email
  yang ikut.
- **Notifikasi siswa saat tugas/ujian dipublish** — trigger pada model
  observer/`booted()` hook. **Untuk production (~40 siswa per kelas), ini
  perlu `ShouldQueue`** supaya request guru "Publish" tidak terbeban
  oleh 40 row insert + 40 mail send.
- **Auto-publish content** — solve dengan filter query (`available_from <= now()`).
  Tidak butuh job penjadwal.
- **Auto-close exam window** — solve dengan validasi di
  `StartExamSession` (cek `available_until`). Tidak butuh job.

---

## 2. Notifikasi & Inbox Siswa

### 2.0 Status & cakupan

**Sisi guru: end-to-end sudah jalan.** [TeacherPanelProvider.php](../app/Providers/Filament/TeacherPanelProvider.php)
sudah aktifkan `->databaseNotifications()->databaseNotificationsPolling('30s')`
— bell notifikasi muncul di header panel Filament, polling tiap 30 detik
otomatis. Kombinasi:

1. Siswa submit tugas/ujian → `TeacherSubmissionAlert::sendToDatabase()`.
2. Row masuk tabel `notifications`.
3. Bell Filament guru polling → muncul di UI tanpa effort tambahan.

**Sisi siswa: tidak ada.** Layout Inertia siswa tidak punya bell sama
sekali. Yang perlu ditambah:

| Trigger | Penerima | Cara dispatch | Class baru | Prioritas |
|---------|----------|---------------|------------|-----------|
| Siswa submit tugas/ujian | Guru | sync ✅ (`TeacherSubmissionAlert`) + bell Filament ✅ | — | **done** |
| Assignment dipublish (`is_published` 0→1) | Semua siswa kelas | sync, `Assignment::booted()` saved hook | `StudentAssignmentPublished` | 🟠 |
| Exam dipublish | Semua siswa kelas | sync, `Exam::booted()` | `StudentExamPublished` | 🟠 |
| Score AssignmentSubmission diisi | Pemilik submission | extend hook existing di [AssignmentSubmission.php:56-63](../app/Models/AssignmentSubmission.php#L56-L63) | `StudentAssignmentGraded` | 🟠 |
| ExamSession di-grade ulang (essay) | Pemilik session | sync di ExamGrader | `StudentExamGraded` | 🟡 |
| Deadline tugas H-1 | Semua siswa kelas yang belum submit | scheduled (§1.3) | `StudentDeadlineReminder` | 🟡 opsional |
| Ujian akan dimulai (1 jam sebelum) | Semua siswa kelas | scheduled (§1.3) | `StudentExamStartReminder` | 🟡 opsional |

### 🟠 2.1 Siswa tidak menerima notifikasi sama sekali

**Masalah:** [app/Notifications/TeacherSubmissionAlert.php](../app/Notifications/TeacherSubmissionAlert.php)
hanya mengirim ke guru. Siswa tidak tahu kalau:
- Ada materi baru di-publish.
- Ada tugas baru / ujian baru.
- Deadline tugas mendekat (H-1 / H-3).
- Ujian akan dimulai.
- Tugas atau ujian sudah dinilai (feedback dari guru).

Untuk penelitian, ini juga membuat **engagement data** tidak lengkap — siswa
yang tidak buka aplikasi tidak akan tahu ada tugas.

**Fix yang disarankan (wajib — yang non-opsional saja):**
- Buat `app/Notifications/StudentAssignmentPublished.php`.
- Buat `app/Notifications/StudentExamPublished.php`.
- Buat `app/Notifications/StudentAssignmentGraded.php`.
- Trigger via `booted()` inline hook di model (konsisten dengan pola
  codebase — tidak ada folder `app/Observers/` di project ini):
  - `Assignment::booted()` saat `is_published` 0 → 1.
  - `Exam::booted()` saat `is_published` 0 → 1.
  - `AssignmentSubmission::booted()` — **hook `saving` sudah ada** di
    [AssignmentSubmission.php:56-63](../app/Models/AssignmentSubmission.php#L56-L63)
    yang set `graded_at`. Tinggal tambah `Notification::send(...)` di blok
    yang sama saat score null→non-null.

**Opsional (kalau §1.3 dikerjakan):**
- `StudentDeadlineReminder` + `StudentExamStartReminder` — dipicu dari job
  scheduled, bukan observer.

### 🟠 2.2 Tidak ada UI inbox di sisi siswa

**Masalah:** Walau notifikasi nantinya tersimpan di tabel `notifications`,
tidak ada halaman `/student/notifications` atau dropdown bel di header
student layout.

**Fix:** Tambahkan komponen `NotificationsDropdown.tsx` di student layout +
endpoint `GET /student/notifications` & `POST /student/notifications/{id}/read`.

**Pattern referensi:** [TeacherPanelProvider.php](../app/Providers/Filament/TeacherPanelProvider.php)
sudah pakai built-in Filament bell polling 30 detik. Untuk siswa (Inertia),
pakai `router.reload({ only: ['notifications'] })` setiap 30 detik —
pattern partial reload sudah dipakai di
[ExamTake.tsx](../resources/js/Pages/Exam/ExamTake.tsx) dan
[Assignment/AssignmentDetail.tsx](../resources/js/Pages/Assignment/AssignmentDetail.tsx),
tinggal extend.

### 🟡 2.3 Email channel — pertimbangkan untuk production 80 siswa

**KOREKSI dari rev sebelumnya:** saya tadi bilang "skip" karena asumsi 6
siswa. Untuk production 80 siswa, **email channel layak dipertimbangkan**
khususnya untuk:

- Reminder deadline H-1 (siswa yang jarang buka aplikasi).
- Notifikasi nilai keluar (engagement variable di penelitian).

**Pilihan implementasi:**
- Aktifkan `MAIL_MAILER=smtp` + provider (Mailtrap untuk dev, Resend /
  SES / Mailgun untuk production).
- Tambah `'mail'` di array `via()` HANYA untuk notifikasi penting
  (deadline + graded). Yang lain (assignment published) tetap database
  saja supaya tidak spam inbox.
- Pakai `ShouldQueue` untuk hindari block request guru.

**Skip kalau:** budget email provider tidak ada, atau periode penelitian
sangat pendek (<1 minggu) sehingga reminder manual via WhatsApp grup
sudah cukup.

---

## 3. Dashboard Siswa

Sumber: [app/Actions/Student/GetStudentDashboard.php](../app/Actions/Student/GetStudentDashboard.php)
dan [resources/js/Pages/Dashboard/](../resources/js/Pages/Dashboard/).

### 🟠 3.1 Info kelas siswa belum ditampilkan menonjol (data sudah ada)

**Koreksi:** [GetStudentDashboard.php](../app/Actions/Student/GetStudentDashboard.php)
sudah return `meta: {classroom_name, academic_year}`. Data ada — yang
missing hanya **rendering UI** + **kolom wali kelas**.

**Fix (dua langkah kecil):**

1. **Backend:** tambah `homeroom_teacher_name` di `meta` array.
   `$primaryClassroom?->teacher?->full_name`. Classroom sudah punya
   relasi `teacher`.
2. **Frontend:** [resources/js/Pages/Dashboard](../resources/js/Pages/Dashboard/)
   render card di top:
   - Nama siswa (Student model belum punya `photo`/`avatar` — fallback
     ke initial avatar generated dari nama).
   - Kelas (mis. "X IPA 1") + nama wali kelas (`meta.homeroom_teacher_name`).
   - Tahun ajaran + semester aktif.

### 🟠 3.2 Tidak ada ringkasan task pending / overdue

**Masalah:** Dashboard tidak menampilkan "Anda punya 3 tugas yang belum
dikumpulkan" atau "Ujian Matematika besok jam 08:00". Siswa harus masuk ke
masing-masing mata pelajaran satu per satu.

**Fix:** Tambah section "To-Do" di dashboard. Filter harus
**mempertimbangkan ketiga kolom waktu** ([Exam](../app/Models/Exam.php) punya
`starts_at`, `available_from`, `available_until`; tidak cukup cek satu):

- **Tugas pending:** `is_published = true` AND `available_from <= now()`
  AND `available_until IS NULL OR available_until >= now()` AND siswa
  belum submit (cek `AssignmentSubmission` ownership).
- **Tugas overdue:** sama tapi `deadline < now()` → tampilkan dengan warna
  merah (kalau §5.1 dikerjakan, juga cek `is_late` flag).
- **Ujian mendekat:** `is_published = true` AND `starts_at BETWEEN now()
  AND now()+7 hari` AND siswa belum punya ExamSession submitted.
- **Ujian available sekarang:** `available_from <= now() AND
  available_until >= now()` AND belum dikerjakan.

### 🟡 3.3 Countdown ujian belum ada di dashboard

**Masalah:** Ada timer saat ujian sedang dikerjakan, tapi tidak ada countdown
"ujian dimulai dalam 2 jam 15 menit" di dashboard utama.

**Fix:** Komponen `UpcomingExamCard.tsx` dengan countdown timer (client-side
setInterval) untuk ujian terdekat.

### 🟡 3.4 Quick stats personal

**Masalah:** Siswa tidak lihat ringkasan "saya sudah submit X tugas dari Y",
"nilai rata-rata saya".

**Fix:** Action tambahan `GetStudentStats` + section "Statistik Saya" di
dashboard.

---

## 4. Ujian (Exam) — Ketahanan & Audit Trail

Sumber: [app/Actions/Student/StartExamSession.php](../app/Actions/Student/StartExamSession.php),
[SubmitExamSession.php](../app/Actions/Student/SubmitExamSession.php),
[SaveExamAnswer.php](../app/Actions/Student/SaveExamAnswer.php),
[app/Services/ExamGrader.php](../app/Services/ExamGrader.php).

### 🔴 4.1 Tidak ada auto-submit backend saat waktu habis

→ Detail lengkap implementasi sudah ada di **§1.2**. Section ini cuma
penanda agar checklist di §14 lengkap.

### ✅ 4.2 Attempt limit ujian — sudah ada

Sudah ada secara struktural via tiga lapis:
1. DB unique constraint `(exam_id, student_id)` di migrasi exam_sessions.
2. `StartExamSession` & `SubmitExamSession` idempotent (return session lama kalau sudah ada).
3. `ExamController::take()` redirect ke result page kalau `submitted_at` terisi.

**Optional tightening (0.1 hari):** tambah guard yang sama di `start()`
untuk save 1 redirect hop. Kalau ke depan butuh multiple attempt,
drop unique constraint + tambah `attempt_number` + `max_attempts`.

### 🟡 4.3 Anti-cheat sederhana (penelitian non-formal)

**Masalah:** Tidak ada deteksi tab switch / copy-paste / right-click. Untuk
penelitian non-formal mungkin tidak wajib, tapi minimal log behavior penting.

**Fix opsional:**
- Frontend: listener `visibilitychange` → kirim ke `POST /student/exams/{session}/event` dengan tipe `tab_blur`.
- Backend: tabel `exam_session_events` (session_id, event_type, occurred_at).
- Data ini berguna untuk **analisis perilaku siswa saat ujian** dalam
  penelitian.

### 🟡 4.4 Setelah submit, jangan tampilkan jawaban benar sampai guru release

**Masalah:** Cek `ExamResult.tsx` — kalau pasca-submit langsung tampil kunci
jawaban, siswa yang submit lebih awal (mis. jam 08:30) bisa screenshot dan
share ke teman sekelas yang submit setelahnya (jam 08:45) dalam satu kelas
yang sama. Untuk penelitian, ini meng-confound data.

**Fix:** Tambah kolom `exams.results_released_at`. Tampilkan koreksi hanya
jika `results_released_at <= now()`. Guru bisa toggle "Rilis hasil" dari
panel Filament setelah semua siswa selesai.

---

## 5. Tugas (Assignment) — Late Handling & Audit

Sumber: [app/Actions/Student/SubmitStudentAssignment.php](../app/Actions/Student/SubmitStudentAssignment.php),
[app/Models/AssignmentSubmission.php](../app/Models/AssignmentSubmission.php).

### 🟠 5.1 Late submission tidak dicatat eksplisit

**Masalah:** Saat ini, jika `deadline` lewat → submit langsung di-throw
ValidationException. Untuk penelitian, lebih informatif kalau submit yang
terlambat tetap diterima tapi ditandai.

**Fix minimal (cocok untuk production 80 siswa):**
- Tambah satu kolom `assignment_submissions.is_late` (boolean). Diisi otomatis
  saat insert: `is_late = submitted_at > assignment.deadline`.
- Update [SubmitStudentAssignment.php](../app/Actions/Student/SubmitStudentAssignment.php)
  agar tidak throw kalau lewat deadline, melainkan set `is_late = true`.
- Di Filament submission resource, tampilkan badge "Terlambat" + delta
  durasi (compute on-the-fly: `submitted_at->diffForHumans(deadline)`).

Yang **TIDAK perlu** untuk skala ini:
- `late_penalty_percent` → guru tinggal kurangi nilai manual.
- `assignments.allow_late_submission` (opt-in per assignment) → default
  semua boleh late + ditandai sudah cukup.
- Kolom `late_by_minutes` → sudah bisa dihitung dari `submitted_at` minus
  `deadline`.

### 🟡 5.2 Resubmit tidak ada audit trail per versi

**Masalah:** Siswa bisa resubmit sebelum guru grade. Tapi file lama akan
ter-overwrite oleh Spatie MediaLibrary collection, sehingga guru tidak tahu
"siswa sudah ganti berapa kali". Activity log ada, tapi file historis hilang.

**Fix opsional:**
- Tabel `assignment_submission_versions` untuk arsipkan file/metadata setiap
  kali siswa save.
- Atau, gunakan MediaLibrary collection terpisah per upload event.

### 🟡 5.3 Notifikasi guru tidak membedakan first submit vs resubmit

**Masalah:** Setiap kali siswa submit, guru dapat notif yang sama. Sulit
membedakan "ada submission baru" vs "siswa edit lagi yang belum digrade".

**Fix:** [TeacherSubmissionAlert.php](../app/Notifications/TeacherSubmissionAlert.php)
tambahkan parameter `$isResubmit` (true jika ini bukan submit pertama) dan
sesuaikan judul/isi notifikasi.

---

## 6. Material — Tracking untuk Penelitian

### 🟡 6.1 Tidak ada view / download tracking

**Masalah:** Untuk penelitian engagement, perlu tahu siapa lihat materi
mana dan berapa kali.

**Fix (pakai infrastruktur yang sudah ada — tidak buat tabel baru):**
Spatie ActivityLog sudah terpasang (lihat §11.1). Cukup trigger log di
endpoint controller saat halaman material dibuka & saat file diunduh:

```php
activity('material_view')
    ->performedOn($material)
    ->causedBy($student->user)
    ->log('viewed');
```

Filter belakangan: `Activity::where('log_name', 'material_view')` →
sudah berisi `subject_id` (material), `causer_id` (user/student),
`created_at`. Tidak butuh tabel custom `material_views` /
`material_downloads`.

### 🟢 6.2 Metadata visibility

**Masalah:** Siswa tidak lihat "diupload tanggal X oleh guru Y" di halaman
material. Minor.

**Fix:** Render `created_at` + `teacher_name` di header MaterialDetail.

---

## 7. Auth & Akun Siswa

### 🟠 7.1 Tidak ada password reset untuk siswa

**Masalah:** [config/auth.php](../config/auth.php) hanya define password
broker untuk `users`, tidak untuk `students`. Kalau siswa lupa password,
satu-satunya cara adalah guru/admin reset manual lewat panel Filament.

**Fix yang dipilih:** Tombol "Reset password siswa" di Filament guru
([StudentResource](../app/Filament/Resources/StudentResource.php) atau page
Edit) yang generate password baru (mis. `Str::random(8)`) dan tampilkan
sekali via Filament notification. Catat di activity log siapa yang reset.

Untuk 80 siswa, ini masih scalable — selama periode penelitian guru
mungkin dipanggil ~5–10x untuk reset. Self-service forgot-password
self-service lebih ribet (perlu email verified per siswa) dan **belum
critical** untuk research scope.

Yang **TIDAK perlu** untuk pendekatan ini:
- Broker `students` di [config/auth.php](../config/auth.php) + route
  `/student/forgot-password` self-service.
- Tabel `password_reset_tokens` sudah ada (default Laravel 12) tapi tidak
  digunakan untuk guard student dalam pendekatan ini.

**Pertimbangkan:** kalau peneliti tidak available untuk reset manual
selama uji coba (mis. ujian Sabtu malam), tambahkan self-service
forgot-password dengan email verifikasi NISN-based. Tapi ini work extra
~1 hari dev.

### 🟠 7.2 Last login & activity tracking belum dipakai untuk siswa

**Masalah:** `User` model punya kolom `last_login_at`, tapi auth siswa lewat
[AuthenticateStudent.php](../app/Actions/Student/AuthenticateStudent.php)
tampaknya tidak meng-update field tersebut. Untuk penelitian, ini data
berharga.

**Fix:** Set `User::find(...)->update(['last_login_at' => now()])` setelah
sukses authenticate. (Untuk verifikasi cakupan activity log per model,
lihat §11.1 — tidak perlu dibahas dua kali di sini.)

### 🟠 7.5 Rate limiting di endpoint student action belum ada

**Masalah:** Cek [routes/web.php](../routes/web.php) — student group cuma
pakai `auth:student`, tidak ada middleware `throttle:...`. Endpoint kritis
tanpa throttle:

- `POST /student/exams/sessions/{session}/answer` (save-answer)
- `POST /student/exams/sessions/{session}/submit`
- `POST /student/materials/.../assignments/{assignment}/submit`

Tanpa throttle, klik berulang dari siswa (atau script) bisa bikin DB
write storm — terutama save-answer yang dipanggil tiap perubahan jawaban.

**Fix minimal:** Tambah `->middleware('throttle:60,1')` (60 request per
menit per user) di student auth group, atau definisikan custom limiter di
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleApi(); // atau custom limiter dgn key student id
})
```

Untuk save-answer khusus, bisa lebih longgar (`throttle:120,1`) karena
auto-save bisa frequent.

### 🟡 7.3 Throttle login siswa tidak terlihat di FE

**Masalah:** [LoginRequest.php](../app/Http/Requests/Auth/LoginRequest.php)
ada rate limiter, tapi UI login siswa kemungkinan tidak menampilkan pesan
"too many attempts" dengan jelas.

**Fix:** Periksa
[resources/js/Pages/Auth/Login.tsx](../resources/js/Pages/Auth/Login.tsx) (atau
file login siswa) — tampilkan error throttle.

### 🟢 7.4 2FA / API token

Tidak relevan untuk skala penelitian ~85 user di lingkungan sekolah. Skip.

---

## 8. Upload File — Hardening

Sumber: [SubmitStudentAssignment.php](../app/Actions/Student/SubmitStudentAssignment.php)
lines ±134-167, [config/filesystems.php](../config/filesystems.php).

### ✅ 8.1 Quota per siswa / global — sudah cukup

**Koreksi dari revisi awal:** saya keliru bilang field `max_file_size_mb`
nullable. Verifikasi di
[AssignmentResource.php](../app/Filament/Resources/AssignmentResource.php)
field text input sudah `.default(10)->numeric()->minValue(1)->maxValue(100)`.
Setiap assignment baru otomatis dapat batas 10 MB per file dengan clamp
1–100 MB.

Untuk production ~85 user, batas per-file 10 MB cukup. Tidak perlu:
- Quota per siswa (total ukuran).
- Quota global server (cukup monitor disk saja, lihat §13).
- Validasi jumlah file × ukuran khusus.

Skenario realistis: 80 siswa × ~6 tugas dengan max 10 MB = **~5 GB**
submission. Plus materi guru ~1–2 GB. Sediakan 30–50 GB partisi storage
(lihat verifikasi WAJIB di FAQ stack).

### 🟡 8.2 Storage symlink & permission verification

**Masalah:** Belum ada cek otomatis bahwa `php artisan storage:link` sudah
dijalankan di server target. Risiko: file submission tidak bisa diakses
guru.

**Fix:** Tambahkan langkah ini di
[docs/06-implementation-checklist.md](./06-implementation-checklist.md) atau
deployment guide baru.

### 🟢 8.3 Antivirus scan

Tidak relevan untuk penelitian internal. Skip.

---

## 9. Data Penelitian & Seeder

### ✅ 9.1 Seeder dataset penelitian (sudah dirapikan)

Status: **DONE**. Empat seeder berikut sudah diperbarui agar pas dengan
skenario penelitian (2 kelas × 3 siswa, 3 mapel inti):

| File | Output |
|------|--------|
| [database/seeders/TeacherSeeder.php](../database/seeders/TeacherSeeder.php) | 3 guru: Budi Santoso (MTK), Sari Wulandari (BIND), Andi Pratama (BING). Login: `matematika@example.com` / `bindo@example.com` / `bing@example.com`, password `password`. |
| [database/seeders/ClassroomSeeder.php](../database/seeders/ClassroomSeeder.php) | 2 kelas: **X IPA 1** (wali: Budi) dan **X IPA 2** (wali: Sari). |
| [database/seeders/StudentSeeder.php](../database/seeders/StudentSeeder.php) | 6 siswa, 3 per kelas. Password default = `birth_date` (Y-m-d). NISN 0012345001–0012345006. |
| [database/seeders/ClassroomSubjectSeeder.php](../database/seeders/ClassroomSubjectSeeder.php) | 3 mapel × 2 kelas = 6 ClassroomSubject, masing-masing dipegang guru sesuai spesialisasinya. |

Total dataset hasil `php artisan migrate:fresh --seed`:

- 3 guru, 2 kelas, 6 siswa.
- 6 ClassroomSubjects (3 mapel × 2 kelas).
- 24 materi (4 per ClassroomSubject; 3 published + 1 draft).
- 24 tugas.
- 18 ujian (3 per ClassroomSubject: pretest, posttest, tugas akhir mode submission).
- 36 soal ujian.

MaterialSeeder, AssignmentSeeder, dan ExamSeeder **tidak diubah** — keduanya
loop per ClassroomSubject, jadi otomatis menyesuaikan ke struktur baru.

### 🟡 9.2 Dummy submission seeder untuk pre-live load test

**KOREKSI dari rev sebelumnya:** saya tadi skip karena asumsi production
= 6 siswa. Untuk production 80 siswa, **layak dipertimbangkan** untuk
smoke test sebelum go-live:

- Generate dummy ExamSession + AssignmentSubmission untuk ~80 siswa
  hasil seeder yang **diperluas** ke production-scale.
- Test load di staging: apakah ExamGrader cepat untuk grade 80 session
  sekaligus? Apakah Filament listing tahan 80 row submission?
- **Jangan jalankan di production database** — hanya di staging.

Skip kalau ada budget waktu untuk **soft launch** (1 kelas dulu, lalu 2
kelas) — soft launch realistis sebagai load test.

### 🟡 9.3 Reset & re-seed command

**Masalah:** Untuk re-run penelitian dari awal, butuh command sekali jalan.

**Fix opsional:** Buat artisan command `php artisan research:reset` yang
truncate semua submission/session lalu re-seed.

---

## 10. Konfigurasi Waktu, Academic Year, dan Visibilitas

### 🔴 10.0 Timezone WAJIB diverifikasi

**Masalah:** [config/app.php:68](../config/app.php#L68) di project ini
**hardcoded** `'timezone' => 'UTC'` (bukan `env('APP_TIMEZONE', 'UTC')`).
Set `APP_TIMEZONE` di `.env` saja **tidak akan berpengaruh**. Akibatnya:

- Guru input deadline "23:59" → tersimpan sebagai 23:59 UTC = **06:59 WIB
  keesokan harinya**.
- Auto-submit job (§1.2) cek `started_at + duration < now()` → bandingkan
  beda zona.
- Activity log timestamp & notifikasi "1 jam lalu" jadi melenceng.

**Untuk penelitian dengan deadline ketat, ini bisa membatalkan data.**

**Fix (dua langkah, keduanya wajib):**

1. Edit [config/app.php:68](../config/app.php#L68):
   ```php
   'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
   ```
2. Set di `.env` dan `.env.example`:
   ```
   APP_TIMEZONE=Asia/Jakarta
   ```

Lalu test: input deadline lewat Filament guru "23:59", reload halaman, cek
apakah ditampilkan tetap "23:59" (bukan bergeser). Cek juga MySQL global
time_zone (`SELECT @@global.time_zone, @@session.time_zone`) — biarkan
Laravel handle conversion lewat Carbon cast.

### 🟡 10.1 Visibility warning di Filament guru

**Masalah:** Saat guru centang `is_published = true` tapi set
`available_from` ke masa depan, tidak ada visual warning di Filament form
bahwa "tugas ini tidak akan terlihat siswa sampai tanggal X". Ini sumber
bug sulit dilacak — guru mengira siswa bisa lihat, padahal masih
pre-available.

**Fix:** Tambah helper text dinamis di field `available_from` di
`AssignmentResource` & `ExamResource`:
```php
->helperText(fn ($state) => $state && Carbon::parse($state)->isFuture()
    ? '⚠️ Tugas/ujian belum akan terlihat siswa sampai ' . Carbon::parse($state)->isoFormat('DD MMM YYYY HH:mm')
    : null
)
```

### 🟢 10.2 Academic year & semester masih string — skip

**Masalah:** Field `academic_year` ada di `classrooms` dan
`classroom_subjects` sebagai string ("2025/2026"). Tidak ada entity
terpisah. Untuk skala penelitian satu periode ini OK, tapi:
- Tidak ada konsep "tahun ajaran aktif sekarang".
- Filter cross-resource pakai string typo-prone.

**Fix opsional (tidak wajib untuk penelitian 1 periode):**
- Tabel `academic_years` (id, name, start_date, end_date, is_active).
- Tabel `semesters` (id, academic_year_id, name, start_date, end_date,
  is_active).
- Foreign key di classrooms & classroom_subjects.

**Untuk penelitian sekarang:** abaikan, gunakan string. Tapi catat di
roadmap.

---

## 11. Audit Trail & Activity Log

### 🟡 11.1 Activity log sudah ada — verifikasi event yang dilog

Sumber: migrasi `2026_05_19_081109_create_activity_log_table.php` (Spatie
ActivityLog). **Koreksi dari revisi awal:** `ExamSession` &
`AssignmentSubmission` **sudah** pakai trait `LogsActivity`. Yang perlu
hanya verifikasi konfigurasi event-nya cukup untuk penelitian.

| Model | Trait `LogsActivity`? | Action |
|-------|----------------------|--------|
| `ExamSession` | ✅ optimal | Sudah `logOnly(['started_at','submitted_at','total_score']) + logOnlyDirty() + dontLogEmptyChanges()` di [ExamSession.php](../app/Models/ExamSession.php). Tidak perlu diubah. |
| `AssignmentSubmission` | ✅ ada | Verifikasi `getActivitylogOptions()` mencakup minimal `submitted_at`, `score`, `graded_at`, `feedback`. |
| `ExamAnswer` (save jawaban tiap soal) | ❌ tidak ada | **Tambahkan trait** + log `created`/`updated`. Penting untuk reproduksi urutan jawaban di analisis penelitian. |
| `Material` | ❌ tidak ada | Tidak perlu LogsActivity di model. Cukup trigger manual `activity('material_view')->performedOn($material)->causedBy($student->user)->log('viewed')` di endpoint show & download (lihat §6.1). |
| `User` (siswa) | tidak diperlukan via log | Cukup update `last_login_at` di `AuthenticateStudent` (§7.2). |

Action di §11.1 (lebih sempit dari rev awal):
1. Verifikasi `AssignmentSubmission::getActivitylogOptions()` (ExamSession sudah optimal).
2. Tambahkan trait `LogsActivity` di `ExamAnswer`.
3. Trigger manual log event di endpoint Material show & download.

### 🟠 11.2 Export data penelitian (bukan cuma activity log)

**Masalah:** Setelah penelitian selesai, peneliti butuh **data inti** dalam
format yang bisa dianalisis (CSV/Excel):

- ExamSession + ExamAnswer (jawaban siswa per soal, durasi, nilai).
- AssignmentSubmission (siswa, nilai, on-time/late, feedback).
- Activity log (timeline: kapan login, kapan buka materi, kapan submit).

Saat ini Filament punya export bawaan untuk resource, tapi belum
dikonfigurasi untuk dataset penelitian.

**Fix:**
- Tambah action "Export ke Excel" di:
  - `ExamResource` (per exam → semua ExamSession + jawaban tiap soal).
  - `AssignmentResource` (per assignment → semua submission + status).
- Format: 1 baris per siswa, kolom = soal/atribut.
- Opsional: Filament `ActivityLogResource` dengan filter + export untuk
  data timeline behavior.
- Opsional: artisan command `php artisan research:export-all` yang
  generate satu zip berisi semua CSV — supaya peneliti bisa ekspor sekali
  jalan di akhir periode.

### 🟠 11.3 Storage cleanup saat siswa/submission dihapus

**Masalah:** [AssignmentSubmission](../app/Models/AssignmentSubmission.php)
implement Spatie `HasMedia`. Kalau guru hapus siswa via Filament (atau
penelitian selesai dan data ingin di-purge), file submission MediaLibrary
mungkin jadi orphan (tergantung apakah Student/Submission soft-delete dan
cascade behavior MediaLibrary).

**Action (verifikasi, bukan langsung fix):**
1. Cek apakah `AssignmentSubmission` & `Student` pakai `SoftDeletes` —
   kalau iya, file tidak akan ikut terhapus secara otomatis.
2. Test manual: hapus 1 submission dari Filament, cek apakah row di tabel
   `media` ikut hilang + file di `storage/app` terhapus.
3. Kalau orphan: override `shouldDeletePreservingMedia()` atau hook
   `deleting` event untuk auto-cleanup.

Untuk penelitian: dokumentasikan **prosedur purge data** setelah analisis
selesai (kalau diperlukan oleh ethics committee/IRB).

---

## 12. Localization & UI Polish

### 🟢 12.1 Hardcoded bahasa Indonesia

**Masalah:** Tidak ada folder `lang/id/` yang berisi extracted strings.
Semua bahasa Indonesia di FE & PHP hardcoded.

**Fix:** Tidak wajib untuk skala penelitian. Skip.

### 🟡 12.2 Empty states & error messages

**Masalah:** Belum diaudit apakah halaman student menampilkan empty state
yang informatif (mis. "Belum ada tugas di kelas ini") dengan ilustrasi/icon.

**Fix:** Lakukan pass UX di tiap halaman student:
- Dashboard tanpa kelas → arahkan ke admin.
- Course tanpa material → state "Guru belum upload materi".
- Tugas/ujian tanpa item → state ramah.

---

## 13. Backup & Operasional

### 🟠 13.1 Tidak ada backup strategy

**Masalah:** Untuk penelitian, **data hilang = penelitian gagal**. Tidak
ada artisan command / cron yang membackup DB & storage.

**Untuk production ~85 user, paling sederhana:**

**Catatan:** kalau §1.2 sudah jalan, server sudah punya cron
`* * * * * php artisan schedule:run` — backup ini cron OS terpisah, **bukan**
schedule entry. Keduanya hidup di crontab yang sama tapi tidak saling
tergantung.

**Cron OS + `~/.my.cnf`** — jangan taruh password MySQL di crontab
(visible di `ps aux` & cron log). Buat dulu file credentials di home user:

```ini
# ~/.my.cnf  — chmod 600
[client]
user=db_user
password=db_password
```

Lalu crontab cukup:
```cron
0 1 * * * cd /path/to/lms-app && mysqldump dbname > storage/backups/db-$(date +\%F).sql && tar -czf storage/backups/files-$(date +\%F).tar.gz storage/app/public
```

Lalu rsync `storage/backups/` ke disk eksternal / cloud manual seminggu
sekali.

**Jangan**: pakai `-pPASS` di command line, atau install
`spatie/laravel-backup` (overkill — tambah satu dependency, satu config,
satu jadwal scheduler hanya untuk replicate apa yang cron OS 1 baris bisa
lakukan).

Tidak perlu S3 / multi-disk / retention policy kompleks untuk penelitian
singkat.

### 🟡 13.2 Deployment & monitoring guide

**Masalah:** [README.md](../README.md) masih **default Laravel boilerplate**
— tidak ada section deployment, setup, atau research checklist. Contributor
baru tidak punya entrypoint.

**Fix:** Edit README.md untuk:
- Section "Setup lokal" — clone, install, migrate, seed, storage:link.
- Section "Akun demo" — list 3 guru + 6 siswa hasil seeder.
- Section "Deploy penelitian" dengan checklist:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Jakarta`
    (lihat §10.0).
  - HTTPS aktif (Let's Encrypt / nginx reverse proxy).
  - `php artisan migrate --force`
  - `php artisan db:seed`
  - `php artisan storage:link`
  - Cron `* * * * * php artisan schedule:run` (untuk §1.2).
  - Cron daily backup dengan `~/.my.cnf` (lihat §13.1).
  - Verifikasi restore di staging.

Tidak perlu file terpisah `docs/10-deployment-research.md` — README sudah
cukup untuk research-scale single deployment.

Tidak perlu supervisor / queue worker dedicated kalau pakai
`QUEUE_CONNECTION=sync` seperti rekomendasi §1.2.

---

## 14. Punch List Prioritas (Ringkas)

Sebelum **go-live penelitian**, kerjakan minimal yang ditandai 🔴 dan 🟠:

### 🔴 CRITICAL — wajib sebelum go-live
- [ ] §1.2 Artisan command `exam:auto-submit-expired` + migrasi `submission_reason` + entry schedule di `routes/console.php` + cron OS `* * * * * php artisan schedule:run`.
- [ ] §10.0 Set `APP_TIMEZONE=Asia/Jakarta` di `.env` dan `.env.example`. **Verifikasi dengan test: input deadline lalu cek storage di DB.**
- [x] §9.1 Seeder dataset penelitian (6 siswa × 2 kelas, 3 mapel). **Selesai.**

### 🟠 HIGH — sangat disarankan sebelum go-live
- [ ] §2.1 Notifikasi siswa untuk: assignment/exam dipublish, nilai keluar. **`AssignmentSubmission::booted()` saving hook sudah ada — tinggal extend** untuk dispatch `StudentAssignmentGraded`.
- [ ] §2.2 UI inbox / dropdown notifikasi di student layout (polling 30 detik).
- [ ] §3.1 Backend: tambah `homeroom_teacher_name` di `GetStudentDashboard::meta`. Frontend: render kartu identitas siswa + kelas + wali kelas.
- [ ] §3.2 Section "To-Do" — filter 3 kolom waktu Exam, bukan cuma `starts_at`.
- [ ] §5.1 Late submission flag (1 kolom `is_late` di `assignment_submissions`).
- [ ] §7.1 Password reset siswa — tombol di Filament guru panel.
- [ ] §7.2 Update `last_login_at` di `AuthenticateStudent`.
- [ ] §7.5 Throttle di student endpoints (save-answer, submit) — minimal `throttle:60,1`.
- [ ] §11.1 Verifikasi `AssignmentSubmission::getActivitylogOptions()` (`ExamSession` sudah optimal); tambah `LogsActivity` di `ExamAnswer`.
- [ ] §11.2 Export data penelitian — action "Export ke Excel" di ExamResource & AssignmentResource (`maatwebsite/excel` sudah terpasang).
- [ ] §11.3 Verifikasi storage cleanup saat submission/student dihapus.
- [ ] §13.1 Backup harian — cron OS `mysqldump` + tar storage, **pakai `~/.my.cnf`**, jangan password di crontab. Test restore di staging.
- [ ] §13.2 Edit README.md — research-deployment checklist (saat ini masih default Laravel boilerplate).

### 🟠 NAIK PRIORITAS untuk production 85 user (sebelumnya 🟡 untuk 6 siswa)
- [ ] §1.3 Reminder deadline tugas & ujian (job + schedule + email channel).
- [ ] §2.3 Email channel via SMTP untuk notifikasi penting.
- [ ] §1.4 `ShouldQueue` untuk `Student*Published` (notif batch ke 40 siswa per kelas).
- [ ] Setup supervisor + `php artisan queue:work` (lihat FAQ stack).
- [ ] §9.2 Dummy submission seeder untuk smoke test di staging.

### 🟡 MEDIUM — kerjakan kalau ada waktu
- [ ] §3.3 Countdown ujian di dashboard.

(catatan: §1.3 dan §2.3 yang sebelumnya di MEDIUM, sekarang dipindah ke
section "NAIK PRIORITAS" di atas karena production 85 user.)
- [ ] §3.4 Quick stats personal siswa.
- [ ] §4.3 Log `tab_blur` saat ujian (tabel `exam_session_events`).
- [ ] §4.4 Toggle "Rilis hasil ujian" (`exams.results_released_at`).
- [ ] §5.2 Audit trail resubmit per versi.
- [ ] §5.3 Differentiate first submit vs resubmit di notifikasi guru.
- [ ] §6.1 Tracking view & download material (via activity log — tanpa tabel baru).
- [ ] §7.3 UI throttle login siswa.
- [ ] §8.2 Cek storage symlink di deployment guide.
- [ ] §9.3 Reset & re-seed command penelitian.
- [ ] §10.1 Helper text "akan terlihat siswa pada tanggal X" di form Filament.
- [ ] §12.2 Empty state UX pass.
- [ ] §13.2 Deployment guide penelitian.

### ✅ SUDAH ADA — verifikasi saja / koreksi dari rev awal
- §4.2 Attempt limit ujian — unique DB constraint + idempotent action.
  `ExamController::take()` sudah punya guard redirect (kalau session
  submitted, redirect ke result). `start()` belum punya guard yang sama
  tapi alur tetap aman via idempotency.
- §8.1 `max_file_size_mb` default & clamp — sudah `.default(10)
  ->minValue(1)->maxValue(100)` di AssignmentResource form.
- §11.1 `ExamSession::getActivitylogOptions()` — sudah `logOnly([...]) +
  logOnlyDirty() + dontLogEmptyChanges()`. Tidak perlu diubah.
- `maatwebsite/excel` v3.1 — sudah terpasang di composer.json, siap pakai
  untuk export §11.2.

### 🟢 LOW / skip — tidak relevan untuk skala 85 user di 1 sekolah

**Konfigurasi & infra:**
- Spatie laravel-backup full setup (cron OS sudah cukup).
- Redis / Horizon / Pulse / Reverb / Octane / Telescope / S3 / CDN (lihat
  Catatan Revisi 2).

**Job / scheduler:**
- `SendDailyDigest` / notifikasi ringkasan harian (skip kecuali bagian
  variabel penelitian).
- Job auto-publish material/assignment (filter query sudah cover).
- Job auto-close exam (validasi di action sudah cover).

**Feature:**
- §6.2 Metadata material visibility.
- §7.4 2FA / API token.
- §8.3 Antivirus upload.
- §10.1 `academic_years` / `semesters` entity.
- §11.2 Export activity log ke CSV (sudah di-merge ke §11.2 main).
- §12.1 Localization (i18n).
- Factory bulk siswa (cukup pakai seeder existing untuk dev; production
  pakai data sekolah real).

---

## 15. Estimasi Effort (range, bukan angka pasti)

Saya sudah beberapa kali revisi estimasi (±8 → ±4 → ±6.5 → sekarang).
Yang baru: skala production = 85 user (bukan 6) mengangkat beberapa item
dari MEDIUM ke HIGH, sehingga total naik.

**Total CRITICAL + HIGH: 8–12 hari kerja** untuk 1 developer yang
familiar dengan codebase Laravel + Filament + Inertia. Range cukup lebar
karena:

- Familiarity developer dengan stack sangat memengaruhi.
- UI work (§2.2 dropdown notifikasi + §3 dashboard) butuh iterasi
  desain.
- Setup supervisor + queue worker + monitoring di staging perlu test.
- Test manual + load test per section.

Distribusi kasar per cluster:

| Cluster | Sections | Bobot |
|---------|----------|-------|
| Critical infra (timezone, auto-submit, supervisor/queue) | §1.2, §10.0, FAQ stack | 1–1.5 hari |
| Notifikasi siswa + UI inbox + email channel + ShouldQueue | §2.1, §2.2, §2.3, §1.4 | **Terbesar** — 3–4 hari |
| Reminder deadline (job + schedule + email) | §1.3 | 0.5–1 hari |
| Dashboard siswa | §3.1, §3.2 | 1–1.5 hari |
| Audit trail & data export | §11.1, §11.2, §11.3 | 1–1.5 hari |
| Auth & security tweaks | §7.1, §7.2, §7.5 | 0.5 hari |
| Misc fix kecil | §4.2, §5.1, §13.1, §13.2 | 1 hari |
| Dummy seeder + soft launch test | §9.2 | 0.5 hari |

Kalau developer baru di codebase, kalikan 1.3–1.5x.

---

## Lampiran A — File / Folder yang perlu dibuat baru

```
app/Console/Commands/
└── AutoSubmitExpiredExamSessionsCommand.php   ← §1.2 (wajib)

app/Notifications/
├── StudentAssignmentPublished.php             ← §2.1 (sync, bukan ShouldQueue)
├── StudentExamPublished.php                   ← §2.1
└── StudentAssignmentGraded.php                ← §2.1

database/migrations/
├── xxxx_add_submission_reason_to_exam_sessions.php   ← §1.2
├── xxxx_add_is_late_to_assignment_submissions.php    ← §5.1
└── xxxx_add_results_released_at_to_exams.php         ← §4.4 (opsional, MEDIUM)
```

**Yang tidak perlu dibuat** (koreksi dari rev sebelumnya):
- `app/Jobs/AutoSubmitExpiredExamSessions.php` — dipilih artisan command,
  bukan job class (lebih clean: tidak butuh Student context, lebih jelas
  di `Schedule::command`).
- `app/Observers/*` — codebase pakai `booted()` inline. Ikut konvensi.
- `app/Console/Kernel.php` — L12 pakai `routes/console.php` (sudah ada).
- Migrasi `jobs` / `failed_jobs` / `sessions` / `job_batches` — sudah ada
  default L12.
- `docs/10-deployment-research.md` — edit README.md langsung saja (§13.2).
- `SendDeadlineReminders`, `SendDailyDigest`, `StudentExamGraded`,
  `StudentDeadlineReminder` — `StudentDeadlineReminder` sekarang **worth-it**
  untuk production 85 user (lihat §1.3 di NAIK PRIORITAS). `SendDailyDigest`
  & `StudentExamGraded` masih opsional.
- `StudentFactory.php` & seeder bulk — pakai data sekolah real untuk
  production. Seeder dev 6 siswa cukup untuk smoke test lokal.

## Lampiran B — File yang perlu di-update

| File | Section | Perubahan |
|------|---------|-----------|
| [config/app.php](../config/app.php) | §10.0 | Line 68: `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')` (saat ini hardcoded `'UTC'`). |
| [.env](../.env), [.env.example](../.env.example) | §10.0 | Tambah `APP_TIMEZONE=Asia/Jakarta`. |
| [routes/console.php](../routes/console.php) | §1.2 | Daftarkan `Schedule::command('exam:auto-submit-expired')->everyTwoMinutes()`. |
| [routes/web.php](../routes/web.php) | §7.5 | Tambah `->middleware('throttle:60,1')` di student auth group. |
| [app/Models/Assignment.php](../app/Models/Assignment.php) | §2.1 | `booted()` saved hook: dispatch `StudentAssignmentPublished` saat `is_published` 0→1. |
| [app/Models/Exam.php](../app/Models/Exam.php) | §2.1 | Sama: dispatch `StudentExamPublished`. |
| [app/Models/AssignmentSubmission.php](../app/Models/AssignmentSubmission.php) | §2.1 | `booted()` saving hook **sudah ada** untuk set `graded_at` — extend untuk dispatch `StudentAssignmentGraded`. |
| [app/Models/ExamAnswer.php](../app/Models/ExamAnswer.php) | §11.1 | Tambah trait `LogsActivity` + `getActivitylogOptions()`. |
| [app/Actions/Student/AuthenticateStudent.php](../app/Actions/Student/AuthenticateStudent.php) | §7.2 | Update `User::last_login_at` setelah authenticate. |
| [app/Actions/Student/SubmitStudentAssignment.php](../app/Actions/Student/SubmitStudentAssignment.php) | §5.1 | Hapus throw deadline; set `is_late = submitted_at > deadline`. |
| [app/Actions/Student/GetStudentDashboard.php](../app/Actions/Student/GetStudentDashboard.php) | §3.1, §3.2 | Tambah `homeroom_teacher_name` di `meta` + to-do array (tugas/ujian pending). |
| [app/Http/Controllers/Student/ExamController.php](../app/Http/Controllers/Student/ExamController.php) | §4.2 | (Opsional) tambah guard di `start()`: redirect ke result kalau session sudah submitted. |
| [app/Filament/Resources/StudentResource.php](../app/Filament/Resources/StudentResource.php) | §7.1 | Tambah header action "Reset Password" di Edit page. |
| [app/Filament/Resources/AssignmentResource.php](../app/Filament/Resources/AssignmentResource.php) | §11.2 | Tambah table action "Export to Excel" untuk submission. |
| [app/Filament/Resources/ExamResource.php](../app/Filament/Resources/ExamResource.php) | §10.1, §11.2 | Helper text warning di `available_from` + table action "Export to Excel". |
| [resources/js/Layouts/StudentLayout.tsx](../resources/js/Layouts/StudentLayout.tsx) | §2.2 | Tambah dropdown notifikasi (polling 30 detik). |
| [resources/js/Pages/Dashboard/*.tsx](../resources/js/Pages/Dashboard/) | §3.1, §3.2 | Render kartu identitas siswa + section To-Do. |
| [README.md](../README.md) | §13.2 | Section "Setup lokal", "Akun demo", "Deploy penelitian". |

**Yang TIDAK perlu di-update** (sudah ada / sengaja tidak diubah):
- [.env.example](../.env.example) — `MAIL_*`, `BROADCAST_*`, `REDIS_*` biarkan default (§2.3, Catatan Revisi 2).
- [app/Notifications/TeacherSubmissionAlert.php](../app/Notifications/TeacherSubmissionAlert.php) — biarkan sync, jangan `ShouldQueue` (§1.4).
- [database/seeders/](../database/seeders/) — sudah selesai di §9.1.
- [config/auth.php](../config/auth.php) — broker `students` tidak ditambah; pakai tombol reset di Filament guru (§7.1).
- [app/Actions/Student/StartExamSession.php](../app/Actions/Student/StartExamSession.php) — attempt limit sudah ada via DB unique constraint (§4.2).
- [app/Actions/Student/SubmitExamSession.php](../app/Actions/Student/SubmitExamSession.php) — biarkan, artisan command (§1.2) tidak panggil action ini.
- [app/Models/ExamSession.php](../app/Models/ExamSession.php) — activity log options sudah optimal (§11.1).

---

*Dokumen ini sudah lima putaran iterasi. Estimasi & detail bisa terus
bergeser seiring pemahaman codebase makin dalam. Pakai dokumen ini sebagai
peta jalan, bukan spek implementasi final. Untuk tiap section yang akan
dikerjakan, sebaiknya buat ticket/issue terpisah dengan scope kongkret.*
