# Implementation Checklist — Fase 1 (Teacher Dashboard)

## Enums

- [ ] `app/Models/Enums/GenderEnum.php`
- [ ] `app/Models/Enums/MaterialTypeEnum.php`
- [ ] `app/Models/Enums/ExamStatusEnum.php`
- [ ] `app/Models/Enums/QuestionTypeEnum.php`

---

## Migrations

- [ ] Update `users` table (uuid PK, is_active, last_login_at, last_login_ip, password_changed_at, soft delete)
- [ ] Create `schools`
- [ ] Create `teachers`
- [ ] Create `students`
- [ ] Create `subjects`
- [ ] Create `classrooms`
- [ ] Create `classroom_students` (pivot)
- [ ] Create `classroom_subjects`
- [ ] Create `materials`
- [ ] Create `assignments`
- [ ] Create `assignment_submissions`
- [ ] Create `exams`
- [ ] Create `exam_questions`
- [ ] Create `exam_sessions`
- [ ] Create `exam_answers`

---

## Models

- [ ] `User` — tambah fillable, casts, relationships, HasUuids, SoftDeletes
- [ ] `Teacher`
- [ ] `Student`
- [ ] `School`
- [ ] `Classroom`
- [ ] `Subject`
- [ ] `ClassroomSubject`
- [ ] `Material`
- [ ] `Assignment`
- [ ] `AssignmentSubmission`
- [ ] `Exam`
- [ ] `ExamQuestion`
- [ ] `ExamSession`
- [ ] `ExamAnswer`

---

## Seeders

- [ ] `RoleSeeder` — roles: super_admin, teacher, student (update yang ada)
- [ ] `SchoolSeeder` — 1 dummy school
- [ ] `UserSeeder` — 1 teacher + 1 student dummy
- [ ] `SubjectSeeder` — beberapa mata pelajaran contoh

---

## Filament Panel

- [ ] Pastikan `TeacherPanelProvider` atau `AdminPanelProvider` sudah register semua resource
- [ ] Shield: `php artisan shield:generate --all`

---

## Filament Resources

- [ ] `ClassroomResource`
  - [ ] List + filter by teacher
  - [ ] Create / Edit form
  - [ ] `StudentsRelationManager`
  - [ ] `ClassroomSubjectsRelationManager`
- [ ] `MaterialResource`
  - [ ] List + filter
  - [ ] Create / Edit (text/file/link)
  - [ ] Reorder
- [ ] `AssignmentResource`
  - [ ] List + filter
  - [ ] Create / Edit + file upload
  - [ ] `SubmissionsRelationManager` (nilai & feedback)
- [ ] `ExamResource`
  - [ ] List + filter
  - [ ] Create / Edit + status management
  - [ ] `QuestionsRelationManager` (CRUD soal + reorder)
  - [ ] `SessionsRelationManager` (monitoring + manual grading)
- [ ] `GradeResource`
  - [ ] Rekap nilai tugas + ujian per siswa per kelas

---

## Filament Widgets (Dashboard)

- [ ] `ClassroomStatsWidget`
- [ ] `StudentStatsWidget`
- [ ] `ActiveAssignmentWidget`
- [ ] `UpcomingExamWidget`

---

## Policies

- [ ] `ClassroomPolicy` — hanya teacher pemilik yang bisa edit/delete
- [ ] `MaterialPolicy`
- [ ] `AssignmentPolicy`
- [ ] `ExamPolicy`
