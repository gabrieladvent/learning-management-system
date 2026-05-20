<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentExam;
use App\Actions\Student\GetStudentExamSession;
use App\Actions\Student\SaveExamAnswer;
use App\Actions\Student\StartExamSession;
use App\Actions\Student\SubmitExamSession;
use App\Actions\Student\SubmitExamSubmission;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    /**
     * Start screen exam (sebelum klik Mulai).
     * Untuk mode `online_quiz`: tampilkan info exam + tombol mulai/lanjutkan/lihat hasil.
     * Untuk mode `submission`: tampilkan form pengumpulan + submission lama (kalau ada).
     *
     * Optimasi UX: kalau siswa sudah submit session online_quiz, langsung antar
     * ke halaman hasil — jangan tampilkan start screen yang isinya tombol
     * "Lihat Hasil" lagi. Akses langsung lewat URL ditolak supaya konsisten.
     */
    public function show(string $material, string $exam, GetStudentExam $action): Response|RedirectResponse
    {
        $student = $this->student();
        $payload = $action->handle($student, $material, $exam);

        if ($payload['exam']['mode'] === 'online_quiz'
            && $payload['session']
            && $payload['session']['submitted_at']
        ) {
            return redirect()->route('student.exams.result', ['session' => $payload['session']['id']]);
        }

        $page = $payload['exam']['mode'] === 'online_quiz' ? 'Exam/ExamStart' : 'Exam/ExamSubmissionForm';

        return Inertia::render($page, $payload);
    }

    /**
     * POST start — buka/resume session ujian (online_quiz).
     */
    public function start(string $material, string $exam, StartExamSession $action): RedirectResponse
    {
        $student = $this->student();
        $session = $action->handle($student, $material, $exam);

        return redirect()->route('student.exams.take', ['session' => $session->id]);
    }

    /**
     * Halaman pengerjaan exam. Idempoten: kalau session sudah submitted, alihkan ke result.
     */
    public function take(string $session, GetStudentExamSession $action): Response|RedirectResponse
    {
        $student = $this->student();
        $payload = $action->handle($student, $session);

        if ($payload['session']['submitted_at']) {
            return redirect()->route('student.exams.result', ['session' => $session]);
        }

        return Inertia::render('Exam/ExamTake', $payload);
    }

    /**
     * Auto-save jawaban (debounced di FE). Return JSON karena dipanggil via axios.
     */
    public function answer(Request $request, string $session, SaveExamAnswer $action): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'string'],
            'answer' => ['nullable', 'string', 'max:50000'],
        ]);

        $student = $this->student();
        $answer = $action->handle($student, $session, $data['question_id'], $data['answer'] ?? null);

        return response()->json([
            'ok' => true,
            'question_id' => $answer->exam_question_id,
            'saved_at' => $answer->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Submit session (selesaikan ujian) — finalize + auto-grade.
     */
    public function submit(string $session, SubmitExamSession $action): RedirectResponse
    {
        $student = $this->student();
        $action->handle($student, $session);

        return redirect()
            ->route('student.exams.result', ['session' => $session])
            ->with('success', 'Ujian berhasil dikumpulkan.');
    }

    /**
     * Halaman hasil exam (online_quiz). Resp sama bentuknya dengan ExamStart payload.
     */
    public function result(string $session, GetStudentExamSession $action): Response
    {
        $student = $this->student();
        $payload = $action->handle($student, $session);

        return Inertia::render('Exam/ExamResult', $payload);
    }

    /**
     * Submit untuk mode submission (kumpul text + file + link).
     */
    public function submitSubmission(
        Request $request,
        string $material,
        string $exam,
        SubmitExamSubmission $action,
    ): RedirectResponse {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
            'removed_file_ids' => ['nullable', 'array'],
            'removed_file_ids.*' => ['string'],
        ]);

        $student = $this->student();
        $action->handle(
            student: $student,
            materialId: $material,
            examId: $exam,
            content: $data['content'] ?? null,
            linkUrl: $data['link_url'] ?? null,
            newFiles: $data['files'] ?? [],
            removedFileIds: $data['removed_file_ids'] ?? [],
        );

        return redirect()
            ->route('student.exams.show', ['material' => $material, 'exam' => $exam])
            ->with('success', 'Ujian berhasil dikumpulkan.');
    }

    private function student(): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return $student;
    }
}
