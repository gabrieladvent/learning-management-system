<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi NISN + password, tanpa efek samping sesi/token.
 *
 * Diekstrak dari AuthenticateStudent supaya web (sesi) dan API (token Sanctum)
 * memakai verifikasi yang SAMA PERSIS. Kalau logikanya disalin, perbaikan di
 * satu sisi akan diam-diam tidak ikut di sisi lain — dan ini jalur autentikasi.
 */
class VerifyStudentCredentials
{
    /**
     * Bcrypt hash dummy (valid) untuk menyamakan waktu proses saat NISN tidak
     * ditemukan — mencegah user-enumeration lewat timing (respons "tidak ada"
     * jadi secepat "password salah").
     *
     * JANGAN dihapus saat refactor. Tanpa ini, selisih waktu respons memberi
     * tahu penyerang NISN mana yang terdaftar.
     */
    private const DUMMY_HASH = '$2y$12$Fc8J17Z2l3tZzWVp.S5WNu4JBMV2KGC9wJ5C0FxUNANpVoLYngHAa';

    /**
     * @throws ValidationException jika kredensial salah atau akun nonaktif
     */
    public function handle(string $nisn, string $password): Student
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
            // Pesan SENGAJA sama untuk "NISN tidak ada", "password salah", dan
            // "akun nonaktif". Membedakannya = membocorkan NISN mana yang terdaftar.
            throw ValidationException::withMessages([
                'nisn' => 'NISN atau password salah.',
            ]);
        }

        return $student;
    }
}
