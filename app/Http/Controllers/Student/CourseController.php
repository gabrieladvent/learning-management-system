<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentCourse;
use App\Actions\Student\SetCoursePin;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function show(string $course, GetStudentCourse $action): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Course/CourseDetail', $action->handle($student, $course));
    }

    public function pin(string $course, SetCoursePin $action): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $action->handle($student, $course, pinned: true);

        return back();
    }

    public function unpin(string $course, SetCoursePin $action): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $action->handle($student, $course, pinned: false);

        return back();
    }
}
