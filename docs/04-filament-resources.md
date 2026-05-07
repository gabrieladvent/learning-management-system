# Filament Resources (Teacher Side)

Semua resource berada di namespace `App\Filament\Resources`.
Panel Filament di-mount di path `/admin`.

---

## 1. ClassroomResource

**File:** `ClassroomResource.php`

### Form Fields
| Field | Widget | Keterangan |
|---|---|---|
| `name` | TextInput | Required |
| `subject` | TextInput | Required |
| `description` | Textarea | Optional |
| `academic_year` | TextInput | Contoh: 2025/2026 |
| `semester` | Select | 1 / 2 |
| `is_active` | Toggle | Default true |
| `code` | TextInput | Auto-generate, disabled |

### Table Columns
- name, subject, semester, academic_year, students_count, is_active (badge), created_at

### Tabs / Relation Managers
- **StudentsRelationManager** — daftar siswa enrolled, bisa attach/detach
- **TopicsRelationManager** — daftar topik/pertemuan (CRUD inline)

### Actions
- `GenerateCode` — regenerate kode join
- `CopyCode` — salin kode join ke clipboard

---

## 2. TopicResource *(atau hanya RelationManager)*

Topik dikelola langsung dari dalam `ClassroomResource` via **TopicsRelationManager**.

### Form Fields
| Field | Widget |
|---|---|
| `title` | TextInput |
| `description` | Textarea |
| `order` | TextInput (number) |

---

## 3. MaterialResource

**File:** `MaterialResource.php`

### Form Fields
| Field | Widget | Keterangan |
|---|---|---|
| `classroom_id` | Select | Hanya kelas milik guru login |
| `topic_id` | Select | Dependent on classroom_id |
| `title` | TextInput | |
| `type` | Radio/Select | text / file / link |
| `content` | RichEditor | Muncul jika type=text |
| `url` | TextInput | Muncul jika type=link |
| `file` | SpatieMediaLibraryFileUpload | Muncul jika type=file |
| `order` | TextInput | |
| `is_published` | Toggle | |
| `published_at` | DateTimePicker | Auto-fill saat toggle |

### Table Columns
- title, classroom.name, topic.title, type (badge), is_published (badge), published_at

---

## 4. AssignmentResource

**File:** `AssignmentResource.php`

### Form Fields
| Field | Widget |
|---|---|
| `classroom_id` | Select |
| `topic_id` | Select (dependent) |
| `title` | TextInput |
| `description` | RichEditor |
| `deadline` | DateTimePicker |
| `max_score` | TextInput (number) |
| `allow_late` | Toggle |
| `is_published` | Toggle |

### Table Columns
- title, classroom.name, deadline (color merah jika terlewat), submissions_count, is_published

### Relation Managers
- **SubmissionsRelationManager**
  - Tabel: student name, submitted_at, is_late (badge), score, feedback
  - Action inline: **GradeSubmission** — isi score + feedback
  - Filter: Sudah dinilai / Belum dinilai / Terlambat

---

## 5. ExamResource

**File:** `ExamResource.php`

### Form Fields
| Field | Widget |
|---|---|
| `classroom_id` | Select |
| `topic_id` | Select |
| `title` | TextInput |
| `description` | Textarea |
| `duration_minutes` | TextInput (number) |
| `start_time` | DateTimePicker |
| `end_time` | DateTimePicker |
| `is_published` | Toggle |

### Table Columns
- title, classroom.name, duration, start_time, end_time, questions_count, sessions_count

### Relation Managers

#### QuestionsRelationManager
Kelola soal langsung dari dalam halaman ujian.

| Field | Widget | Keterangan |
|---|---|---|
| `type` | Select | multiple_choice / essay |
| `question_text` | RichEditor | |
| `points` | TextInput | |
| `order` | TextInput | |

Jika type=`multiple_choice`, muncul **OptionsRepeater**:
- option_text (TextInput)
- is_correct (Toggle, hanya satu yang bisa true)

#### SessionsRelationManager
Daftar siswa yang sudah mengerjakan: student name, started_at, finished_at, score, status.

Action: **GradeEssay** — nilai jawaban esai per soal untuk satu sesi.

---

## 6. AnnouncementResource

**File:** `AnnouncementResource.php`

### Form Fields
| Field | Widget | Keterangan |
|---|---|---|
| `classroom_id` | Select nullable | null = semua kelas guru |
| `title` | TextInput | |
| `content` | RichEditor | |
| `published_at` | DateTimePicker | Jadwalkan publish |

### Table Columns
- title, classroom.name (atau "Semua Kelas"), published_at, created_at

> Saat `published_at` tercapai, kirim Laravel Notification ke seluruh siswa di kelas terkait.

---

## 7. Dashboard Widgets

### StatsOverviewWidget
```
[ Total Kelas Aktif ]  [ Total Siswa ]  [ Tugas Menunggu Penilaian ]  [ Ujian Berlangsung ]
```

### RecentSubmissionsWidget
Tabel 5 pengumpulan tugas terbaru dari semua kelas guru.

### UpcomingExamsWidget
Daftar ujian yang akan dimulai dalam 7 hari ke depan.

---

## Navigation Groups (Filament)

```
Dashboard
  └── Dashboard (default)

Kelas
  └── Kelas Saya        (ClassroomResource)

Konten Pembelajaran
  ├── Materi            (MaterialResource)
  ├── Tugas             (AssignmentResource)
  └── Ujian             (ExamResource)

Komunikasi
  └── Pengumuman        (AnnouncementResource)
```

---

## Scope Data per Guru

Setiap Resource harus menggunakan `EloquentQuery` yang difilter berdasarkan `auth()->id()` sebagai `teacher_id`, sehingga satu guru tidak bisa melihat data guru lain.

```php
// Contoh di ClassroomResource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('teacher_id', auth()->id());
}
```

Sama diterapkan di Material, Assignment, Exam, Announcement.
