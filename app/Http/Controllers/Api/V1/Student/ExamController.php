<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\GetStudentExam;
use App\Actions\Student\GetStudentExamSession;
use App\Actions\Student\ResolveStudentExamQuestionFile;
use App\Actions\Student\SaveExamAnswer;
use App\Actions\Student\StartExamSession;
use App\Actions\Student\SubmitExamSession;
use App\Actions\Student\SubmitExamSubmission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ServesGuardedMedia;
use App\Http\Responses\ApiResponse;
use App\Models\ExamSubmission;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExamController extends Controller
{
    use ServesGuardedMedia;

    /**
     * Detail ujian. `exam.mode` menentukan alur klien:
     * `online_quiz` → layar pengarahan lalu start; `submission` → form unggah.
     */
    public function show(Request $request, string $material, string $exam, GetStudentExam $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return ApiResponse::success(
            $this->withSubmissionDownloadPaths($action->handle($student, $material, $exam), $material, $exam)
        );
    }

    /**
     * Buka atau lanjutkan sesi kuis.
     *
     * Idempoten dan TIDAK me-reset `started_at`: menekan "mulai" berkali-kali
     * tidak memberi siswa waktu tambahan. Sifat ini wajib dipertahankan.
     */
    public function start(Request $request, string $material, string $exam, StartExamSession $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $session = $action->handle($student, $material, $exam);

        return ApiResponse::success(['session_id' => $session->id], 'Sesi ujian siap.');
    }

    /**
     * Sesi ujian: soal, jawaban tersimpan, dan acuan waktu.
     *
     * Payload membawa `session.expires_at` dan `server_time`. Klien menghitung
     * sisa waktu dari selisih KEDUANYA, lalu berhitung mundur dengan jam
     * monotonik — bukan jam dinding perangkat, yang bisa diubah siswa.
     */
    public function session(Request $request, string $session, GetStudentExamSession $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return ApiResponse::success(
            $this->withQuestionDownloadPaths($action->handle($student, $session), $session)
        );
    }

    /**
     * Auto-save satu jawaban. Aman dipanggil berulang untuk soal yang sama.
     */
    public function answer(Request $request, string $session, SaveExamAnswer $action): JsonResponse
    {
        $data = $request->validate([
            'exam_question_id' => ['required', 'string'],
            'answer' => ['nullable', 'string', 'max:50000'],
        ]);

        /** @var Student $student */
        $student = $request->user();

        $answer = $action->handle($student, $session, $data['exam_question_id'], $data['answer'] ?? null);

        return ApiResponse::success([
            'exam_question_id' => $answer->exam_question_id,
            'saved_at' => $answer->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Selesaikan sesi kuis.
     *
     * Sengaja mengembalikan 200 walau sesi SUDAH tersubmit, bukan 409. Klien
     * mobile mengulang kiriman saat responsnya hilang di jaringan buruk; kalau
     * pengulangan itu dibalas error, siswa melihat layar merah untuk ujian yang
     * sebenarnya sudah aman terkumpul. Action-nya memang sudah idempoten.
     */
    public function submit(Request $request, string $session, SubmitExamSession $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $finished = $action->handle($student, $session);

        return ApiResponse::success([
            'session_id' => $finished->id,
            'submitted_at' => $finished->submitted_at?->toIso8601String(),
        ], 'Ujian berhasil dikumpulkan.');
    }

    /**
     * Hasil ujian. Skor tetap disaring oleh Action sampai `results_released_at`
     * lewat — klien memakai flag `results_released` untuk memilih tampilan.
     */
    public function result(Request $request, string $session, GetStudentExamSession $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return ApiResponse::success(
            $this->withQuestionDownloadPaths($action->handle($student, $session), $session)
        );
    }

    /**
     * Kumpulkan berkas jawaban untuk ujian mode `submission`.
     *
     * Dilindungi IdempotentRequest, sama seperti pengumpulan tugas.
     */
    public function submitSubmission(
        Request $request,
        string $material,
        string $exam,
        SubmitExamSubmission $action,
        GetStudentExam $reload,
    ): JsonResponse {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
            'removed_file_ids' => ['nullable', 'array'],
            'removed_file_ids.*' => ['string'],
        ]);

        /** @var Student $student */
        $student = $request->user();

        $action->handle(
            student: $student,
            materialId: $material,
            examId: $exam,
            content: $data['content'] ?? null,
            linkUrl: $data['link_url'] ?? null,
            newFiles: $data['files'] ?? [],
            removedFileIds: $data['removed_file_ids'] ?? [],
        );

        $payload = $this->withSubmissionDownloadPaths($reload->handle($student, $material, $exam), $material, $exam);

        return ApiResponse::success(['submission' => $payload['submission']], 'Ujian berhasil dikumpulkan.');
    }

    public function downloadQuestionFile(
        Request $request,
        string $session,
        string $media,
        ResolveStudentExamQuestionFile $resolve,
    ): BinaryFileResponse {
        /** @var Student $student */
        $student = $request->user();

        return $this->streamMediaFromCollection(
            $resolve->handle($student, $session, $media),
            'question_files',
            $media,
        );
    }

    public function downloadSubmissionFile(
        Request $request,
        string $material,
        string $exam,
        string $media,
    ): BinaryFileResponse {
        /** @var Student $student */
        $student = $request->user();

        $submission = ExamSubmission::query()
            ->where('exam_id', $exam)
            ->where('student_id', $student->id)
            ->first();

        if (! $submission) {
            throw new NotFoundHttpException('Pengumpulan ujian tidak ditemukan.');
        }

        return $this->streamMediaFromCollection($submission, 'submission_files', $media);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withSubmissionDownloadPaths(array $payload, string $materialId, string $examId): array
    {
        if (isset($payload['submission']) && is_array($payload['submission'])) {
            $payload['submission']['files'] = array_map(
                fn (array $file) => $this->swapUrl($file, 'api.v1.exams.submission-files.download', [
                    'material' => $materialId,
                    'exam' => $examId,
                    'media' => $file['id'],
                ]),
                $payload['submission']['files'],
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withQuestionDownloadPaths(array $payload, string $sessionId): array
    {
        $payload['questions'] = array_map(function (array $question) use ($sessionId) {
            $question['files'] = array_map(
                fn (array $file) => $this->swapUrl($file, 'api.v1.exams.questions.download', [
                    'session' => $sessionId,
                    'media' => $file['id'],
                ]),
                $question['files'],
            );

            return $question;
        }, $payload['questions']);

        return $payload;
    }

    /**
     * Menukar `url` (route web absolut) dengan `download_path` relatif ke API.
     * Route web berautentikasi sesi, jadi URL-nya tidak berguna bagi klien token.
     *
     * @param  array<string, mixed>  $file
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function swapUrl(array $file, string $routeName, array $params): array
    {
        unset($file['url']);

        $file['download_path'] = route($routeName, $params, absolute: false);

        return $file;
    }
}
