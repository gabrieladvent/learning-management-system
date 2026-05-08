# Models

Semua model menggunakan:
- `HasUuids` trait (Laravel built-in)
- `SoftDeletes` trait
- `HasFactory` trait

---

## User (`app/Models/User.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`, `HasRoles` (Spatie), `Notifiable`, `FilamentUser`

**Fillable tambahan dari default:**
```
is_active, last_login_at, last_login_ip, password_changed_at
```

**Relationships:**
- `teacher()` → hasOne(Teacher)
- `student()` → hasOne(Student)

**Casts:**
```php
'email_verified_at' => 'datetime',
'last_login_at'     => 'datetime',
'password_changed_at' => 'datetime',
'password'          => 'hashed',
'is_active'         => 'boolean',
```

---

## Teacher (`app/Models/Teacher.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`

**Fillable:**
```
user_id, full_name, nip, specialization, phone, nik, birth_date, place_of_birth, gender
```

**Relationships:**
- `user()` → belongsTo(User)
- `classrooms()` → hasMany(Classroom) — sebagai wali kelas
- `classroomSubjects()` → hasMany(ClassroomSubject) — sebagai pengajar

**Casts:**
```php
'birth_date' => 'date',
'gender'     => GenderEnum::class,
```

---

## Student (`app/Models/Student.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`

**Fillable:**
```
user_id, school_id, nisn, full_name, class, gender, place_of_birth, birth_date, is_active
```

**Relationships:**
- `user()` → belongsTo(User)
- `school()` → belongsTo(School)
- `classrooms()` → belongsToMany(Classroom, 'classroom_students')
- `assignmentSubmissions()` → hasMany(AssignmentSubmission)
- `examSessions()` → hasMany(ExamSession)

**Casts:**
```php
'birth_date' => 'date',
'gender'     => GenderEnum::class,
'is_active'  => 'boolean',
```

---

## School (`app/Models/School.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`, `InteractsWithMedia`

**Fillable:**
```
name, address, phone, email, is_active
```

**Relationships:**
- `students()` → hasMany(Student)
- `classrooms()` → hasMany(Classroom)

---

## Classroom (`app/Models/Classroom.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`

**Fillable:**
```
school_id, teacher_id, name, grade_level, academic_year, is_active
```

**Relationships:**
- `school()` → belongsTo(School)
- `teacher()` → belongsTo(Teacher) — wali kelas
- `students()` → belongsToMany(Student, 'classroom_students')->withPivot('enrolled_at')
- `classroomSubjects()` → hasMany(ClassroomSubject)

---

## Subject (`app/Models/Subject.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`

**Fillable:**
```
name, code, description
```

**Relationships:**
- `classroomSubjects()` → hasMany(ClassroomSubject)

---

## ClassroomSubject (`app/Models/ClassroomSubject.php`)

*Mewakili 1 guru mengajar 1 mata pelajaran di 1 kelas.*

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`

**Fillable:**
```
classroom_id, subject_id, teacher_id, academic_year, semester
```

**Relationships:**
- `classroom()` → belongsTo(Classroom)
- `subject()` → belongsTo(Subject)
- `teacher()` → belongsTo(Teacher)
- `materials()` → hasMany(Material)
- `assignments()` → hasMany(Assignment)
- `exams()` → hasMany(Exam)

---

## Material (`app/Models/Material.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`, `InteractsWithMedia`

**Fillable:**
```
classroom_subject_id, title, description, type, content, topic, order, published_at
```

**Relationships:**
- `classroomSubject()` → belongsTo(ClassroomSubject)

**Casts:**
```php
'type'         => MaterialTypeEnum::class,
'published_at' => 'datetime',
'order'        => 'integer',
```

**Media Collections:**
- `material_files` — file lampiran materi

---

## Assignment (`app/Models/Assignment.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`, `InteractsWithMedia`

**Fillable:**
```
classroom_subject_id, title, description, deadline, max_score
```

**Relationships:**
- `classroomSubject()` → belongsTo(ClassroomSubject)
- `submissions()` → hasMany(AssignmentSubmission)

**Casts:**
```php
'deadline'  => 'datetime',
'max_score' => 'decimal:2',
```

**Media Collections:**
- `assignment_attachments` — file lampiran dari guru

---

## AssignmentSubmission (`app/Models/AssignmentSubmission.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`, `InteractsWithMedia`

**Fillable:**
```
assignment_id, student_id, content, submitted_at, score, feedback
```

**Relationships:**
- `assignment()` → belongsTo(Assignment)
- `student()` → belongsTo(Student)

**Casts:**
```php
'submitted_at' => 'datetime',
'score'        => 'decimal:2',
```

**Media Collections:**
- `submission_files` — file jawaban siswa

---

## Exam (`app/Models/Exam.php`)

**Traits:** `HasUuids`, `SoftDeletes`, `HasFactory`

**Fillable:**
```
classroom_subject_id, title, description, starts_at, duration_minutes, shuffle_questions, status
```

**Relationships:**
- `classroomSubject()` → belongsTo(ClassroomSubject)
- `questions()` → hasMany(ExamQuestion)->orderBy('order')
- `sessions()` → hasMany(ExamSession)

**Casts:**
```php
'starts_at'          => 'datetime',
'shuffle_questions'  => 'boolean',
'status'             => ExamStatusEnum::class,
```

---

## ExamQuestion (`app/Models/ExamQuestion.php`)

**Traits:** `HasUuids`, `HasFactory`, `InteractsWithMedia`

**Fillable:**
```
exam_id, type, question, options, correct_answer, score, order
```

**Relationships:**
- `exam()` → belongsTo(Exam)
- `answers()` → hasMany(ExamAnswer)

**Casts:**
```php
'type'    => QuestionTypeEnum::class,
'options' => 'array',
'score'   => 'decimal:2',
'order'   => 'integer',
```

**Media Collections:**
- `question_files` — gambar/file pada soal

---

## ExamSession (`app/Models/ExamSession.php`)

**Traits:** `HasUuids`, `HasFactory`

**Fillable:**
```
exam_id, student_id, started_at, submitted_at, total_score
```

**Relationships:**
- `exam()` → belongsTo(Exam)
- `student()` → belongsTo(Student)
- `answers()` → hasMany(ExamAnswer)

**Casts:**
```php
'started_at'   => 'datetime',
'submitted_at' => 'datetime',
'total_score'  => 'decimal:2',
```

---

## ExamAnswer (`app/Models/ExamAnswer.php`)

**Traits:** `HasUuids`, `HasFactory`

**Fillable:**
```
exam_session_id, exam_question_id, answer, score, feedback
```

**Relationships:**
- `session()` → belongsTo(ExamSession)
- `question()` → belongsTo(ExamQuestion)

---

## Enums (`app/Models/Enums/`)

```php
// GenderEnum.php
enum GenderEnum: string {
    case Male   = 'male';
    case Female = 'female';
}

// MaterialTypeEnum.php
enum MaterialTypeEnum: string {
    case Text = 'text';
    case File = 'file';
    case Link = 'link';
}

// ExamStatusEnum.php
enum ExamStatusEnum: string {
    case Draft     = 'draft';
    case Published = 'published';
    case Closed    = 'closed';
}

// QuestionTypeEnum.php
enum QuestionTypeEnum: string {
    case MultipleChoice = 'multiple_choice';
    case ShortAnswer    = 'short_answer';
    case Essay          = 'essay';
}
```
