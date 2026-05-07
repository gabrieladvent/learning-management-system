# Implementation Checklist

Centang saat selesai dikerjakan.

## Phase 1 — Foundation
- [ ] Buat semua migrations (lihat `05-migrations-order.md`)
- [ ] Jalankan `php artisan migrate`
- [ ] Buat semua Models dengan relasi lengkap
- [ ] Tambahkan casts & fillable di setiap model
- [ ] Setup Filament Panel (sudah ada, cek `AdminPanelProvider`)

## Phase 2 — Filament Resources (Teacher)
- [ ] ClassroomResource + StudentsRelationManager + TopicsRelationManager
- [ ] MaterialResource (form dengan conditional fields per type)
- [ ] AssignmentResource + SubmissionsRelationManager + Grade action
- [ ] ExamResource + QuestionsRelationManager + SessionsRelationManager
- [ ] AnnouncementResource

## Phase 3 — Dashboard & UX
- [ ] StatsOverviewWidget
- [ ] RecentSubmissionsWidget
- [ ] UpcomingExamsWidget
- [ ] Navigation groups & icons
- [ ] Scope data per guru (getEloquentQuery)

## Phase 4 — Roles & Security
- [ ] Setup Spatie roles: `teacher`, `student`
- [ ] Jalankan `shield:generate` untuk auto-generate permissions
- [ ] Buat Policies untuk setiap model
- [ ] Pastikan guru tidak bisa akses data guru lain

## Phase 5 — Seeder & Testing
- [ ] UserFactory dengan role teacher/student
- [ ] ClassroomSeeder (2–3 kelas dengan siswa)
- [ ] MaterialSeeder, AssignmentSeeder, ExamSeeder
- [ ] Manual test semua flow di browser

## Backlog (Fase Berikutnya — Sisi Siswa)
- [ ] Auth siswa (register/login via Breeze)
- [ ] Dashboard siswa (Inertia)
- [ ] Halaman kelas siswa (materi, tugas, ujian)
- [ ] Form pengumpulan tugas
- [ ] Flow mengerjakan ujian (timer, auto-save)
- [ ] Rekap nilai siswa
- [ ] Sistem notifikasi (Laravel Notification)
