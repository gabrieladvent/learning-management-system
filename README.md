# LMS Penelitian

Laravel 12 + Inertia (React) + Filament 3 — LMS untuk penelitian, dengan auth ganda (admin/guru via Filament, siswa via panel Inertia terpisah).

## Stack

- PHP 8.4 · Laravel 12 · MySQL 8.0+
- Filament 3 (admin/guru) · Inertia v2 + React 18 + TypeScript (siswa)
- Spatie: Activity Log, Media Library, Permission
- Maatwebsite/Excel · Laravel Queue (database driver)

## Development Setup

```bash
# 1. Clone & install
composer install
npm install
cp .env.example .env
php artisan key:generate

# 2. DB + seeder dasar (6 siswa, 3 guru, 2 kelas, 3 mapel)
php artisan migrate:fresh --seed

# 3. Build & dev
php artisan storage:link
npm run dev            # vite dev
php artisan serve      # atau pakai Herd
```

Login Filament default (cek `RoleSeeder` & `TeacherSeeder` untuk credential). Siswa login via `/student/login` pakai NISN + tanggal lahir (format `Y-m-d`).

## Architecture Highlights

- **Pages dokumen** ada di [docs/](docs/) — baca [10-development-phases.md](docs/10-development-phases.md) untuk fase pengembangan.
- **Auth siswa terpisah**: guard `student` di [config/auth.php](config/auth.php).
- **Activity log otomatis** lewat trait `LogsActivity` di `AssignmentSubmission`, `ExamSession`, `ExamAnswer`. Causer resolve via custom resolver di [AppServiceProvider](app/Providers/AppServiceProvider.php).
- **Notifikasi siswa**: 4 class di [app/Notifications/](app/Notifications/) — published, graded, deadline reminder. Batch (Published) pakai `ShouldQueue`.
- **Strict deadline mode**: tugas lewat deadline tidak bisa di-submit (lihat [SubmitStudentAssignment](app/Actions/Student/SubmitStudentAssignment.php)).

## Production Deployment Checklist

Sebelum go-live, ikuti urutan di [docs/10-development-phases.md](docs/10-development-phases.md):

### 1. Pre-flight (Phase 0)
- [ ] PHP 8.4 + ext: `pdo_mysql`, `mbstring`, `bcmath`, `gd`/`imagick`, `zip`, `xml`, `fileinfo`
- [ ] MySQL ≥ 8.0 / MariaDB ≥ 10.5
- [ ] Disk ≥ 30–50 GB di partisi storage
- [ ] HTTPS aktif (Let's Encrypt / nginx)
- [ ] `.env` production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Jakarta`, `QUEUE_CONNECTION=database`
- [ ] `php artisan storage:link`

### 2. Cron (Phase 1.1 + 4.4)
Tambah 1 baris di crontab server:
```cron
* * * * * cd /path/to/lms-app && php artisan schedule:run >> /dev/null 2>&1
```
Schedule yang aktif:
- `exam:auto-submit-expired` — tiap 2 menit, auto-submit ExamSession yang lewat duration
- `SendAssignmentDeadlineReminders` — harian jam 06:30, reminder deadline 24h
- `SendExamStartReminders` — tiap 15 menit, reminder ujian mulai 1h

### 3. Queue Worker (Phase 4.1)
Supervisor config `/etc/supervisor/conf.d/lms-worker.conf`:
```ini
[program:lms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/lms-app/artisan queue:work --queue=default --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/lms-app/storage/logs/worker.log
stopwaitsecs=3600
```
Lalu `supervisorctl reread && supervisorctl update`.

Tanpa queue worker: notifikasi batch (publish ke 40 siswa) + deadline reminder akan nge-pending di tabel `jobs`. **Wajib jalan untuk production.**

### 4. Backup harian (Phase 1.3)
- Buat `~/.my.cnf` (chmod 600) dengan credentials MySQL
- Crontab harian jam 01:00:
  ```cron
  0 1 * * * cd /path/to/lms-app && mysqldump dbname > storage/backups/db-$(date +\%F).sql && tar -czf storage/backups/files-$(date +\%F).tar.gz storage/app/public
  ```
- **Test restore di staging** sebelum production
- Rsync mingguan ke disk eksternal/cloud

### 5. Verifikasi
- [ ] Login Filament guru → publish 1 assignment → cek tabel `jobs` cepat ter-drain
- [ ] Login siswa → bell notif muncul dalam ≤30s
- [ ] Set deadline 1 menit ke depan → tunggu cron `schedule:run` → cek `exam_sessions.submitted_at` ter-isi
- [ ] `php artisan research:export-all` jalan → ZIP keluar di `storage/app/private/exports/`

## Useful Commands

```bash
# Auto-submit ExamSession expired (di-run otomatis tiap 2 menit oleh scheduler)
php artisan exam:auto-submit-expired

# Bundle semua data penelitian ke ZIP
php artisan research:export-all

# Reset siklus penelitian — truncate submissions/sessions/activity log
# JANGAN di production. Pakai untuk dev/staging.
php artisan research:reset

# Seed 80 siswa + dummy data untuk load test (staging only)
php artisan db:seed --class=LoadTestSeeder

# Run queue worker (dev — alternatif: set QUEUE_CONNECTION=sync di .env)
php artisan queue:work

# Cek schedule yang terdaftar
php artisan schedule:list
```

## Folder Structure

- [app/Actions/Student/](app/Actions/Student/) — single-responsibility actions untuk siswa
- [app/Filament/Resources/](app/Filament/Resources/) — Filament resources (admin/guru CRUD)
- [app/Notifications/](app/Notifications/) — semua notifikasi (Student* + TeacherSubmissionAlert)
- [app/Jobs/](app/Jobs/) — queued jobs (reminder)
- [app/Exports/](app/Exports/) — Maatwebsite Excel exports
- [resources/js/Pages/](resources/js/Pages/) — Inertia React pages (siswa)
- [resources/js/Components/](resources/js/Components/) — shared components (Layout, Dashboard, Exam)
- [docs/](docs/) — dokumentasi internal (audit, fase pengembangan, dll)

## License

Proprietary. Skripsi/penelitian — bukan untuk distribusi publik.
