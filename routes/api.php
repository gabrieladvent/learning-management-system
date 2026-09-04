<?php

use App\Http\Controllers\Api\V1\Student\AssignmentController;
use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Controllers\Api\V1\Student\CourseController;
use App\Http\Controllers\Api\V1\Student\DashboardController;
use App\Http\Controllers\Api\V1\Student\ExamController;
use App\Http\Controllers\Api\V1\Student\MaterialController;
use App\Http\Middleware\Api\EnsureStudentActiveApi;
use App\Http\Middleware\Api\EnsureStudentPasswordChangedApi;
use App\Http\Middleware\Api\IdempotentRequest;
use App\Http\Middleware\Api\RefreshTokenExpiry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API siswa (mobile)
|--------------------------------------------------------------------------
|
| Versi di path (`/api/v1`) — lihat ADR-0013 di repo learn_mobile. Seluruh
| response memakai envelope App\Http\Responses\ApiResponse (ADR-0016).
|
| Guard `student-api` (driver sanctum, provider students). TIDAK memakai sesi.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('auth.login');

    Route::middleware(['auth:student-api', EnsureStudentActiveApi::class, RefreshTokenExpiry::class])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::middleware(EnsureStudentPasswordChangedApi::class)->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('todo', [DashboardController::class, 'todo'])->name('todo');

            Route::post('courses/{course}/pin', [CourseController::class, 'pin'])->name('courses.pin');
            Route::delete('courses/{course}/pin', [CourseController::class, 'unpin'])->name('courses.unpin');

            Route::get('courses/{course}', [MaterialController::class, 'course'])->name('courses.show');
            Route::get('courses/{course}/materials/{material}', [MaterialController::class, 'show'])->name('materials.show');
            Route::get('materials/{material}/files/{media}/download', [MaterialController::class, 'download'])->name('materials.files.download');

            // Tugas
            Route::get('materials/{material}/assignments/{assignment}', [AssignmentController::class, 'show'])
                ->name('assignments.show');
            Route::post('materials/{material}/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])
                ->middleware(IdempotentRequest::class)
                ->name('assignments.submit');
            Route::get('materials/{material}/assignments/{assignment}/attachments/{media}/download', [AssignmentController::class, 'downloadAttachment'])
                ->name('assignments.attachments.download');
            Route::get('materials/{material}/assignments/{assignment}/submission-files/{media}/download', [AssignmentController::class, 'downloadSubmissionFile'])
                ->name('assignments.submission-files.download');

            // Ujian
            Route::get('materials/{material}/exams/{exam}', [ExamController::class, 'show'])
                ->name('exams.show');
            Route::post('materials/{material}/exams/{exam}/start', [ExamController::class, 'start'])
                ->name('exams.start');
            Route::post('materials/{material}/exams/{exam}/submit-submission', [ExamController::class, 'submitSubmission'])
                ->middleware(IdempotentRequest::class)
                ->name('exams.submission.submit');
            Route::get('materials/{material}/exams/{exam}/submission-files/{media}/download', [ExamController::class, 'downloadSubmissionFile'])
                ->name('exams.submission-files.download');

            Route::get('exams/sessions/{session}', [ExamController::class, 'session'])
                ->name('exams.session');
            Route::post('exams/sessions/{session}/submit', [ExamController::class, 'submit'])
                ->name('exams.submit');
            Route::get('exams/sessions/{session}/result', [ExamController::class, 'result'])
                ->name('exams.result');
            Route::get('exams/sessions/{session}/questions/{media}/download', [ExamController::class, 'downloadQuestionFile'])
                ->name('exams.questions.download');

            // Auto-save jawaban: throttle lebih longgar karena bisa memicu tiap
            // beberapa detik selama ujian berlangsung.
            Route::post('exams/sessions/{session}/answer', [ExamController::class, 'answer'])
                ->middleware('throttle:120,1')
                ->name('exams.answer');
        });
    });
});
