<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentMaterial;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function show(string $course, string $material, GetStudentMaterial $action): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Material/MaterialDetail', $action->handle($student, $course, $material));
    }
}
