<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Ganti password siswa.
     *
     * Route-nya SENGAJA di luar EnsureStudentPasswordChangedApi: kalau ikut
     * middleware itu, siswa yang masih memakai password default akan diblokir
     * dari satu-satunya endpoint yang bisa melepaskannya — terkunci permanen.
     *
     * Logikanya sengaja disamakan dengan jalur web (Student\ProfileController)
     * supaya password yang diterima di web dan di aplikasi tidak berbeda aturan.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $user = $student->user;

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            // `different` menutup celah yang ada di jalur web: tanpa ini, siswa
            // bisa "mengganti" password ke tanggal lahirnya sendiri. Flag
            // password_changed_at ikut terisi, guard melepaskannya, tapi
            // passwordnya tetap semudah sebelumnya.
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        // Dicek SETELAH validasi format, supaya pesan "password baru terlalu
        // pendek" tetap muncul meski password lamanya juga salah.
        if ($user === null || ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        // Klien memakai nilai ini untuk melepas guard layar ganti password
        // tanpa perlu memanggil ulang GET /auth/me.
        return ApiResponse::success(
            ['must_change_password' => false],
            'Password berhasil diperbarui.',
        );
    }
}
