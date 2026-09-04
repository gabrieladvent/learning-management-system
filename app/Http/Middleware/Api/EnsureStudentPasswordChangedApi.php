<?php

namespace App\Http\Middleware\Api;

use App\Http\Responses\ApiCode;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memblokir akses konten selama siswa masih memakai password default.
 *
 * Siswa dibuat dengan `password_changed_at = null` dan password awal berupa
 * tanggal lahir — mudah ditebak siapa pun yang kenal dia. Padanan web-nya
 * adalah EnsureStudentPasswordChanged.
 *
 * Mengembalikan kode mesin `password_change_required`, SENGAJA dibedakan dari
 * `forbidden` biasa: klien harus mengarahkan siswa ke layar ganti password,
 * bukan menampilkan pesan generik. Padanan web-nya memakai abort(403, '...')
 * yang dari sisi aplikasi tak terbedakan dari 403 lain — itu persis alasan
 * ADR-0016 mewajibkan kode mesin.
 *
 * Middleware ini TIDAK dipasang di route auth (me/logout/ganti password):
 * whitelist-nya diatur di routes/api.php, bukan di sini.
 */
class EnsureStudentPasswordChangedApi
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Student|null $student */
        $student = $request->user();

        if ($student !== null && $student->user?->password_changed_at === null) {
            return ApiResponse::error(ApiCode::PasswordChangeRequired);
        }

        return $next($request);
    }
}
