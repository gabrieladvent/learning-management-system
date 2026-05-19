<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Student;
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
}
