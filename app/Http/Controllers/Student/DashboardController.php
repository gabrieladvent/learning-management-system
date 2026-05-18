<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentDashboard;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(GetStudentDashboard $action): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Dashboard/Dashboard', $action->handle($student));
    }
}
