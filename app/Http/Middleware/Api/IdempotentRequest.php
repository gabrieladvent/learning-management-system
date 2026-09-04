<?php

namespace App\Http\Middleware\Api;

use App\Http\Responses\ApiCode;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membuat endpoint tulis aman diulang.
 *
 * Aplikasi mobile mengantre pengiriman saat jaringan putus, lalu mencoba lagi.
 * Masalahnya, request yang SUDAH sampai server tapi responsnya hilang di jalan
 * tidak bisa dibedakan dari yang tidak pernah sampai. Tanpa kunci idempotensi,
 * percobaan ulang bisa menimpa jawaban yang lebih baru atau menghasilkan
 * notifikasi ganda ke guru.
 *
 * Klien mengirim `idempotency_key` (UUID) yang dibuat SEKALI saat item masuk
 * antrian dan tidak berubah di percobaan ulang. Hasil sukses disimpan 24 jam
 * dan dikembalikan apa adanya untuk kunci yang sama.
 *
 * Hanya respons SUKSES yang disimpan: kegagalan validasi harus bisa diperbaiki
 * lalu dikirim ulang dengan kunci yang sama.
 */
class IdempotentRequest
{
    private const TTL_SECONDS = 86400; // 24 jam

    /** Batas atas satu request diproses; lock lepas sendiri kalau proses mati. */
    private const LOCK_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->input('idempotency_key', '');

        if (! Str::isUuid($key)) {
            // Ditolak, bukan dilewatkan diam-diam: request tanpa kunci berarti
            // tidak ada proteksi duplikat sama sekali, dan itu harus terlihat
            // saat pengembangan klien, bukan saat siswa kehilangan tugasnya.
            return ApiResponse::validationFailed([
                'idempotency_key' => ['idempotency_key wajib diisi dan harus UUID.'],
            ]);
        }

        /** @var Student $student */
        $student = $request->user();
        $cacheKey = "idem:{$student->id}:{$key}";

        if (($stored = Cache::get($cacheKey)) !== null) {
            return $this->replay($stored);
        }

        // Dua request dengan kunci sama yang tiba bersamaan (siswa menekan
        // kirim dua kali) — yang kedua ditolak, bukan diproses paralel.
        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);

        if (! $lock->get()) {
            return ApiResponse::error(
                ApiCode::Conflict,
                'Pengiriman sebelumnya masih diproses. Tunggu sebentar.',
            );
        }

        try {
            $response = $next($request);

            if ($response instanceof JsonResponse && $response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getData(true),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{status:int, body:array<string, mixed>}  $stored
     */
    private function replay(array $stored): JsonResponse
    {
        return response()
            ->json($stored['body'], $stored['status'])
            // Supaya klien (dan kita saat debug) tahu ini hasil yang diputar
            // ulang, bukan pemrosesan baru.
            ->header('Idempotent-Replay', 'true');
    }
}
