<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Satu-satunya tempat envelope response API dibentuk.
 *
 * Bentuknya (ADR-0016):
 *   { "response_code": "...", "response_message": "...", "response_data": {...} }
 *   { "response_code": "...", "response_message": "..." }
 *
 * `response_data` DIHILANGKAN kalau tidak ada data — bukan dikirim sebagai null.
 *
 * Controller tidak boleh menyusun array envelope secara manual. Kalau ada satu
 * saja yang melakukannya, bentuk response jadi tidak konsisten dan klien mobile
 * pecah pada endpoint itu saja — bug yang mahal dicari.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function success(
        ?array $data = null,
        ?string $message = null,
        int $status = 200,
    ): JsonResponse {
        return self::make(
            ApiCode::Success,
            $message ?? ApiCode::Success->defaultMessage(),
            $data,
            $status,
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function error(
        ApiCode $code,
        ?string $message = null,
        ?array $data = null,
        ?int $status = null,
    ): JsonResponse {
        return self::make(
            $code,
            $message ?? $code->defaultMessage(),
            $data,
            $status ?? $code->httpStatus(),
        );
    }

    /**
     * Error validasi — detail per-field masuk ke `response_data.fields`.
     *
     * @param  array<string, array<int, string>>  $fields
     */
    public static function validationFailed(array $fields, ?string $message = null): JsonResponse
    {
        // Pesan diambil dari error pertama supaya siswa langsung melihat kalimat
        // yang relevan, bukan kalimat generik.
        $first = $message;
        if ($first === null) {
            $firstBag = reset($fields);
            $first = is_array($firstBag) && $firstBag !== []
                ? (string) reset($firstBag)
                : ApiCode::ValidationFailed->defaultMessage();
        }

        return self::error(ApiCode::ValidationFailed, $first, ['fields' => $fields]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private static function make(ApiCode $code, string $message, ?array $data, int $status): JsonResponse
    {
        $payload = [
            'response_code' => $code->value,
            'response_message' => $message,
        ];

        if ($data !== null) {
            $payload['response_data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
