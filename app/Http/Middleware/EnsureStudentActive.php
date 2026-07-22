<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request enforcement `is_active` untuk guard siswa.
 *
 * AuthenticateStudent hanya mengecek is_active SAAT login. Tanpa middleware ini,
 * siswa (atau User terkait) yang di-nonaktifkan admin tetap punya sesi valid
 * sampai kedaluwarsa. Middleware ini men-logout & memblok mereka seketika.
 */
class EnsureStudentActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        // Blok bila record Student di-nonaktifkan, atau bila User terkait ada tapi
        // di-nonaktifkan. Student tanpa User tidak diblok di sini (mereka tidak bisa
        // login normal karena AuthenticateStudent butuh User + password).
        $inactive = $student
            && (! $student->is_active || ($student->user !== null && ! $student->user->is_active));

        if ($inactive) {
            Auth::guard('student')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Request JSON/axios (auto-save, heartbeat) → 401 supaya FE bisa handle.
            if ($request->expectsJson()) {
                abort(401, 'Akun tidak aktif.');
            }

            return redirect()
                ->route('student.login')
                ->withErrors(['nisn' => 'Akun Anda telah dinonaktifkan. Hubungi admin sekolah.']);
        }

        return $next($request);
    }
}
