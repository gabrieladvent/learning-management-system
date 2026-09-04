<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memperpanjang masa berlaku token setiap kali dipakai (sliding expiration).
 *
 * `sanctum.expiration` bawaan bersifat ABSOLUT sejak token dibuat: siswa yang
 * memakai aplikasi tiap hari tetap akan ditendang keluar tepat 30 hari setelah
 * login, lalu harus mengingat password barunya. ADR-0004 menetapkan "30 hari
 * sejak pemakaian TERAKHIR", jadi perpanjangannya harus eksplisit seperti ini.
 *
 * Penulisan dibatasi maksimal sekali per REFRESH_INTERVAL_MINUTES supaya tidak
 * ada UPDATE ke personal_access_tokens di setiap request.
 */
class RefreshTokenExpiry
{
    /** Jangan menulis ulang expires_at lebih sering dari ini. */
    private const REFRESH_INTERVAL_MINUTES = 1440; // 1 hari

    public function handle(Request $request, Closure $next): Response
    {
        // SEBELUM controller, bukan sesudah. Kalau dijalankan setelahnya,
        // logout yang baru saja menghapus token akan di-save() ulang di sini
        // dan barisnya HIDUP KEMBALI — siswa tetap bisa mengakses API dengan
        // token yang seharusnya sudah dicabut.
        $this->extend($request);

        return $next($request);
    }

    private function extend(Request $request): void
    {
        $minutes = (int) config('sanctum.student_token_ttl_minutes');
        if ($minutes <= 0) {
            return;
        }

        $token = $request->user()?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! $token->exists) {
            return;
        }

        $target = now()->addMinutes($minutes);

        // Selisih antara expires_at sekarang dan target = waktu sejak
        // perpanjangan terakhir. Kalau masih di bawah interval, lewati.
        if ($token->expires_at !== null
            && $token->expires_at->diffInMinutes($target, absolute: true) < self::REFRESH_INTERVAL_MINUTES) {
            return;
        }

        $token->forceFill(['expires_at' => $target])->save();
    }
}
