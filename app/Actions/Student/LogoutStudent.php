<?php

namespace App\Actions\Student;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutStudent
{
    public function handle(Request $request): void
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
