<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetStudentAssignment
{
    /**
     * Ambil detail assignment + lampiran soal + submission siswa.
     * Memvalidasi enrollment + visibility material & assignment.
     *
     * @return array{
     *     course: array<string, mixed>,
     *     material: array<string, mixed>,
     *     assignment: array<string, mixed>,
     *     submission: ?array<string, mixed>,
     * }
     */
    public function handle(Student $student, string $materialId, string $assignmentId): array
    {
        $material = Material::query()
            ->with(['classroomSubject.classroom', 'classroomSubject.subject', 'classroomSubject.teacher'])
            ->whereKey($materialId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->whereHas('classroomSubject.classroom.students', fn (Builder $q) => $q->whereKey($student->id))
            ->first();

        if (! $material) {
            throw new NotFoundHttpException('Materi tidak ditemukan atau belum tersedia.');
        }

        $assignment = $material->assignments()
            ->whereKey($assignmentId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->first();

        if (! $assignment) {
            throw new NotFoundHttpException('Tugas tidak ditemukan atau belum tersedia.');
        }

        /** @var ?AssignmentSubmission $submission */
        $submission = $assignment->submissions()
            ->where('student_id', $student->id)
            ->first();

        $attachments = $assignment->getMedia('assignment_attachments')->map(fn ($media) => [
            'id' => $media->uuid ?? (string) $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'extension' => pathinfo($media->file_name, PATHINFO_EXTENSION),
            'url' => $media->getUrl(),
        ])->values()->all();

        $course = $material->classroomSubject;
        $isOverdue = $assignment->deadline && now()->greaterThan($assignment->deadline);

        $status = match (true) {
            $submission && $submission->score !== null => 'graded',
            $submission && $submission->submitted_at !== null => 'submitted',
            $isOverdue => 'overdue',
            default => 'pending',
        };

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
            ],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'deadline' => $assignment->deadline?->toIso8601String(),
                'max_score' => $assignment->max_score !== null ? (float) $assignment->max_score : null,
                'allowed_file_types' => $assignment->allowed_file_types ?? Assignment::DEFAULT_FILE_TYPES,
                'max_file_size_mb' => $assignment->max_file_size_mb ?? 10,
                'is_overdue' => (bool) $isOverdue,
                'status' => $status,
                'attachments' => $attachments,
            ],
            'submission' => $submission ? $this->mapSubmission($submission) : null,
            'activities' => $this->buildActivities($assignment, $submission),
        ];
    }

    /**
     * Bentuk timeline aktivitas dari timestamp yang sudah ada — tidak perlu audit log table.
     * Diurutkan descending (terbaru di atas) agar siap dirender langsung.
     *
     * @return array<int, array{id:string,title:string,description:string,occurred_at:string,variant:string}>
     */
    private function buildActivities(Assignment $assignment, ?AssignmentSubmission $submission): array
    {
        $items = [];

        $publishedAt = $assignment->available_from ?? $assignment->created_at;
        if ($publishedAt) {
            $items[] = [
                'id' => 'assignment-published',
                'title' => 'Tugas dipublikasikan',
                'description' => 'Guru memublikasikan tugas ini.',
                'occurred_at' => $publishedAt->toIso8601String(),
                'variant' => 'system',
            ];
        }

        if ($submission) {
            // "create" = saat row pertama di-insert. created_at immutable, tidak ter-touch oleh edit.
            if ($submission->created_at) {
                $items[] = [
                    'id' => 'submission-created',
                    'title' => 'Kamu mengumpulkan tugas',
                    'description' => 'Submission pertama dikumpulkan.',
                    'occurred_at' => $submission->created_at->toIso8601String(),
                    'variant' => 'create',
                ];
            }

            // "update" = field last_edited_at di-set oleh action SubmitStudentAssignment saat siswa edit.
            // Deterministik, tidak pakai heuristic threshold.
            if ($submission->last_edited_at) {
                $items[] = [
                    'id' => 'submission-updated',
                    'title' => 'Submission diperbarui',
                    'description' => 'Kamu memperbarui jawaban atau lampiran.',
                    'occurred_at' => $submission->last_edited_at->toIso8601String(),
                    'variant' => 'update',
                ];
            }

            if ($submission->graded_at && $submission->score !== null) {
                $items[] = [
                    'id' => 'submission-graded',
                    'title' => 'Dinilai oleh guru',
                    'description' => 'Nilai: '.rtrim(rtrim((string) $submission->score, '0'), '.').($assignment->max_score ? ' / '.rtrim(rtrim((string) $assignment->max_score, '0'), '.') : ''),
                    'occurred_at' => $submission->graded_at->toIso8601String(),
                    'variant' => 'grade',
                ];
            }
        }

        usort($items, fn ($a, $b) => Carbon::parse($b['occurred_at'])->getTimestamp() <=> Carbon::parse($a['occurred_at'])->getTimestamp());

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSubmission(AssignmentSubmission $submission): array
    {
        $files = $submission->getMedia('submission_files')->map(fn ($media) => [
            'id' => $media->uuid ?? (string) $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'extension' => pathinfo($media->file_name, PATHINFO_EXTENSION),
            'url' => $media->getUrl(),
        ])->values()->all();

        return [
            'id' => $submission->id,
            'content' => $submission->content,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'score' => $submission->score !== null ? (float) $submission->score : null,
            'feedback' => $submission->feedback,
            'files' => $files,
        ];
    }
}
