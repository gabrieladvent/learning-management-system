<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentAssignment;
use App\Actions\Student\ResolveVisibleAssignment;
use App\Actions\Student\SubmitStudentAssignment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ServesGuardedMedia;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssignmentController extends Controller
{
    use ServesGuardedMedia;

    public function show(string $material, string $assignment, GetStudentAssignment $action): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Assignment/AssignmentDetail', $action->handle($student, $material, $assignment));
    }

    public function submit(
        Request $request,
        string $material,
        string $assignment,
        SubmitStudentAssignment $action,
    ): RedirectResponse {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
            'removed_file_ids' => ['nullable', 'array'],
            'removed_file_ids.*' => ['string'],
        ]);

        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $action->handle(
            student: $student,
            materialId: $material,
            assignmentId: $assignment,
            content: $data['content'] ?? null,
            linkUrl: $data['link_url'] ?? null,
            newFiles: $data['files'] ?? [],
            removedFileIds: $data['removed_file_ids'] ?? [],
        );

        return redirect()
            ->route('student.assignments.show', ['material' => $material, 'assignment' => $assignment])
            ->with('success', 'Tugas berhasil dikumpulkan.');
    }

    /**
     * Download lampiran tugas (file dari guru). Otorisasi: siswa terdaftar di
     * kelas + tugas published & dalam window ketersediaan.
     */
    public function downloadAttachment(
        string $material,
        string $assignment,
        string $media,
        ResolveVisibleAssignment $resolve,
    ): BinaryFileResponse {
        $assignmentModel = $resolve->handle($this->student(), $material, $assignment);

        return $this->streamMediaFromCollection($assignmentModel, 'assignment_attachments', $media);
    }

    /**
     * Download file jawaban tugas. Otorisasi lewat kepemilikan submission.
     */
    public function downloadSubmissionFile(string $material, string $assignment, string $media): BinaryFileResponse
    {
        $student = $this->student();

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment)
            ->where('student_id', $student->id)
            ->first();

        if (! $submission) {
            throw new NotFoundHttpException('Pengumpulan tugas tidak ditemukan.');
        }

        return $this->streamMediaFromCollection($submission, 'submission_files', $media);
    }

    private function student(): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return $student;
    }
}
