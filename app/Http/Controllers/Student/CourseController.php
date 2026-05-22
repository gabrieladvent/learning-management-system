<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CourseController extends Controller
{
    public function show(string $course, GetStudentCourse $action): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Course/CourseDetail', $action->handle($student, $course));
    }

    public function pin(string $course): RedirectResponse
    {
        $student = $this->studentWithCourseGuard($course);

        $student->pinnedClassroomSubjects()->syncWithoutDetaching([
            $course => ['pinned_at' => now()],
        ]);

        return back();
    }

    public function unpin(string $course): RedirectResponse
    {
        $student = $this->studentWithCourseGuard($course);

        $student->pinnedClassroomSubjects()->detach($course);

        return back();
    }

    private function studentWithCourseGuard(string $course): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $belongs = $student->classrooms()
            ->whereHas('classroomSubjects', fn ($q) => $q->whereKey($course))
            ->exists();

        if (! $belongs) {
            throw new NotFoundHttpException('Mata pelajaran tidak ditemukan.');
        }

        return $student;
    }
}
