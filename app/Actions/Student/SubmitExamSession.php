<?php

namespace App\Actions\Student;

use App\Models\ExamSession;
use App\Models\Student;
use App\Notifications\TeacherSubmissionAlert;
use App\Services\ExamGrader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubmitExamSession
{
    public function __construct(private ExamGrader $grader) {}

    /**
     * Finalize session ujian (online_quiz). Set submitted_at, lalu auto-grade.
     *
     * Idempoten — kalau sudah submit, langsung return session apa adanya.
     *
     * Sumber kebenaran waktu adalah server. Action ini bisa dipicu oleh:
     *  - Tombol "Selesaikan Ujian"
     *  - Auto-submit dari frontend saat timer habis
     *  - (Future) job/recovery saat user menutup tab di akhir waktu — TBD
     */
    public function handle(Student $student, string $sessionId): ExamSession
    {
        /** @var ?ExamSession $session */
        $session = ExamSession::query()
            ->with(['exam.questions'])
            ->whereKey($sessionId)
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->first();

        if (! $session) {
            throw new NotFoundHttpException('Session ujian tidak ditemukan.');
        }

        if ($session->submitted_at) {
            return $session;
        }

        if (! $session->started_at) {
            throw ValidationException::withMessages([
                'session' => 'Session belum dimulai, tidak bisa di-submit.',
            ]);
        }

        $graded = DB::transaction(function () use ($session) {
            $session->submitted_at = now();
            $session->save();

            return $this->grader->grade($session);
        });

        TeacherSubmissionAlert::forExamSession($graded->load('exam.material.classroomSubject.teacher', 'student'));

        return $graded;
    }
}
