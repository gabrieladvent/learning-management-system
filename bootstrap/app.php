<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfWrongGuard;
use App\Http\Responses\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // RedirectIfWrongGuard di-append di web group supaya jalan setelah
        // StartSession (Auth::guard()->check() butuh session aktif). Untuk
        // route Filament `/teacher/*`, middleware ini di-register terpisah di
        // TeacherPanelProvider karena panel pakai stack middleware-nya sendiri.
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RedirectIfWrongGuard::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('student') || $request->is('student/*')) {
                return route('student.login');
            }

            return route('login');
        });

        // Heartbeat tracking pakai navigator.sendBeacon di pagehide — beacon tidak
        // bisa attach header CSRF custom. Endpoint tetap di-protect oleh guard
        // `student` (auth session) + payload tracking saja (tidak destructive).
        // Lihat docs/11-learning-progress-tracking.md §5.1.
        $middleware->validateCsrfTokens(except: [
            'student/progress/heartbeat',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Request ke /api/* selalu dibalas envelope API (ADR-0016), termasuk
        // exception yang dilempar framework. Request web sengaja TIDAK disentuh:
        // Inertia bergantung pada redirect-back untuk error validasi form.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ApiExceptionRenderer::handles($request)) {
                return null;
            }

            return ApiExceptionRenderer::render($e, $request);
        });
    })->create();
