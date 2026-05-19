<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\ClassroomSubject;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetStudentMaterial
{
    /**
     * Ambil detail material untuk student. Memvalidasi:
     * - student terdaftar di classroom milik course
     * - material ada di course tersebut
     * - material is_published & dalam range available_from/until
     *
     * @return array{
     *     course: array<string, mixed>,
     *     material: array<string, mixed>,
     * }
     */
    public function handle(Student $student, string $courseId, string $materialId): array
    {
        $course = ClassroomSubject::query()
            ->with(['classroom', 'subject', 'teacher'])
            ->whereKey($courseId)
            ->whereHas('classroom.students', fn (Builder $q) => $q->whereKey($student->id))
            ->first();

        if (! $course) {
            throw new NotFoundHttpException('Course tidak ditemukan atau kamu tidak terdaftar di kelas ini.');
        }

        $material = $course->materials()
            ->whereKey($materialId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->first();

        if (! $material) {
            throw new NotFoundHttpException('Materi tidak ditemukan atau belum tersedia.');
        }

        $files = $material->getMedia('material_files')->map(fn ($media) => [
            'id' => $media->uuid ?? (string) $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'extension' => pathinfo($media->file_name, PATHINFO_EXTENSION),
            'url' => $media->getUrl(),
        ])->values()->all();

        $assignments = $material->assignments()
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderBy('order')
            ->get()
            ->map(fn (Assignment $assignment) => $this->mapAssignment($assignment))
            ->values()
            ->all();

        return [
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject?->name,
                'subject_code' => $course->subject?->code,
                'classroom_name' => $course->classroom?->name,
                'teacher_name' => $course->teacher?->full_name,
            ],
            'material' => [
                'id' => $material->id,
                'title' => $material->title,
                'topic' => $material->topic,
                'description' => $material->description,
                'content' => $material->content,
                'link_url' => $material->link_url,
                'available_from' => $material->available_from?->toIso8601String(),
                'available_until' => $material->available_until?->toIso8601String(),
                'created_at' => $material->created_at?->toIso8601String(),
                'files' => $files,
                'assignments' => $assignments,
            ],
        ];
    }

    /**
     * Bentuk ringkasan assignment + status submission siswa untuk list card.
     *
     * @return array<string, mixed>
     */
    private function mapAssignment(Assignment $assignment): array
    {
        $submission = $assignment->submissions->first();
        $isOverdue = $assignment->deadline && now()->greaterThan($assignment->deadline);

        $status = match (true) {
            $submission && $submission->score !== null => 'graded',
            $submission && $submission->submitted_at !== null => 'submitted',
            $isOverdue => 'overdue',
            default => 'pending',
        };

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'description' => $assignment->description ? str($assignment->description)->stripTags()->limit(160)->toString() : null,
            'deadline' => $assignment->deadline?->toIso8601String(),
            'max_score' => $assignment->max_score !== null ? (float) $assignment->max_score : null,
            'status' => $status,
            'is_overdue' => (bool) $isOverdue,
            'submitted_at' => $submission?->submitted_at?->toIso8601String(),
            'score' => $submission && $submission->score !== null ? (float) $submission->score : null,
        ];
    }
}
