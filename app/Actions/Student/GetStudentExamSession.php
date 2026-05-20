<?php

namespace App\Actions\Student;

use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\Student;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetStudentExamSession
{
    /**
     * Ambil session ujian + soal + jawaban siswa untuk Halaman ExamTake.
     *
     * Soal di-shuffle (jika `exam.shuffle_questions = true`) dengan seed dari
     * session_id supaya urutan stabil antar request.
     *
     * @return array{
     *     session: array<string, mixed>,
     *     exam: array<string, mixed>,
     *     material: array<string, mixed>,
     *     course: array<string, mixed>,
     *     questions: array<int, array<string, mixed>>,
     *     answers: array<string, ?string>,
     *     server_time: string,
     * }
     */
    public function handle(Student $student, string $sessionId): array
    {
        /** @var ?ExamSession $session */
        $session = ExamSession::query()
            ->with([
                'exam.material.classroomSubject.classroom',
                'exam.material.classroomSubject.subject',
                'exam.material.classroomSubject.teacher',
                'exam.questions',
                'answers',
            ])
            ->whereKey($sessionId)
            ->where('student_id', $student->id)
            ->first();

        if (! $session) {
            throw new NotFoundHttpException('Session ujian tidak ditemukan.');
        }

        $exam = $session->exam;
        $material = $exam->material;
        $course = $material->classroomSubject;

        $questions = $exam->questions->sortBy('order')->values();

        if ($exam->shuffle_questions) {
            // Seed dari session id (digits) supaya urutan stabil per session,
            // tapi beda antar siswa.
            $seed = crc32($session->id);
            mt_srand($seed);
            $questions = $questions->shuffle()->values();
            mt_srand();
        }

        $questions = $questions->map(fn (ExamQuestion $q) => $this->mapQuestion($q))->all();
        $answers = $session->answers->mapWithKeys(fn ($a) => [$a->exam_question_id => $a->answer])->all();
        $expiresAt = $session->started_at?->copy()->addMinutes($exam->duration_minutes);

        return [
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at?->toIso8601String(),
                'submitted_at' => $session->submitted_at?->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
                'total_score' => $session->total_score !== null ? (float) $session->total_score : null,
            ],
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'mode' => $exam->mode->value,
                'duration_minutes' => $exam->duration_minutes,
                'max_score' => $exam->max_score !== null ? (float) $exam->max_score : null,
            ],
            'material' => [
                'id' => $material->id,
                'title' => $material->title,
                'topic' => $material->topic,
            ],
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject?->name,
                'classroom_name' => $course->classroom?->name,
                'teacher_name' => $course->teacher?->full_name,
            ],
            'questions' => $questions,
            'answers' => $answers,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapQuestion(ExamQuestion $question): array
    {
        $files = $question->getMedia('question_files')->map(fn (Media $media) => [
            'id' => $media->uuid ?? (string) $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'extension' => pathinfo($media->file_name, PATHINFO_EXTENSION),
            'url' => $media->getUrl(),
        ])->values()->all();

        return [
            'id' => $question->id,
            'type' => $question->type->value,
            'question' => $question->question,
            'options' => $question->options ?? [],
            'score' => (float) $question->score,
            'files' => $files,
        ];
    }
}
