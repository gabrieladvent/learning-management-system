<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateStudent
{
    /**
     * Verify NISN + password, log the student in, regenerate the session.
     *
     * @throws ValidationException jika kredensial salah atau akun nonaktif
     */
    public function handle(Request $request, string $nisn, string $password): Student
    {
        $student = Student::query()
            ->with('user')
            ->where('nisn', $nisn)
            ->where('is_active', true)
            ->first();

        $valid = $student
            && $student->user
            && Hash::check($password, $student->user->password);

        if (! $valid) {
            throw ValidationException::withMessages([
                'nisn' => 'NISN atau password salah.',
            ]);
        }

        Auth::guard('student')->login($student, remember: false);
        $request->session()->regenerate();

        $student->user->forceFill(['last_login_at' => now()])->save();

        return $student;
    }
}
