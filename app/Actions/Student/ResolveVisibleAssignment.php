<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cari tugas yang boleh dilihat siswa, lengkap dengan pengecekan aksesnya:
 * tugas & materinya terpublikasi, masih dalam jendela ketersediaan, dan siswa
 * terdaftar di kelas pemilik materi.
 *
 * Diekstrak dari Student\AssignmentController supaya web dan API memakai guard
 * yang SAMA. Ini otorisasi atas lampiran di disk privat — kalau disalin lalu
 * salah satu sisi tertinggal saat aturan berubah, siswa bisa mengunduh lampiran
 * tugas kelas lain.
 */
class ResolveVisibleAssignment
{
    /**
     * @throws NotFoundHttpException jika tugas tidak ada atau belum boleh diakses
     */
    public function handle(Student $student, string $materialId, string $assignmentId): Assignment
    {
        $assignment = Assignment::query()
            ->whereKey($assignmentId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->whereHas('material', function (Builder $q) use ($materialId, $student) {
                $q->whereKey($materialId)
                    ->where('is_published', true)
                    ->whereHas('classroomSubject.classroom.students', fn (Builder $s) => $s->whereKey($student->id));
            })
            ->first();

        if (! $assignment) {
            throw new NotFoundHttpException('Tugas tidak ditemukan atau belum tersedia.');
        }

        return $assignment;
    }
}
