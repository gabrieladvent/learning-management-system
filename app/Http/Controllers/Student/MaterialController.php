<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentMaterial;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MaterialController extends Controller
{
    public function show(string $course, string $material, GetStudentMaterial $action): InertiaResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $payload = $action->handle($student, $course, $material);

        $materialModel = Material::query()->find($material);
        if ($materialModel) {
            // Causer otomatis di-resolve oleh CauserResolver di AppServiceProvider
            // (returns student aktif untuk guard 'student').
            activity('material_view')
                ->performedOn($materialModel)
                ->log('viewed');
        }

        return Inertia::render('Material/MaterialDetail', $payload);
    }

    public function downloadFile(string $materialId, string $mediaId): BinaryFileResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

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

        /** @var Media|null $media */
        $media = $material->getMedia('material_files')->firstWhere('id', $mediaId)
            ?? $material->getMedia('material_files')->firstWhere('uuid', $mediaId);

        if (! $media) {
            throw new NotFoundHttpException('File tidak ditemukan.');
        }

        // Activity log — proxy completion untuk material type=file (§7.1).
        activity('material_download')
            ->performedOn($material)
            ->withProperties(['media_id' => (string) $media->getKey(), 'file_name' => $media->file_name])
            ->log('downloaded');

        return response()->download($media->getPath(), $media->file_name);
    }
}
