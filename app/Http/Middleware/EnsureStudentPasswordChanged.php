<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa siswa mengganti password default (birth_date) saat pertama kali login.
 *
 * Siswa dibuat dengan `password_changed_at = null`. Selama masih null, mereka
 * diarahkan ke halaman profil (form ganti password) dan tidak bisa mengakses
 * materi/ujian dulu. Ini menutup risiko password default yang mudah ditebak.
 *
 * Whitelist: halaman profil, endpoint ganti password, dan logout.
 */
class EnsureStudentPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        if ($student && $student->user && $student->user->password_changed_at === null) {
            $allowed = $request->routeIs(
                'student.profile.edit',
                'student.profile.password',
                'student.logout',
            );

            if (! $allowed) {
                if ($request->expectsJson()) {
                    abort(403, 'Ganti password default Anda terlebih dahulu.');
                }

                return redirect()
                    ->route('student.profile.edit')
                    ->with('warning', 'Demi keamanan, ganti password default Anda sebelum melanjutkan.');
            }
        }

        return $next($request);
    }
}
