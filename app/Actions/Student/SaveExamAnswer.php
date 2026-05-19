<?php

namespace App\Actions\Student;

use App\Models\ExamAnswer;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SaveExamAnswer
{
    /**
     * Simpan/upsert jawaban siswa untuk satu soal di session ujian.
     *
     * Idempoten — frontend memanggil ini tiap perubahan (debounced 1s).
     *
     * Aturan:
     *  - Session harus milik student
     *  - Session belum boleh submitted
     *  - Belum lewat expires_at (started_at + duration_minutes) — server time
     *  - Question harus milik exam dari session
     */
    public function handle(
        Student $student,
        string $sessionId,
        string $questionId,
        ?string $answer,
    ): ExamAnswer {
        /** @var ?ExamSession $session */
        $session = ExamSession::query()
            ->with('exam')
            ->whereKey($sessionId)
            ->where('student_id', $student->id)
            ->first();

        if (! $session) {
            throw new NotFoundHttpException('Session ujian tidak ditemukan.');
        }

        if ($session->submitted_at) {
            throw ValidationException::withMessages([
                'session' => 'Ujian sudah dikumpulkan, tidak bisa diubah lagi.',
            ]);
        }

        $expiresAt = $session->started_at?->copy()->addMinutes($session->exam->duration_minutes);
        if ($expiresAt && now()->greaterThan($expiresAt->copy()->addSeconds(15))) {
            // Toleransi +15 detik supaya request "save terakhir" sebelum auto-submit
            // tetap diterima walau clock client agak ngegeser.
            throw ValidationException::withMessages([
                'session' => 'Waktu ujian sudah habis.',
            ]);
        }

        $belongsToExam = $session->exam->questions()->whereKey($questionId)->exists();
        if (! $belongsToExam) {
            throw new NotFoundHttpException('Soal tidak ditemukan di ujian ini.');
        }

        /** @var ExamAnswer $row */
        $row = ExamAnswer::updateOrCreate(
            ['exam_session_id' => $session->id, 'exam_question_id' => $questionId],
            ['answer' => $answer],
        );

        return $row;
    }
}
