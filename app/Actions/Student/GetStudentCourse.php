<?php

namespace App\Actions\Student;

use App\Models\ClassroomSubject;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetStudentCourse
{
    /**
     * Ambil detail course (classroom_subject) + daftar materi terpublikasi
     * yang dapat diakses oleh student. Memvalidasi enrollment student.
     *
     * @return array{
     *     course: array<string, mixed>,
     *     materials: array<int, array<string, mixed>>,
     * }
     */
    public function handle(Student $student, string $courseId): array
    {
        $course = ClassroomSubject::query()
            ->with(['classroom', 'subject', 'teacher'])
            ->whereKey($courseId)
            ->whereHas('classroom.students', fn (Builder $q) => $q->whereKey($student->id))
            ->first();

        if (! $course) {
            throw new NotFoundHttpException('Course tidak ditemukan atau kamu tidak terdaftar di kelas ini.');
        }

        $visibilityScope = function (Builder $q): void {
            $q->where('is_published', true)
                ->where(fn (Builder $inner) => $inner->whereNull('available_from')->orWhere('available_from', '<=', now()))
                ->where(fn (Builder $inner) => $inner->whereNull('available_until')->orWhere('available_until', '>=', now()));
        };

        $materials = $course->materials()
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->withCount([
                'assignments as assignment_count' => $visibilityScope,
                'exams as exam_count' => $visibilityScope,
            ])
            ->orderBy('order')
            ->get()
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'title' => $material->title,
                'topic' => $material->topic,
                'description' => $material->description,
                'order' => $material->order,
                'available_from' => $material->available_from?->toIso8601String(),
                'created_at' => $material->created_at?->toIso8601String(),
                'has_files' => $material->getMedia('material_files')->isNotEmpty(),
                'has_link' => filled($material->link_url),
                'has_content' => filled($material->content),
                'assignment_count' => (int) ($material->assignment_count ?? 0),
                'exam_count' => (int) ($material->exam_count ?? 0),
            ])
            ->values()
            ->all();

        return [
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject?->name,
                'subject_code' => $course->subject?->code,
                'classroom_name' => $course->classroom?->name,
                'teacher_name' => $course->teacher?->full_name,
                'semester' => $course->semester,
                'academic_year' => $course->academic_year,
            ],
            'materials' => $materials,
        ];
    }
}
