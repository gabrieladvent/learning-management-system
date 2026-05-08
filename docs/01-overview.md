# LMS App — Project Overview

## Stack

- **Laravel 12** — backend framework
- **Filament 3** — admin/teacher panel (primary UI)
- **Spatie Laravel Permission** — role & permission management
- **Filament Shield** — auto-generate permissions per Filament resource
- **Spatie Laravel Media Library** — file attachment handling
- **Inertia.js + React** — student-facing frontend (future phase)

## Roles

| Role | Access |
|------|--------|
| `super_admin` | Full access to all panels |
| `teacher` | Filament panel — manage kelas, materi, tugas, ujian, nilai |
| `student` | Inertia/React frontend (future phase) |

## Global Conventions

- **UUID** — semua tabel menggunakan `uuid` sebagai primary key (`$table->uuid('id')->primary()`)
- **Soft Delete** — semua tabel utama menggunakan `SoftDeletes`
- **Timestamps** — semua tabel menggunakan `created_at` / `updated_at`
- **Enum** — status/tipe disimpan sebagai PHP 8.1 backed enum di `app/Models/Enums/`

## Development Phase (Current)

**Fase 1 — Teacher Dashboard (Filament)**

1. Schema DB & migrasi
2. Models + relationships
3. Filament Resources: Classroom, Material, Assignment, Exam, Grade
4. Filament Widgets: Dashboard stats

**Fase 2 — Student Frontend (Inertia/React)** *(belum dimulai)*
