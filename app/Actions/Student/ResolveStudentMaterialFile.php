<?php

namespace App\Actions\Student;

use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveStudentMaterialFile
{
    /**
     * @return array{material: Material, media: Media}
     *
     * @throws NotFoundHttpException jika materi/file tidak ada atau tidak boleh diakses
     */
    public function handle(Student $student, string $materialId, string $mediaId): array
    {
        $material = Material::query()
            ->whereKey($materialId)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->whereHas('classroomSubject.classroom.students', fn (Builder $q) => $q->whereKey($student->id))
            ->first();

        if (! $material) {
            throw new NotFoundHttpException('Materi tidak ditemukan.');
        }

        // Dicari di dalam collection spesifik supaya tidak bisa lintas-model.
        /** @var Media|null $media */
        $media = $material->getMedia('material_files')->firstWhere('id', $mediaId)
            ?? $material->getMedia('material_files')->firstWhere('uuid', $mediaId);

        if (! $media) {
            throw new NotFoundHttpException('File tidak ditemukan.');
        }

        // Activity log — proxy completion untuk material type=file (docs/11 §7.1).
        activity('material_download')
            ->performedOn($material)
            ->withProperties(['media_id' => (string) $media->getKey(), 'file_name' => $media->file_name])
            ->log('downloaded');

        return ['material' => $material, 'media' => $media];
    }
}
