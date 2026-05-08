# Database Schema

Semua tabel menggunakan UUID sebagai PK dan soft delete kecuali pivot table.

---

## users

Tabel auth utama Laravel.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | hashed |
| is_active | boolean | default true |
| last_login_at | timestamp | nullable |
| last_login_ip | string | nullable |
| password_changed_at | timestamp | nullable |
| remember_token | string | nullable |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## teachers

Profil guru — one-to-one dengan `users`.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| user_id | uuid | FK → users.id |
| full_name | string | |
| nip | string | nullable, unique |
| specialization | string | nullable |
| phone | string | nullable |
| nik | string | nullable, unique |
| birth_date | date | nullable |
| place_of_birth | string | nullable |
| gender | enum | `male`, `female` |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## students

Profil siswa — one-to-one dengan `users`.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| user_id | uuid | FK → users.id |
| school_id | uuid | FK → schools.id |
| nisn | string | nullable, unique |
| full_name | string | |
| class | string | e.g. "X IPA 1" |
| gender | enum | `male`, `female` |
| place_of_birth | string | nullable |
| birth_date | date | nullable |
| is_active | boolean | default true |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## schools

Institusi/sekolah.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| name | string | |
| address | text | nullable |
| phone | string | nullable |
| email | string | nullable |
| logo | string | nullable, via media-library |
| is_active | boolean | default true |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## classrooms

Kelas (rombongan belajar per tahun ajaran).

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| school_id | uuid | FK → schools.id |
| teacher_id | uuid | FK → teachers.id (wali kelas) |
| name | string | e.g. "X IPA 1" |
| grade_level | string | e.g. "X", "XI", "XII" |
| academic_year | string | e.g. "2025/2026" |
| is_active | boolean | default true |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## classroom_students (pivot)

Siswa yang terdaftar di kelas.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| classroom_id | uuid | FK → classrooms.id |
| student_id | uuid | FK → students.id |
| enrolled_at | timestamp | default now |

---

## subjects

Mata pelajaran.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| name | string | |
| code | string | nullable, unique |
| description | text | nullable |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## classroom_subjects

Assignment guru mengajar mata pelajaran di kelas tertentu.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| classroom_id | uuid | FK → classrooms.id |
| subject_id | uuid | FK → subjects.id |
| teacher_id | uuid | FK → teachers.id |
| academic_year | string | e.g. "2025/2026" |
| semester | tinyint | 1 atau 2 |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## materials

Materi pembelajaran per classroom_subject.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| classroom_subject_id | uuid | FK → classroom_subjects.id |
| title | string | |
| description | text | nullable |
| type | enum | `text`, `file`, `link` |
| content | text | nullable (text body atau URL) |
| topic | string | nullable (grouping/topik) |
| order | integer | default 0 (urutan tampil) |
| published_at | timestamp | nullable |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

> File attachment ditangani oleh Spatie Media Library (koleksi `material_files`).

---

## assignments

Tugas per classroom_subject.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| classroom_subject_id | uuid | FK → classroom_subjects.id |
| title | string | |
| description | text | nullable |
| deadline | datetime | |
| max_score | decimal(5,2) | default 100 |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

> File attachment guru: Spatie Media Library (koleksi `assignment_attachments`).

---

## assignment_submissions

Pengumpulan tugas oleh siswa.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| assignment_id | uuid | FK → assignments.id |
| student_id | uuid | FK → students.id |
| content | text | nullable (jawaban teks) |
| submitted_at | timestamp | nullable |
| score | decimal(5,2) | nullable |
| feedback | text | nullable |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

> File attachment siswa: Spatie Media Library (koleksi `submission_files`).

---

## exams

Ujian per classroom_subject.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| classroom_subject_id | uuid | FK → classroom_subjects.id |
| title | string | |
| description | text | nullable |
| starts_at | datetime | |
| duration_minutes | integer | |
| shuffle_questions | boolean | default false |
| status | enum | `draft`, `published`, `closed` |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp | soft delete |

---

## exam_questions

Soal ujian.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| exam_id | uuid | FK → exams.id |
| type | enum | `multiple_choice`, `short_answer`, `essay` |
| question | text | |
| options | json | nullable (untuk multiple_choice: array pilihan) |
| correct_answer | string | nullable (untuk multiple_choice) |
| score | decimal(5,2) | bobot soal |
| order | integer | default 0 |
| created_at / updated_at | timestamps | |

> File pada soal: Spatie Media Library (koleksi `question_files`).

---

## exam_sessions

Sesi pengerjaan ujian oleh siswa.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| exam_id | uuid | FK → exams.id |
| student_id | uuid | FK → students.id |
| started_at | timestamp | nullable |
| submitted_at | timestamp | nullable |
| total_score | decimal(5,2) | nullable |
| created_at / updated_at | timestamps | |

---

## exam_answers

Jawaban per soal per sesi ujian.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | PK |
| exam_session_id | uuid | FK → exam_sessions.id |
| exam_question_id | uuid | FK → exam_questions.id |
| answer | text | nullable |
| score | decimal(5,2) | nullable (auto/manual grading) |
| feedback | text | nullable |
| created_at / updated_at | timestamps | |
