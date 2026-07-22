<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\AuthController as StudentAuthController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\MaterialController as StudentMaterialController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ProgressController as StudentProgressController;
use App\Http\Middleware\EnsureStudentActive;
use App\Http\Middleware\EnsureStudentPasswordChanged;
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

    Route::middleware(['auth:student', 'throttle:60,1', EnsureStudentActive::class, EnsureStudentPasswordChanged::class])->group(function () {
        Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('courses/{course}', [StudentCourseController::class, 'show'])->name('courses.show');
        Route::post('courses/{course}/pin', [StudentCourseController::class, 'pin'])->name('courses.pin');
        Route::delete('courses/{course}/pin', [StudentCourseController::class, 'unpin'])->name('courses.unpin');
        Route::get('courses/{course}/materials/{material}', [StudentMaterialController::class, 'show'])->name('materials.show');
        Route::get('materials/{material}/assignments/{assignment}', [StudentAssignmentController::class, 'show'])->name('assignments.show');
        Route::post('materials/{material}/assignments/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->name('assignments.submit');
        // Download file tugas via disk PRIVAT (berautorisasi) — bukan URL publik.
        Route::get('materials/{material}/assignments/{assignment}/attachments/{media}/download', [StudentAssignmentController::class, 'downloadAttachment'])->name('assignments.attachments.download');
        Route::get('materials/{material}/assignments/{assignment}/submission-files/{media}/download', [StudentAssignmentController::class, 'downloadSubmissionFile'])->name('assignments.submission-files.download');

        // Ujian (Phase 4)
        Route::get('materials/{material}/exams/{exam}', [StudentExamController::class, 'show'])->name('exams.show');
        Route::post('materials/{material}/exams/{exam}/start', [StudentExamController::class, 'start'])->name('exams.start');
        Route::post('materials/{material}/exams/{exam}/submit-submission', [StudentExamController::class, 'submitSubmission'])->name('exams.submission.submit');
        Route::get('materials/{material}/exams/{exam}/submission-files/{media}/download', [StudentExamController::class, 'downloadSubmissionFile'])->name('exams.submission-files.download');
        Route::get('exams/sessions/{session}', [StudentExamController::class, 'take'])->name('exams.take');
        Route::post('exams/sessions/{session}/submit', [StudentExamController::class, 'submit'])->name('exams.submit');
        Route::get('exams/sessions/{session}/result', [StudentExamController::class, 'result'])->name('exams.result');
        Route::get('exams/sessions/{session}/questions/{media}/download', [StudentExamController::class, 'downloadQuestionFile'])->name('exams.questions.download');

        // Profil siswa: ganti password, upload foto, lihat progress.
        Route::get('profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile/password', [StudentProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('profile/photo', [StudentProfileController::class, 'updatePhoto'])->name('profile.photo');

        Route::get('notifications', [StudentNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [StudentNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [StudentNotificationController::class, 'markRead'])->name('notifications.read');

        Route::post('logout', [StudentAuthController::class, 'logout'])->name('logout');
    });

    // Auto-save jawaban ujian — throttle lebih longgar karena bisa fire tiap beberapa detik.
    Route::middleware(['auth:student', 'throttle:120,1', EnsureStudentActive::class])->group(function () {
        Route::post('exams/sessions/{session}/answer', [StudentExamController::class, 'answer'])->name('exams.answer');

        // Heartbeat tracking pembelajaran (Phase 1 learning-progress-tracking).
        Route::post('progress/heartbeat', [StudentProgressController::class, 'heartbeat'])->name('progress.heartbeat');
        Route::post('progress/disclosure-seen', [StudentProgressController::class, 'dismissDisclosure'])->name('progress.disclosure-seen');

        // Download file material — track sebagai proxy completion untuk material type=file (§7.1).
        Route::get('materials/{material}/files/{media}/download', [StudentMaterialController::class, 'downloadFile'])->name('materials.files.download');
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
