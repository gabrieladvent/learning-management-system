<?php

namespace App\Actions\Student;

use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\Student;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cari soal pemilik sebuah file lampiran ujian, lewat sesi milik siswa sendiri.
 *
 * Otorisasinya bertumpu pada kepemilikan sesi: siswa hanya bisa mengunduh file
 * soal dari ujian yang sesinya memang miliknya. Diekstrak dari
 * Student\ExamController supaya web dan API tidak punya dua salinan aturan ini —
 * kalau salah satu tertinggal saat aturan berubah, berkas soal ujian bisa
 * terbuka ke siswa yang tidak berhak.
 */
class ResolveStudentExamQuestionFile
{
    /**
     * @throws NotFoundHttpException jika sesi atau file tidak ada / bukan miliknya
     */
    public function handle(Student $student, string $sessionId, string $mediaId): ExamQuestion
    {
        /** @var ?ExamSession $session */
        $session = ExamSession::query()
            ->whereKey($sessionId)
            ->where('student_id', $student->id)
            ->with('exam.questions')
            ->first();

        if (! $session || ! $session->exam) {
            throw new NotFoundHttpException('Session ujian tidak ditemukan.');
        }

        $question = $session->exam->questions->first(
            fn (ExamQuestion $q) => $q->getMedia('question_files')
                ->contains(fn ($m) => (string) $m->id === $mediaId || $m->uuid === $mediaId)
        );

        if (! $question) {
            throw new NotFoundHttpException('File tidak ditemukan.');
        }

        return $question;
    }
}
