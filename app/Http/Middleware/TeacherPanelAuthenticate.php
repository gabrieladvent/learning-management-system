<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Support\Facades\Auth;

/**
 * Wrapper Filament Authenticate dengan cross-guard awareness.
 *
 * Default behavior Filament: kalau guard `web` belum auth, redirect ke
 * Filament login. Tapi kalau yang sedang login adalah siswa (guard `student`),
 * lebih masuk akal arahkan kembali ke dashboard siswa daripada login Filament —
 * supaya siswa tidak nyangkut di halaman login guru yang mereka tidak punya akun.
 *
 * Override hanya pada `redirectTo()` (URL tujuan saat unauthenticated). Untuk
 * tetap intercept lebih cepat (sebelum AuthenticationException dilempar),
 * `authenticate()` juga mengembalikan redirect bila siswa terdeteksi.
 */
class TeacherPanelAuthenticate extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        if (Auth::guard('student')->check()) {
            return route('student.dashboard');
        }

        return parent::redirectTo($request);
    }
}
