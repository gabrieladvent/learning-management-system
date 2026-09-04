<?php

namespace App\Http\Middleware\Api;

use App\Http\Responses\ApiCode;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak siswa yang dinonaktifkan setelah token diterbitkan.
 *
 * `is_active` bisa berubah kapan saja dari panel admin, sementara token berlaku
 * 30 hari. Tanpa pengecekan per-request, siswa yang dinonaktifkan tetap punya
 * akses sampai tokennya kedaluwarsa.
 *
 * Mengembalikan kode mesin `account_inactive` — SENGAJA dibedakan dari
 * `forbidden` biasa, karena klien harus logout paksa dan menampilkan pesan
 * "hubungi sekolah", bukan pesan generik. Lihat ADR-0016.
 */
class EnsureStudentActiveApi
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Student|null $student */
        $student = $request->user();

        if ($student !== null && (! $student->is_active || ! ($student->user?->is_active ?? false))) {
            // Cabut token sekaligus supaya perangkat tidak terus mencoba.
            $student->currentAccessToken()?->delete();

            return ApiResponse::error(ApiCode::AccountInactive);
        }

        return $next($request);
    }
}
