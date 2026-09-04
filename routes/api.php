<?php

use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Middleware\Api\EnsureStudentActiveApi;
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
    });
});
