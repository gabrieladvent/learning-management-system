<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentAssignment;
use App\Actions\Student\SubmitStudentAssignment;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
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
}
