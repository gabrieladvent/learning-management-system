<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cegah user web (guard `web` — guru/admin) membuka area siswa `/student/*`.
 *
 * Sisi sebaliknya (siswa membuka `/teacher/*`) di-handle oleh
 * App\Http\Middleware\TeacherPanelAuthenticate yang meng-override
 * Filament\Http\Middleware\Authenticate. Pendekatan ini perlu karena Filament
 * panel pakai stack middleware-nya sendiri, dan Filament's Authenticate runs
 * sebelum middleware umum sempat intercept.
 */
class RedirectIfWrongGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $isStudentRoute = $request->is('student') || $request->is('student/*');
        $isAuthEndpoint = $request->is('*/login') || $request->is('*/logout');

        if ($isStudentRoute && ! $isAuthEndpoint && Auth::guard('web')->check()) {
            return redirect('/teacher');
        }

        return $next($request);
    }
}
