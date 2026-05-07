# Migrations Order

Urutan pembuatan migration harus mengikuti dependency FK.

```
# Existing (jangan diubah)
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_05_07_082556_create_permission_tables.php
2026_05_07_083617_create_media_table.php

# Fase 1 — Master Data (buat dulu)
xxxx_xx_xx_update_users_table.php           ← tambah kolom ke users (uuid PK, is_active, dll)
xxxx_xx_xx_create_schools_table.php
xxxx_xx_xx_create_teachers_table.php        ← depends: users
xxxx_xx_xx_create_students_table.php        ← depends: users, schools
xxxx_xx_xx_create_subjects_table.php

# Fase 2 — Kelas & Assignments
xxxx_xx_xx_create_classrooms_table.php              ← depends: schools, teachers
xxxx_xx_xx_create_classroom_students_table.php      ← depends: classrooms, students
xxxx_xx_xx_create_classroom_subjects_table.php      ← depends: classrooms, subjects, teachers

# Fase 3 — Konten
xxxx_xx_xx_create_materials_table.php               ← depends: classroom_subjects
xxxx_xx_xx_create_assignments_table.php             ← depends: classroom_subjects
xxxx_xx_xx_create_assignment_submissions_table.php  ← depends: assignments, students

# Fase 4 — Ujian
xxxx_xx_xx_create_exams_table.php                   ← depends: classroom_subjects
xxxx_xx_xx_create_exam_questions_table.php          ← depends: exams
xxxx_xx_xx_create_exam_sessions_table.php           ← depends: exams, students
xxxx_xx_xx_create_exam_answers_table.php            ← depends: exam_sessions, exam_questions
```

---

## Catatan update_users_table

Migration ini harus:
1. Ubah PK `id` dari `bigIncrements` ke `uuid` — **hati-hati jika sudah ada data**
2. Tambah kolom: `is_active`, `last_login_at`, `last_login_ip`, `password_changed_at`
3. Tambah `softDeletes()`

Karena users table sudah ada, gunakan `Schema::table` bukan `Schema::create`.

Jika fresh install, lebih aman: drop dan recreate (edit migration original).

---

## Artisan Commands (urutan jalankan)

```bash
php artisan migrate                    # jalankan semua pending migration
php artisan db:seed --class=RoleSeeder # seed roles: super_admin, teacher, student
php artisan db:seed --class=SchoolSeeder
php artisan db:seed --class=UserSeeder # buat user dummy teacher
php artisan shield:generate --all      # generate permission dari Filament resources
php artisan shield:super-admin --user=1 # set super admin
```
