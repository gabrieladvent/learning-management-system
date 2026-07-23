<?php

namespace App\Actions\Student;

use App\Models\ExamSession;
use App\Models\Student;
use App\Notifications\TeacherSubmissionAlert;
use App\Services\ExamGrader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->whereKey($sessionId)
            ->where('student_id', $student->id)
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

        // Finalize submitted_at secara atomik dengan lock SINGKAT. Grading (bagian
        // mahal: 1 update per jawaban) sengaja TIDAK di dalam lock ini supaya lock
        // session cepat dilepas — mengurangi kontensi saat sekelas submit bersamaan
        // di akhir waktu ujian (H5). Re-check submitted_at di dalam lock menjaga
        // idempotensi terhadap submit paralel / auto-submit.
        $justSubmitted = false;

        $session = DB::transaction(function () use ($sessionId, $student, &$justSubmitted) {
            /** @var ?ExamSession $locked */
            $locked = ExamSession::query()
                ->whereKey($sessionId)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($locked && ! $locked->submitted_at && $locked->started_at) {
                $locked->submitted_at = now();
                $locked->save();
                $justSubmitted = true;
            }

            return $locked;
        });

        // Sudah disubmit oleh proses lain (submit paralel / auto-submit) di antara
        // cek awal dan lock — jangan grade/alert ulang.
        if (! $justSubmitted) {
            return $session;
        }

        // Grading di LUAR lock.
        $graded = $this->grader->grade($session->load('exam.questions'));

        Log::info('Exam session disubmit & digrade', [
            'session_id' => $graded->id,
            'exam_id' => $graded->exam_id,
            'student_id' => $student->id,
            'total_score' => $graded->total_score,
        ]);

        TeacherSubmissionAlert::forExamSession($graded->load('exam.material.classroomSubject.teacher', 'student'));

        return $graded;
    }
}
