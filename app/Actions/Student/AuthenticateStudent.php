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
     * Bcrypt hash dummy (valid) untuk menyamakan waktu proses saat NISN tidak
     * ditemukan — mencegah user-enumeration lewat timing (respons "tidak ada"
     * jadi secepat "password salah").
     */
    private const DUMMY_HASH = '$2y$12$Fc8J17Z2l3tZzWVp.S5WNu4JBMV2KGC9wJ5C0FxUNANpVoLYngHAa';

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

        if ($student && $student->user) {
            $valid = Hash::check($password, $student->user->password);
        } else {
            // Tetap lakukan hash-check pada dummy supaya waktu respons konstan.
            Hash::check($password, self::DUMMY_HASH);
            $valid = false;
        }

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
