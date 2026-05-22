<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\AuthController as StudentAuthController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\MaterialController as StudentMaterialController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::guard('student')->check()
        ? redirect()->route('student.dashboard')
        : redirect()->route('student.login');
});

Route::prefix('student')->name('student.')->group(function () {
    Route::get('login', [StudentAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [StudentAuthController::class, 'login'])->name('login.attempt');

    Route::middleware(['auth:student', 'throttle:60,1'])->group(function () {
        Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('courses/{course}', [StudentCourseController::class, 'show'])->name('courses.show');
        Route::post('courses/{course}/pin', [StudentCourseController::class, 'pin'])->name('courses.pin');
        Route::delete('courses/{course}/pin', [StudentCourseController::class, 'unpin'])->name('courses.unpin');
        Route::get('courses/{course}/materials/{material}', [StudentMaterialController::class, 'show'])->name('materials.show');
        Route::get('materials/{material}/assignments/{assignment}', [StudentAssignmentController::class, 'show'])->name('assignments.show');
        Route::post('materials/{material}/assignments/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->name('assignments.submit');

        // Ujian (Phase 4)
        Route::get('materials/{material}/exams/{exam}', [StudentExamController::class, 'show'])->name('exams.show');
        Route::post('materials/{material}/exams/{exam}/start', [StudentExamController::class, 'start'])->name('exams.start');
        Route::post('materials/{material}/exams/{exam}/submit-submission', [StudentExamController::class, 'submitSubmission'])->name('exams.submission.submit');
        Route::get('exams/sessions/{session}', [StudentExamController::class, 'take'])->name('exams.take');
        Route::post('exams/sessions/{session}/submit', [StudentExamController::class, 'submit'])->name('exams.submit');
        Route::get('exams/sessions/{session}/result', [StudentExamController::class, 'result'])->name('exams.result');

        Route::get('notifications', [StudentNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [StudentNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [StudentNotificationController::class, 'markRead'])->name('notifications.read');

        Route::post('logout', [StudentAuthController::class, 'logout'])->name('logout');
    });

    // Auto-save jawaban ujian — throttle lebih longgar karena bisa fire tiap beberapa detik.
    Route::middleware(['auth:student', 'throttle:120,1'])->group(function () {
        Route::post('exams/sessions/{session}/answer', [StudentExamController::class, 'answer'])->name('exams.answer');
    });
});

Route::get('/dashboard', function () {
    return redirect()->route('student.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
