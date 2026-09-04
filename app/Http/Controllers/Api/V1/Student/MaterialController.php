<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\GetStudentCourse;
use App\Actions\Student\GetStudentMaterial;
use App\Actions\Student\ResolveStudentMaterialFile;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaterialController extends Controller
{
    /**
     * Detail course + daftar materi yang boleh diakses siswa.
     *
     * Materi sudah terurut `order`; klien mengelompokkannya per `topic`.
     */
    public function course(Request $request, string $course, GetStudentCourse $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return ApiResponse::success($action->handle($student, $course));
    }

    /**
     * Detail satu materi: konten, link, file, tugas, dan ujian miliknya.
     */
    public function show(Request $request, string $course, string $material, GetStudentMaterial $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $payload = $action->handle($student, $course, $material);

        $payload['material']['files'] = array_map(
            fn (array $file) => $this->toDownloadPath($file, $material),
            $payload['material']['files'],
        );

        // Dicatat SETELAH Action lolos otorisasi — jangan log akses yang ditolak.
        // Paritas dengan jalur web: tanpa ini, membaca materi lewat aplikasi
        // tidak terhitung di laporan progres guru.
        $materialModel = Material::query()->find($material);
        if ($materialModel) {
            activity('material_view')
                ->performedOn($materialModel)
                ->log('viewed');
        }

        return ApiResponse::success($payload);
    }

    /**
     * Stream file lampiran dari disk PRIVAT lewat route berautorisasi.
     *
     * File tidak punya URL publik — satu-satunya jalan masuk adalah endpoint ini,
     * yang memverifikasi enrollment lebih dulu. Lihat ADR-0010.
     */
    public function download(
        Request $request,
        string $material,
        string $media,
        ResolveStudentMaterialFile $action,
    ): BinaryFileResponse {
        /** @var Student $student */
        $student = $request->user();

        ['media' => $file] = $action->handle($student, $material, $media);

        // BinaryFileResponse menangani header Range sendiri saat prepare(),
        // jadi unduhan besar bisa dilanjutkan di jaringan yang putus-nyambung.
        return response()
            ->download($file->getPath(), $file->file_name)
            ->setAutoEtag()
            ->setAutoLastModified();
    }

    /**
     * Menukar `url` (route web absolut) dengan `download_path` relatif ke API.
     *
     * URL web tidak bisa dipakai klien token — route-nya berautentikasi sesi.
     * Path relatif juga membuat aplikasi tidak menyimpan host di cache, sehingga
     * pindah environment tidak membuat file lama tak bisa diunduh.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function toDownloadPath(array $file, string $materialId): array
    {
        unset($file['url']);

        $file['download_path'] = route('api.v1.materials.files.download', [
            'material' => $materialId,
            'media' => $file['id'],
        ], absolute: false);

        return $file;
    }
}
