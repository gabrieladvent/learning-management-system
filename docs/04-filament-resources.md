# Filament Resources — Teacher Dashboard

Panel: `TeacherPanelProvider` (atau extend `AdminPanelProvider` dengan role guard).

---

## 1. Dashboard Widgets

Tampil di halaman utama Filament teacher.

| Widget | Class | Data |
|--------|-------|------|
| Total Kelas | `ClassroomStatsWidget` | Jumlah kelas aktif yang diajar teacher login |
| Total Siswa | `StudentStatsWidget` | Total siswa di semua kelas teacher |
| Tugas Aktif | `ActiveAssignmentWidget` | Tugas dengan deadline belum lewat |
| Ujian Mendatang | `UpcomingExamWidget` | Ujian dengan status published / starts_at > now |

Semua widget di-scope ke `Auth::user()->teacher`.

---

## 2. ClassroomResource

**Path:** `app/Filament/Resources/ClassroomResource.php`

**Fitur:**
- List kelas milik teacher (filter by `teacher_id`)
- Create / edit kelas:
  - Nama, grade level, academic year, status aktif
  - Assign siswa (RelationManager `StudentsRelationManager`)
  - Assign mata pelajaran + guru pengajar (`ClassroomSubjectsRelationManager`)
- Soft delete

**RelationManagers:**
- `StudentsRelationManager` — attach/detach siswa ke kelas
- `ClassroomSubjectsRelationManager` — tambah/edit classroom_subject (subject + teacher)

---

## 3. MaterialResource

**Path:** `app/Filament/Resources/MaterialResource.php`

**Fitur:**
- List materi filter by classroom_subject yang diajar teacher login
- Create / edit:
  - Pilih classroom_subject
  - Judul, deskripsi, topik, urutan
  - Tipe: Text (RichEditor), File (SpatieMediaLibraryFileUpload), Link (URL input)
  - Tanggal publish (bisa dijadwal)
- Reorder materi (drag & drop dengan `$table->reorderable('order')`)
- Soft delete

---

## 4. AssignmentResource

**Path:** `app/Filament/Resources/AssignmentResource.php`

**Fitur:**
- List tugas filter by classroom_subject teacher
- Create / edit:
  - Pilih classroom_subject
  - Judul, deskripsi (RichEditor)
  - Deadline (DateTimePicker)
  - Nilai maksimum
  - Upload lampiran (SpatieMediaLibraryFileUpload)
- Detail page → tab:
  - **Submissions** (RelationManager): lihat siapa sudah/belum kumpul, nilai, feedback
- Soft delete

**RelationManagers:**
- `SubmissionsRelationManager` — beri nilai & feedback per siswa

---

## 5. ExamResource

**Path:** `app/Filament/Resources/ExamResource.php`

**Fitur:**
- List ujian filter by classroom_subject teacher
- Create / edit ujian:
  - Pilih classroom_subject
  - Judul, deskripsi
  - Tanggal mulai, durasi (menit)
  - Shuffle questions toggle
  - Status: draft → published → closed
- RelationManager **QuestionsRelationManager**:
  - Add soal: tipe, teks soal, opsi (JSON editor / repeater), jawaban benar, bobot
  - Reorder soal
  - Upload gambar soal (media library)
- Detail page → tab **Sessions**: monitoring siapa sudah ikut, total skor
- Auto-grading untuk `multiple_choice` saat submit
- Manual grading essay: buka session → isi score & feedback per soal

**RelationManagers:**
- `QuestionsRelationManager`
- `SessionsRelationManager` — monitoring & manual grading

---

## 6. GradeResource (Rekap Nilai)

**Path:** `app/Filament/Resources/GradeResource.php`

**Fitur:**
- Pilih kelas + mata pelajaran
- Tampilkan rekap per siswa:
  - Nilai tiap tugas
  - Nilai tiap ujian
  - Rata-rata
- Edit nilai langsung dari tabel (inline)
- Export ke Excel (optional — via `pxlrbt/filament-excel` atau malogisk/filament-excel)

---

## Scoping (Multi-tenancy Sederhana)

Karena satu Filament panel dipakai semua teacher, setiap resource harus di-scope ke teacher yang login:

```php
// Contoh di ClassroomResource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('teacher_id', auth()->user()->teacher->id);
}
```

Untuk `ClassroomSubject`-based resources (Material, Assignment, Exam):

```php
public static function getEloquentQuery(): Builder
{
    $teacherId = auth()->user()->teacher->id;
    return parent::getEloquentQuery()
        ->whereHas('classroomSubject', fn($q) => $q->where('teacher_id', $teacherId));
}
```

---

## Navigation Groups

```
- Dashboard
- Kelas
  - Daftar Kelas
- Pembelajaran
  - Materi
  - Tugas
  - Ujian
- Penilaian
  - Rekap Nilai
```
