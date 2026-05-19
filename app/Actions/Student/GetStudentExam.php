<?php

namespace App\Actions\Student;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetStudentExam
{
    /**
     * Ambil detail exam (start screen + ringkasan submission/session siswa).
     * Validasi enrollment + visibility.
     *
     * Tidak mengembalikan questions[] — itu hanya tersedia setelah session dimulai
     * (lihat GetStudentExamSession). Untuk mode `submission`, kembalikan submission jika ada.
     *
     * @return array{
     *     course: array<string, mixed>,
     *     material: array<string, mixed>,
     *     exam: array<string, mixed>,
     *     session: ?array<string, mixed>,
     *     submission: ?array<string, mixed>,
     *     activities: array<int, mixed>,
     * }
     */
    public function handle(Student $student, string $materialId, string $examId): array
    {
        $material = $this->resolveMaterial($student, $materialId);
        $exam = $this->resolveExam($material, $examId);

        $course = $material->classroomSubject;
        $mode = $exam->mode->value;

        $session = $mode === 'online_quiz'
            ? $exam->sessions()->where('student_id', $student->id)->first()
            : null;

        $submission = $mode === 'submission'
            ? $exam->submissions()->where('student_id', $student->id)->first()
            : null;

        $status = $this->computeStatus($exam, $session, $submission);
        $questionsCount = $exam->questions()->count();

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
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'mode' => $mode,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'max_score' => $exam->max_score !== null ? (float) $exam->max_score : null,
                'questions_count' => $questionsCount,
                'shuffle_questions' => (bool) $exam->shuffle_questions,
                'allowed_file_types' => $exam->allowed_file_types ?? Exam::DEFAULT_FILE_TYPES,
                'max_file_size_mb' => $exam->max_file_size_mb ?? 10,
                'available_until' => $exam->available_until?->toIso8601String(),
                'status' => $status,
            ],
            'session' => $session ? $this->mapSession($session, $exam) : null,
            'submission' => $submission ? $this->mapSubmission($submission) : null,
            'activities' => $session
                ? $this->buildSessionActivities($session, $exam)
                : ($submission ? $this->buildSubmissionActivities($submission, $exam) : []),
        ];
    }

    /**
     * @return 'belum_mulai'|'in_progress'|'submitted'|'graded'
     */
    private function computeStatus(Exam $exam, ?ExamSession $session, ?ExamSubmission $submission): string
    {
        if ($exam->mode->value === 'online_quiz') {
            if (! $session) {
                return 'belum_mulai';
            }
            if (! $session->submitted_at) {
                return 'in_progress';
            }
            $hasPending = $session->answers()->whereNull('score')->exists();

            return $hasPending ? 'submitted' : 'graded';
        }

        if (! $submission) {
            return 'belum_mulai';
        }

        if ($submission->score !== null) {
            return 'graded';
        }

        return $submission->submitted_at ? 'submitted' : 'belum_mulai';
    }

    private function resolveMaterial(Student $student, string $materialId): Material
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

        return $material;
    }

    private function resolveExam(Material $material, string $examId): Exam
    {
        $exam = $material->exams()
            ->whereKey($examId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->first();

        if (! $exam) {
            throw new NotFoundHttpException('Ujian tidak ditemukan atau belum tersedia.');
        }

        return $exam;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSession(ExamSession $session, Exam $exam): array
    {
        $expiresAt = $session->started_at?->copy()->addMinutes($exam->duration_minutes);

        return [
            'id' => $session->id,
            'started_at' => $session->started_at?->toIso8601String(),
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
            'total_score' => $session->total_score !== null ? (float) $session->total_score : null,
            'answered_count' => (int) $session->answers()->whereNotNull('answer')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSubmission(ExamSubmission $submission): array
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
            'link_url' => $submission->link_url,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'score' => $submission->score !== null ? (float) $submission->score : null,
            'feedback' => $submission->feedback,
            'files' => $files,
        ];
    }

    /**
     * Timeline session ujian (online_quiz). Mirror struktur AssignmentActivityTimeline.
     *
     * @return array<int, array{id:string,title:string,description:string,occurred_at:string,variant:string}>
     */
    private function buildSessionActivities(ExamSession $session, Exam $exam): array
    {
        $items = [];

        $publishedAt = $exam->available_from ?? $exam->created_at;
        if ($publishedAt) {
            $items[] = [
                'id' => 'exam-published',
                'title' => 'Ujian dipublikasikan',
                'description' => 'Guru memublikasikan ujian ini.',
                'occurred_at' => $publishedAt->toIso8601String(),
                'variant' => 'system',
            ];
        }

        $activities = Activity::query()
            ->where('subject_type', $session->getMorphClass())
            ->where('subject_id', $session->getKey())
            ->orderBy('created_at')
            ->get();

        foreach ($activities as $activity) {
            $attrs = $activity->attribute_changes?->get('attributes') ?? [];

            if ($activity->event === 'created' || array_key_exists('started_at', $attrs)) {
                if (! empty(array_filter($items, fn ($i) => $i['variant'] === 'create'))) {
                    continue;
                }
                $items[] = [
                    'id' => 'activity-'.$activity->id,
                    'title' => 'Kamu memulai ujian',
                    'description' => 'Timer dimulai pada saat ini.',
                    'occurred_at' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'variant' => 'create',
                ];

                continue;
            }

            if (array_key_exists('submitted_at', $attrs) && $attrs['submitted_at'] !== null) {
                $items[] = [
                    'id' => 'activity-'.$activity->id,
                    'title' => 'Kamu menyelesaikan ujian',
                    'description' => 'Jawaban dikirim & diproses untuk penilaian.',
                    'occurred_at' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'variant' => 'update',
                ];

                continue;
            }

            if (array_key_exists('total_score', $attrs)) {
                $items[] = [
                    'id' => 'activity-'.$activity->id,
                    'title' => 'Nilai diperbarui',
                    'description' => 'Total skor sementara: '.rtrim(rtrim((string) $attrs['total_score'], '0'), '.'),
                    'occurred_at' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'variant' => 'grade',
                ];
            }
        }

        usort($items, fn ($a, $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        return $items;
    }

    /**
     * Timeline submission ujian (mode submission).
     *
     * @return array<int, array{id:string,title:string,description:string,occurred_at:string,variant:string}>
     */
    private function buildSubmissionActivities(ExamSubmission $submission, Exam $exam): array
    {
        $items = [];

        $publishedAt = $exam->available_from ?? $exam->created_at;
        if ($publishedAt) {
            $items[] = [
                'id' => 'exam-published',
                'title' => 'Ujian dipublikasikan',
                'description' => 'Guru memublikasikan ujian ini.',
                'occurred_at' => $publishedAt->toIso8601String(),
                'variant' => 'system',
            ];
        }

        $activities = Activity::query()
            ->where('subject_type', $submission->getMorphClass())
            ->where('subject_id', $submission->getKey())
            ->orderBy('created_at')
            ->get();

        foreach ($activities as $activity) {
            $attrs = $activity->attribute_changes?->get('attributes') ?? [];
            $scoreChanged = is_array($attrs) && array_key_exists('score', $attrs) && $attrs['score'] !== null;

            if ($activity->event === 'created') {
                $items[] = [
                    'id' => 'activity-'.$activity->id,
                    'title' => 'Kamu mengumpulkan ujian',
                    'description' => 'Jawaban pertama dikirim.',
                    'occurred_at' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'variant' => 'create',
                ];

                continue;
            }

            if ($scoreChanged) {
                $max = $exam->max_score;
                $score = $attrs['score'];
                $items[] = [
                    'id' => 'activity-'.$activity->id,
                    'title' => 'Dinilai oleh guru',
                    'description' => 'Nilai: '.rtrim(rtrim((string) $score, '0'), '.').($max ? ' / '.rtrim(rtrim((string) $max, '0'), '.') : ''),
                    'occurred_at' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'variant' => 'grade',
                ];

                continue;
            }

            $items[] = [
                'id' => 'activity-'.$activity->id,
                'title' => 'Submission diperbarui',
                'description' => 'Kamu memperbarui jawaban atau lampiran.',
                'occurred_at' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'variant' => 'update',
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        return $items;
    }
}
