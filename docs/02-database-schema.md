# Database Schema

## ERD Ringkasan

```
users
  └─< classrooms (teacher_id)
  └─< classroom_student (pivot enrollment)
        └── classrooms

classrooms
  └─< topics
  └─< materials
  └─< assignments
        └─< assignment_submissions (student_id)
  └─< exams
        └─< questions
              └─< question_options
        └─< exam_sessions (student_id)
              └─< exam_answers
  └─< announcements
```

---

## Tabel Detail

### `users` *(sudah ada, extend saja)*
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | |
| email_verified_at | timestamp nullable | |
| remember_token | string nullable | |
| timestamps | | |

> Role dikelola via **spatie/laravel-permission** (`model_has_roles`, dst).
> Avatar dikelola via **spatie/laravel-media-library** (collection `avatar`).

---

### `classrooms`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| teacher_id | bigint FK → users | |
| name | string | Nama kelas, misal "Matematika 10A" |
| subject | string | Mata pelajaran |
| description | text nullable | |
| code | string(8) unique | Kode join siswa |
| academic_year | string | Contoh: "2025/2026" |
| semester | tinyint | 1 atau 2 |
| is_active | boolean default true | |
| timestamps | | |

---

### `classroom_student` *(pivot)*
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| classroom_id | bigint FK | |
| student_id | bigint FK → users | |
| enrolled_at | timestamp | |
| timestamps | | |

---

### `topics`
Pertemuan / topik untuk mengelompokkan materi, tugas, ujian.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| classroom_id | bigint FK | |
| title | string | Contoh: "Pertemuan 1: Aljabar" |
| description | text nullable | |
| order | unsignedInt default 0 | Urutan tampil |
| timestamps | | |

---

### `materials`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| classroom_id | bigint FK | |
| topic_id | bigint FK nullable | |
| title | string | |
| type | enum('text','file','link') | |
| content | longtext nullable | Untuk type=text |
| url | string nullable | Untuk type=link |
| order | unsignedInt default 0 | |
| is_published | boolean default false | |
| published_at | timestamp nullable | |
| timestamps | | |

> File (PDF/Word/dll) dikelola via **media-library** pada collection `material_files`.

---

### `assignments`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| classroom_id | bigint FK | |
| topic_id | bigint FK nullable | |
| title | string | |
| description | longtext | Instruksi soal |
| deadline | datetime | |
| max_score | unsignedSmallInt default 100 | |
| allow_late | boolean default false | |
| is_published | boolean default false | |
| published_at | timestamp nullable | |
| timestamps | | |

---

### `assignment_submissions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| assignment_id | bigint FK | |
| student_id | bigint FK → users | |
| content | longtext nullable | Jawaban teks |
| url | string nullable | Jawaban link |
| submitted_at | timestamp | |
| is_late | boolean default false | |
| score | decimal(5,2) nullable | Diisi guru |
| feedback | text nullable | Komentar guru |
| graded_at | timestamp nullable | |
| graded_by | bigint FK → users nullable | |
| timestamps | | |

> File jawaban via **media-library** collection `submission_files`.

---

### `exams`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| classroom_id | bigint FK | |
| topic_id | bigint FK nullable | |
| title | string | |
| description | text nullable | |
| duration_minutes | unsignedSmallInt | Timer ujian |
| start_time | datetime | Waktu buka ujian |
| end_time | datetime | Waktu tutup ujian |
| is_published | boolean default false | |
| timestamps | | |

---

### `questions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| exam_id | bigint FK | |
| type | enum('multiple_choice','essay') | |
| question_text | longtext | |
| points | unsignedSmallInt default 1 | |
| order | unsignedInt default 0 | |
| timestamps | | |

---

### `question_options` *(hanya untuk multiple_choice)*
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| question_id | bigint FK | |
| option_text | text | |
| is_correct | boolean default false | |
| order | unsignedTinyInt default 0 | |
| timestamps | | |

---

### `exam_sessions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| exam_id | bigint FK | |
| student_id | bigint FK → users | |
| started_at | timestamp | |
| finished_at | timestamp nullable | |
| score | decimal(6,2) nullable | Auto-calculated |
| status | enum('ongoing','completed','expired') | |
| timestamps | | |

---

### `exam_answers`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| exam_session_id | bigint FK | |
| question_id | bigint FK | |
| selected_option_id | bigint FK nullable | Untuk multiple_choice |
| answer_text | text nullable | Untuk essay |
| score | decimal(5,2) nullable | Essay dinilai manual guru |
| timestamps | | |

---

### `announcements`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| classroom_id | bigint FK nullable | null = semua kelas guru |
| teacher_id | bigint FK → users | |
| title | string | |
| content | longtext | |
| published_at | timestamp nullable | |
| timestamps | | |

> Notifikasi ke siswa dikirim via Laravel Notification (tabel `notifications` bawaan Laravel).
