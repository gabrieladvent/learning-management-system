<?php

namespace App\Http\Middleware;

use App\Actions\Student\BuildStudentTodoList;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var Student|null $student */
        $student = Auth::guard('student')->user();

        $user = Auth::guard('web')->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'student' => $student ? [
                    'id' => $student->id,
                    'nisn' => $student->nisn,
                    'full_name' => $student->full_name,
                    'class' => $student->class,
                    'tracking_opt_out' => (bool) $student->tracking_opt_out,
                    'tracking_disclosure_seen_at' => $student->user?->tracking_disclosure_seen_at?->toIso8601String(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => $student
                ? [
                    'unread_count' => $student->unreadNotifications()->count(),
                    'recent' => $student->notifications()
                        ->limit(10)
                        ->get()
                        ->map(fn ($notification) => [
                            'id' => $notification->id,
                            'data' => $notification->data,
                            'read_at' => $notification->read_at?->toIso8601String(),
                            'created_at' => $notification->created_at?->toIso8601String(),
                        ])
                        ->values(),
                ]
                : null,
            'todo' => fn () => $student
                ? app(BuildStudentTodoList::class)->handle($student)
                : null,
        ];
    }
}
