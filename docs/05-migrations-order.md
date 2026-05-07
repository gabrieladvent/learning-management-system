# Urutan Migrations

Jalankan sesuai urutan berikut agar tidak ada FK error.

```
[sudah ada]
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_tablegit 
2026_05_07_082556_create_permission_tables     ← spatie/laravel-permission
2026_05_07_083617_create_media_table           ← spatie/laravel-media-library

[yang perlu dibuat]
xxxx_create_classrooms_table
xxxx_create_classroom_student_table
xxxx_create_topics_table
xxxx_create_materials_table
xxxx_create_assignments_table
xxxx_create_assignment_submissions_table
xxxx_create_exams_table
xxxx_create_questions_table
xxxx_create_question_options_table
xxxx_create_exam_sessions_table
xxxx_create_exam_answers_table
xxxx_create_announcements_table
```

---

## Perintah Artisan

```bash
# Buat semua migration sekaligus
php artisan make:migration create_classrooms_table
php artisan make:migration create_classroom_student_table
php artisan make:migration create_topics_table
php artisan make:migration create_materials_table
php artisan make:migration create_assignments_table
php artisan make:migration create_assignment_submissions_table
php artisan make:migration create_exams_table
php artisan make:migration create_questions_table
php artisan make:migration create_question_options_table
php artisan make:migration create_exam_sessions_table
php artisan make:migration create_exam_answers_table
php artisan make:migration create_announcements_table

# Buat Model + Factory + Seeder sekaligus
php artisan make:model Classroom -mfs
php artisan make:model Topic -mfs
php artisan make:model Material -mfs
php artisan make:model Assignment -mfs
php artisan make:model AssignmentSubmission -mfs
php artisan make:model Exam -mfs
php artisan make:model Question -mfs
php artisan make:model QuestionOption -mfs
php artisan make:model ExamSession -mfs
php artisan make:model ExamAnswer -mfs
php artisan make:model Announcement -mfs

# Buat Filament Resources
php artisan make:filament-resource Classroom --generate
php artisan make:filament-resource Material --generate
php artisan make:filament-resource Assignment --generate
php artisan make:filament-resource Exam --generate
php artisan make:filament-resource Announcement --generate

# Buat Relation Managers
php artisan make:filament-relation-manager ClassroomResource students name
php artisan make:filament-relation-manager ClassroomResource topics title
php artisan make:filament-relation-manager AssignmentResource submissions student.name
php artisan make:filament-relation-manager ExamResource questions question_text
php artisan make:filament-relation-manager ExamResource sessions student.name

# Buat Dashboard Widgets
php artisan make:filament-widget StatsOverview --stats-overview
php artisan make:filament-widget RecentSubmissions --table
php artisan make:filament-widget UpcomingExams --table

# Setup Shield (roles & permissions)
php artisan shield:generate --all
php artisan shield:super-admin
```

---

## Roles & Permissions (Filament Shield)

```
super_admin  → akses penuh semua resource
teacher      → akses ClassroomResource, MaterialResource, AssignmentResource,
               ExamResource, AnnouncementResource (hanya data miliknya)
student      → tidak ada akses Filament
```

Tambahkan role seed di `DatabaseSeeder`:
```php
Role::create(['name' => 'teacher', 'guard_name' => 'web']);
Role::create(['name' => 'student', 'guard_name' => 'web']);
```
