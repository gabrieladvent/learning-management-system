<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\AuthController as StudentAuthController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
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

    Route::middleware('auth:student')->group(function () {
        Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::post('logout', [StudentAuthController::class, 'logout'])->name('logout');
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
