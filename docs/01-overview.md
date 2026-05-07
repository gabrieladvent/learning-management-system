# LMS App — Project Overview

## Stack

| Layer | Package |
|---|---|
| Framework | Laravel 12 |
| Admin Panel | Filament 3.3 |
| Role & Permission | Spatie Laravel Permission + Filament Shield |
| File Upload | Spatie Laravel Media Library |
| Frontend (Siswa) | Inertia.js + Vue/React + Vite |
| Auth Scaffolding | Laravel Breeze |

## Aktor

| Aktor | Role Slug | Panel |
|---|---|---|
| Admin / Superadmin | `super_admin` | Filament (`/admin`) |
| Guru | `teacher` | Filament (`/admin`) |
| Siswa | `student` | Inertia (web) |

> Fase pertama: fokus pada **sisi Guru via Filament**. Sisi Siswa dibangun belakangan.

## Fitur Guru (Filament)

| # | Fitur | Keterangan |
|---|---|---|
| 1 | Dashboard Ringkasan | Statistik kelas, siswa, tugas aktif, ujian mendatang |
| 2 | Manajemen Kelas | Buat kelas, kelola siswa, kode join |
| 3 | Materi Pembelajaran | Upload teks / file / link, urutkan per topik |
| 4 | Tugas | Deadline, monitor pengumpulan, beri nilai & feedback |
| 5 | Ujian Online | Soal pilihan ganda & esai, timer, auto-grade PG |
| 6 | Rekap Nilai | Tabel rekap semua nilai per siswa |
| 7 | Pengumuman | Broadcast ke kelas, auto-notifikasi siswa |

## Urutan Pembangunan (Teacher Side)

1. Database & Migrations
2. Models + Relationships
3. Filament Resources (Classroom → Material → Assignment → Exam → Announcement)
4. Filament Dashboard Widgets
5. Policies & Filament Shield roles
6. Seeder & Factory untuk testing
