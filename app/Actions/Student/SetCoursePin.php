<?php

namespace App\Actions\Student;

use App\Models\Student;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pin / unpin mata pelajaran untuk siswa.
 *
 * Diekstrak dari Student\CourseController supaya web dan API memakai guard
 * enrollment yang SAMA. Kalau logikanya disalin ke controller API, perbaikan di
 * satu sisi tidak ikut di sisi lain — dan ini pengecekan otorisasi.
 */
class SetCoursePin
{
    /**
     * Idempoten: pin dua kali tidak error, unpin yang belum di-pin juga tidak.
     *
     * @throws NotFoundHttpException jika siswa tidak terdaftar di kelas pemilik course
     */
    public function handle(Student $student, string $courseId, bool $pinned): void
    {
        $belongs = $student->classrooms()
            ->whereHas('classroomSubjects', fn ($q) => $q->whereKey($courseId))
            ->exists();

        if (! $belongs) {
            // Pesan sengaja sama dengan "tidak ada" — jangan bocorkan bahwa
            // course-nya ada tapi bukan milik siswa ini.
            throw new NotFoundHttpException('Mata pelajaran tidak ditemukan.');
        }

        if ($pinned) {
            $student->pinnedClassroomSubjects()->syncWithoutDetaching([
                $courseId => ['pinned_at' => now()],
            ]);

            return;
        }

        $student->pinnedClassroomSubjects()->detach($courseId);
    }
}
