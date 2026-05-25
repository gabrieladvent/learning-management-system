<?php

namespace Tests\Feature\LearningProgress;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class MaterialDownloadActivityLogTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_material_file_download_creates_material_download_activity_row(): void
    {
        Storage::fake('public');

        ['student' => $student, 'material' => $material] = $this->scaffoldStudentWithMaterial();

        $media = $material
            ->addMedia(UploadedFile::fake()->create('handout.pdf', 8, 'application/pdf'))
            ->toMediaCollection('material_files');

        $response = $this->actingAs($student, 'student')
            ->get(route('student.materials.files.download', [
                'material' => $material->id,
                'media' => (string) $media->getKey(),
            ]));

        $response->assertOk();
        $response->assertDownload('handout.pdf');

        $logs = Activity::query()
            ->where('log_name', 'material_download')
            ->where('subject_type', $material->getMorphClass())
            ->where('subject_id', $material->id)
            ->get();

        $this->assertCount(1, $logs, 'Download harus menulis tepat 1 baris material_download activity_log.');
        $this->assertSame((string) $media->getKey(), data_get($logs->first()->properties, 'media_id'));
    }

    public function test_download_route_is_forbidden_for_student_not_enrolled(): void
    {
        Storage::fake('public');

        ['student' => $otherStudent] = $this->scaffoldStudentWithMaterial();
        ['material' => $materialB] = $this->scaffoldStudentWithMaterial();

        $media = $materialB
            ->addMedia(UploadedFile::fake()->create('handout.pdf', 8, 'application/pdf'))
            ->toMediaCollection('material_files');

        $response = $this->actingAs($otherStudent, 'student')
            ->get(route('student.materials.files.download', [
                'material' => $materialB->id,
                'media' => (string) $media->getKey(),
            ]));

        $response->assertNotFound();

        $this->assertSame(
            0,
            Activity::query()->where('log_name', 'material_download')->count(),
            'Akses tidak sah jangan menghasilkan activity_log row.',
        );
    }
}
