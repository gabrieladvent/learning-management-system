<?php

namespace App\Actions\Student;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StartExamSession
{
    /**
     * Buka session ujian baru atau resume yang sudah ada (idempoten).
     *
     * Aturan:
     *  - Hanya untuk exam mode `online_quiz`.
     *  - Validasi: enrolled, exam visible, exam published, sudah lewat starts_at.
     *  - Jika session sudah ada & submitted_at terisi → return apa adanya (siswa
     *    nantinya akan diarahkan ke result page oleh controller).
     *  - Jika session sudah ada tapi belum submit → resume (jangan reset started_at).
     *  - Race condition di-handle oleh unique(exam_id, student_id) di DB.
     */
    public function handle(Student $student, string $materialId, string $examId): ExamSession
    {
        $material = $this->resolveMaterial($student, $materialId);
        $exam = $this->resolveExam($material, $examId);

        if ($exam->mode->value !== 'online_quiz') {
            throw ValidationException::withMessages([
                'mode' => 'Ujian ini bukan kuis online — tidak perlu memulai session.',
            ]);
        }

        if ($exam->starts_at && now()->lessThan($exam->starts_at)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Ujian belum dimulai. Cek kembali waktu mulai.',
            ]);
        }

        return DB::transaction(function () use ($exam, $student) {
            $session = ExamSession::query()
                ->where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($session) {
                return $session;
            }

            return ExamSession::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'started_at' => now(),
            ]);
        });
    }

    private function resolveMaterial(Student $student, string $materialId): Material
    {
        $material = Material::query()
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
}
