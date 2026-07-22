<?php

namespace App\Http\Controllers\Student\Concerns;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streaming file media dari disk PRIVAT lewat route berautorisasi.
 *
 * File tidak lagi punya URL publik (`getUrl()`), jadi satu-satunya jalan siswa
 * mengunduh adalah controller yang sudah memverifikasi kepemilikan/enrollment.
 * Media dicari dalam collection spesifik agar tidak bisa lintas-model.
 */
trait ServesGuardedMedia
{
    protected function streamMediaFromCollection(
        HasMedia $model,
        string $collection,
        string $mediaId,
    ): BinaryFileResponse {
        /** @var Media|null $media */
        $media = $model->getMedia($collection)->firstWhere('id', $mediaId)
            ?? $model->getMedia($collection)->firstWhere('uuid', $mediaId);

        if (! $media) {
            throw new NotFoundHttpException('File tidak ditemukan.');
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
