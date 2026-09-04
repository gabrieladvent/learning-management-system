<?php

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Menerjemahkan exception jadi envelope API.
 *
 * Dipasang di bootstrap/app.php dan HANYA berlaku untuk request ke /api/*.
 * Request web tetap memakai penanganan Laravel/Inertia yang sudah ada — kalau
 * ikut dibungkus, redirect-back untuk form Inertia akan rusak.
 */
final class ApiExceptionRenderer
{
    public static function handles(Request $request): bool
    {
        return $request->is('api/*');
    }

    public static function render(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return ApiResponse::validationFailed($e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(ApiCode::Unauthenticated);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiResponse::error(ApiCode::NotFound);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return ApiResponse::error(ApiCode::Forbidden, self::messageOrNull($e->getMessage()));
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return ApiResponse::error(ApiCode::TooManyRequests);
        }

        if ($e instanceof HttpExceptionInterface) {
            return ApiResponse::error(
                self::codeForStatus($e->getStatusCode()),
                self::messageOrNull($e->getMessage()),
                status: $e->getStatusCode(),
            );
        }

        // Exception tak terduga: JANGAN bocorkan pesan aslinya ke siswa — bisa
        // memuat nama tabel, path file, atau detail internal lain. Detailnya
        // tetap masuk log lewat penanganan Laravel di belakang ini.
        return ApiResponse::error(ApiCode::ServerError);
    }

    private static function codeForStatus(int $status): ApiCode
    {
        return match ($status) {
            400 => ApiCode::BadRequest,
            401 => ApiCode::Unauthenticated,
            403 => ApiCode::Forbidden,
            404 => ApiCode::NotFound,
            409 => ApiCode::Conflict,
            413 => ApiCode::PayloadTooLarge,
            422 => ApiCode::ValidationFailed,
            426 => ApiCode::ClientTooOld,
            429 => ApiCode::TooManyRequests,
            default => ApiCode::ServerError,
        };
    }

    /**
     * `abort()` tanpa pesan menghasilkan string kosong — pakai default enum.
     */
    private static function messageOrNull(string $message): ?string
    {
        $trimmed = trim($message);

        return $trimmed === '' ? null : $trimmed;
    }
}
