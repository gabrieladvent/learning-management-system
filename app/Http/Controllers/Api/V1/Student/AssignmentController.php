<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\GetStudentAssignment;
use App\Actions\Student\ResolveVisibleAssignment;
use App\Actions\Student\SubmitStudentAssignment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ServesGuardedMedia;
use App\Http\Responses\ApiResponse;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssignmentController extends Controller
{
    use ServesGuardedMedia;

    public function show(
        Request $request,
        string $material,
        string $assignment,
        GetStudentAssignment $action,
    ): JsonResponse {
        /** @var Student $student */
        $student = $request->user();

        return ApiResponse::success(
            $this->withDownloadPaths($action->handle($student, $material, $assignment), $material, $assignment)
        );
    }

    /**
     * Kumpulkan / perbarui jawaban tugas.
     *
     * Dilindungi middleware IdempotentRequest: percobaan ulang dari antrian
     * offline dengan kunci yang sama mengembalikan hasil yang sama, tidak
     * memproses ulang.
     */
    public function submit(
        Request $request,
        string $material,
        string $assignment,
        SubmitStudentAssignment $action,
        GetStudentAssignment $reload,
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
            assignmentId: $assignment,
            content: $data['content'] ?? null,
            linkUrl: $data['link_url'] ?? null,
            newFiles: $data['files'] ?? [],
            removedFileIds: $data['removed_file_ids'] ?? [],
        );

        // Dibaca ulang lewat Action yang sama dengan endpoint detail supaya
        // bentuk `submission` di respons submit identik dengan yang dilihat
        // klien saat memuat halaman — klien tidak perlu dua parser.
        $payload = $this->withDownloadPaths($reload->handle($student, $material, $assignment), $material, $assignment);

        return ApiResponse::success(
            ['submission' => $payload['submission']],
            'Tugas berhasil dikumpulkan.',
        );
    }

    /**
     * Lampiran dari guru. Otorisasi: tugas terlihat oleh siswa ini.
     */
    public function downloadAttachment(
        Request $request,
        string $material,
        string $assignment,
        string $media,
        ResolveVisibleAssignment $resolve,
    ): BinaryFileResponse {
        /** @var Student $student */
        $student = $request->user();

        return $this->streamMediaFromCollection(
            $resolve->handle($student, $material, $assignment),
            'assignment_attachments',
            $media,
        );
    }

    /**
     * File jawaban siswa. Otorisasi lewat kepemilikan submission — siswa hanya
     * bisa mengunduh berkasnya sendiri.
     */
    public function downloadSubmissionFile(
        Request $request,
        string $material,
        string $assignment,
        string $media,
    ): BinaryFileResponse {
        /** @var Student $student */
        $student = $request->user();

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment)
            ->where('student_id', $student->id)
            ->first();

        if (! $submission) {
            throw new NotFoundHttpException('Pengumpulan tugas tidak ditemukan.');
        }

        return $this->streamMediaFromCollection($submission, 'submission_files', $media);
    }

    /**
     * Menukar `url` (route web absolut) dengan `download_path` relatif ke API,
     * baik pada lampiran guru maupun berkas jawaban siswa.
     *
     * Route web berautentikasi sesi, jadi URL-nya tidak berguna bagi klien
     * token. Path relatif juga membuat aplikasi tidak menyimpan host di cache.
     * Lihat ADR-0003 dan ADR-0010.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withDownloadPaths(array $payload, string $materialId, string $assignmentId): array
    {
        $payload['assignment']['attachments'] = $this->mapFiles(
            $payload['assignment']['attachments'],
            'api.v1.assignments.attachments.download',
            $materialId,
            $assignmentId,
        );

        if (isset($payload['submission']) && is_array($payload['submission'])) {
            $payload['submission']['files'] = $this->mapFiles(
                $payload['submission']['files'],
                'api.v1.assignments.submission-files.download',
                $materialId,
                $assignmentId,
            );
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array<string, mixed>>
     */
    private function mapFiles(array $files, string $routeName, string $materialId, string $assignmentId): array
    {
        return array_map(function (array $file) use ($routeName, $materialId, $assignmentId) {
            unset($file['url']);

            $file['download_path'] = route($routeName, [
                'material' => $materialId,
                'assignment' => $assignmentId,
                'media' => $file['id'],
            ], absolute: false);

            return $file;
        }, $files);
    }
}
