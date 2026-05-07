# Models & Relationships

## Daftar Model

| Model | Tabel | Keterangan |
|---|---|---|
| `User` | `users` | Guru, Siswa, Admin |
| `Classroom` | `classrooms` | Kelas yang dibuat guru |
| `Topic` | `topics` | Pertemuan / topik dalam kelas |
| `Material` | `materials` | Materi pembelajaran |
| `Assignment` | `assignments` | Tugas |
| `AssignmentSubmission` | `assignment_submissions` | Jawaban siswa |
| `Exam` | `exams` | Ujian |
| `Question` | `questions` | Soal ujian |
| `QuestionOption` | `question_options` | Pilihan jawaban |
| `ExamSession` | `exam_sessions` | Sesi siswa mengerjakan ujian |
| `ExamAnswer` | `exam_answers` | Jawaban siswa per soal |
| `Announcement` | `announcements` | Pengumuman guru |

---

## Relasi Lengkap

### User
```php
// Sebagai Guru
hasMany(Classroom::class, 'teacher_id')
hasMany(Announcement::class, 'teacher_id')

// Sebagai Siswa
belongsToMany(Classroom::class, 'classroom_student', 'student_id')
    ->withPivot('enrolled_at')->withTimestamps()
hasMany(AssignmentSubmission::class, 'student_id')
hasMany(ExamSession::class, 'student_id')

// Traits
use HasRoles;              // spatie/laravel-permission
use InteractsWithMedia;    // spatie/laravel-media-library
```

### Classroom
```php
belongsTo(User::class, 'teacher_id')
belongsToMany(User::class, 'classroom_student', foreignPivotKey: 'classroom_id', relatedPivotKey: 'student_id')
    ->withPivot('enrolled_at')->withTimestamps()

hasMany(Topic::class)
hasMany(Material::class)
hasMany(Assignment::class)
hasMany(Exam::class)
hasMany(Announcement::class)
```

### Topic
```php
belongsTo(Classroom::class)
hasMany(Material::class)
hasMany(Assignment::class)
hasMany(Exam::class)
```

### Material
```php
belongsTo(Classroom::class)
belongsTo(Topic::class)->nullable()

// Traits
use InteractsWithMedia;  // collection: 'material_files'
```

### Assignment
```php
belongsTo(Classroom::class)
belongsTo(Topic::class)->nullable()
hasMany(AssignmentSubmission::class)
```

### AssignmentSubmission
```php
belongsTo(Assignment::class)
belongsTo(User::class, 'student_id')
belongsTo(User::class, 'graded_by')

// Traits
use InteractsWithMedia;  // collection: 'submission_files'
```

### Exam
```php
belongsTo(Classroom::class)
belongsTo(Topic::class)->nullable()
hasMany(Question::class)->orderBy('order')
hasMany(ExamSession::class)
```

### Question
```php
belongsTo(Exam::class)
hasMany(QuestionOption::class)->orderBy('order')
hasMany(ExamAnswer::class)
```

### QuestionOption
```php
belongsTo(Question::class)
```

### ExamSession
```php
belongsTo(Exam::class)
belongsTo(User::class, 'student_id')
hasMany(ExamAnswer::class)
```

### ExamAnswer
```php
belongsTo(ExamSession::class)
belongsTo(Question::class)
belongsTo(QuestionOption::class, 'selected_option_id')->nullable()
```

### Announcement
```php
belongsTo(Classroom::class)->nullable()
belongsTo(User::class, 'teacher_id')
```

---

## Helper Scopes (rekomendasi)

```php
// Classroom
scopeActive($query)          // where is_active = true
scopeByTeacher($query, $id)  // where teacher_id = $id

// Assignment
scopePublished($query)       // where is_published = true
scopeDueSoon($query, $days)  // deadline dalam N hari ke depan

// Exam
scopeActive($query)          // start_time <= now() AND end_time >= now() AND is_published
```

---

## Casting & Appends (rekomendasi)

```php
// Classroom
protected $casts = [
    'is_active' => 'boolean',
];

// Assignment
protected $casts = [
    'deadline'     => 'datetime',
    'is_published' => 'boolean',
    'allow_late'   => 'boolean',
];

// Exam
protected $casts = [
    'start_time'   => 'datetime',
    'end_time'     => 'datetime',
    'is_published' => 'boolean',
];

// ExamSession
protected $casts = [
    'started_at'  => 'datetime',
    'finished_at' => 'datetime',
];
```
