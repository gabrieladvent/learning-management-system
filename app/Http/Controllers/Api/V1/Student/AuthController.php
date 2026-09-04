<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\VerifyStudentCredentials;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * Login siswa — menerbitkan personal access token Sanctum.
     *
     * Tidak memakai sesi sama sekali: tidak ada Auth::login(), tidak ada
     * session()->regenerate(). Verifikasi kredensialnya sendiri dipakai bersama
     * dengan jalur web lewat VerifyStudentCredentials.
     */
    public function login(Request $request, VerifyStudentCredentials $verify): JsonResponse
    {
        $data = $request->validate([
            'nisn' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = 'student-login:'.$data['nisn'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'nisn' => sprintf(
                    'Terlalu banyak percobaan login. Coba lagi dalam %d detik.',
                    $seconds,
                ),
            ]);
        }

        try {
            $student = $verify->handle($data['nisn'], $data['password']);
        } catch (ValidationException $e) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            Log::warning('Student API login gagal', [
                'nisn' => $data['nisn'],
                'ip' => $request->ip(),
                'attempts' => RateLimiter::attempts($throttleKey),
            ]);

            throw $e;
        }

        RateLimiter::clear($throttleKey);

        $student->user->forceFill(['last_login_at' => now()])->save();

        $deviceName = $data['device_name'] ?? 'unknown-device';

        // Satu token per perangkat: token lama dengan nama sama dicabut supaya
        // instalasi ulang tidak menumpuk token yang tak bisa dilacak pemiliknya.
        $student->tokens()->where('name', $deviceName)->delete();

        // expires_at diisi eksplisit — lihat catatan di config/sanctum.php.
        $ttl = (int) config('sanctum.student_token_ttl_minutes');
        $expiresAt = $ttl > 0 ? now()->addMinutes($ttl) : null;

        $token = $student->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'must_change_password' => $student->user->password_changed_at === null,
            'student' => $this->studentPayload($student),
        ], 'Berhasil masuk.');
    }

    /**
     * Mencabut HANYA token yang sedang dipakai — perangkat lain tetap login.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $student->currentAccessToken()?->delete();

        return ApiResponse::success(message: 'Berhasil keluar.');
    }

    /**
     * Profil siswa terkini. Dipakai klien saat cold start untuk memvalidasi
     * token sebelum menampilkan data cache.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return ApiResponse::success([
            'must_change_password' => $student->user?->password_changed_at === null,
            'student' => $this->studentPayload($student),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'nisn' => $student->nisn,
            'class' => $student->class,
            'photo_url' => $student->user?->getAvatarUrl(),
            'tracking_opt_out' => (bool) $student->tracking_opt_out,
            'tracking_disclosure_seen' => $student->user?->tracking_disclosure_seen_at !== null,
        ];
    }
}
