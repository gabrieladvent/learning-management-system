<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticateStudent
{
    public function __construct(
        private readonly VerifyStudentCredentials $verify,
    ) {}

    /**
     * Verify NISN + password, log the student in, regenerate the session.
     *
     * Khusus jalur WEB (sesi). Jalur API memakai VerifyStudentCredentials
     * langsung lalu menerbitkan token Sanctum — lihat
     * App\Http\Controllers\Api\V1\Student\AuthController.
     *
     * @throws ValidationException jika kredensial salah atau akun nonaktif
     */
    public function handle(Request $request, string $nisn, string $password): Student
    {
        $student = $this->verify->handle($nisn, $password);

        Auth::guard('student')->login($student, remember: false);
        $request->session()->regenerate();

        $student->user->forceFill(['last_login_at' => now()])->save();

        return $student;
    }
}
