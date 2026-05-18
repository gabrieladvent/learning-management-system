<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\AuthenticateStudent;
use App\Actions\Student\LogoutStudent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('student.dashboard');
        }

        return Inertia::render('Auth/StudentLogin');
    }

    public function login(Request $request, AuthenticateStudent $authenticate): RedirectResponse
    {
        $data = $request->validate([
            'nisn' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $authenticate->handle($request, $data['nisn'], $data['password']);

        return redirect()->intended(route('student.dashboard'));
    }

    public function logout(Request $request, LogoutStudent $logout): RedirectResponse
    {
        $logout->handle($request);

        return redirect()->route('student.login');
    }
}
