<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\BuildStudentTodoList;
use App\Actions\Student\GetStudentDashboard;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Beranda siswa: daftar mata pelajaran, statistik, dan meta kelas.
     *
     * Course sudah terurut dengan yang di-pin di atas — klien tidak perlu
     * mengurut ulang.
     */
    public function index(Request $request, GetStudentDashboard $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $data = $action->handle($student);
        $data['stats'] = $this->stripUrl($data['stats']);

        return ApiResponse::success($data);
    }

    /**
     * To-do list: tugas & ujian dikelompokkan hari ini / minggu ini / nanti.
     */
    public function todo(Request $request, BuildStudentTodoList $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $data = $action->handle($student);

        foreach (['today', 'this_week', 'later'] as $bucket) {
            $data[$bucket] = array_map($this->stripUrl(...), $data[$bucket]);
        }

        return ApiResponse::success($data);
    }

    /**
     * Membuang field `url` (hasil route() Laravel) dari payload.
     *
     * URL web tidak berarti apa-apa di aplikasi mobile, dan membiarkannya
     * membuat klien tergoda memakainya — menyeret ketergantungan ke struktur
     * route web yang bisa berubah kapan saja. Klien menyusun rutenya sendiri
     * dari `material_id`. Lihat ADR-0003.
     *
     * Penghapusan sengaja TIDAK rekursif: kalau kelak ada field `url` yang
     * memang bermakna (mis. link materi dari guru), penghapusan rekursif akan
     * menelannya diam-diam.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stripUrl(array $payload): array
    {
        unset($payload['url']);

        if (isset($payload['upcoming_exam']) && is_array($payload['upcoming_exam'])) {
            unset($payload['upcoming_exam']['url']);
        }

        return $payload;
    }
}
