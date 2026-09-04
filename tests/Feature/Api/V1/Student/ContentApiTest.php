<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\ClassroomSubject;
use App\Models\Material;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
    }

    /**
     * @return array{student:Student, classroomSubject:ClassroomSubject, material:Material}
     */
    private function scaffold(): array
    {
        $ctx = $this->scaffoldStudentWithMaterial();

        $user = User::create([
            'name' => $ctx['student']->full_name,
            'email' => null,
            'password' => bcrypt('rahasia-banget'),
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('student');
        $ctx['student']->forceFill(['user_id' => $user->id])->save();

        return $ctx;
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test-device')->plainTextToken;
    }

    public function test_course_detail_lists_materials(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/courses/{$ctx['classroomSubject']->id}")
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.course.id', $ctx['classroomSubject']->id)
            ->assertJsonPath('response_data.materials.0.id', $ctx['material']->id)
            ->assertJsonStructure([
                'response_data' => [
                    'course' => ['id', 'subject_name', 'classroom_name', 'teacher_name'],
                    'materials' => [['id', 'title', 'topic', 'has_files', 'has_link', 'has_content']],
                ],
            ]);
    }

    public function test_course_from_another_class_is_not_found(): void
    {
        $ctx = $this->scaffold();
        $other = $this->scaffoldStudentWithMaterial();

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/courses/{$other['classroomSubject']->id}")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_unpublished_material_is_not_found(): void
    {
        $ctx = $this->scaffold();
        $hidden = $this->makeMaterial($ctx['classroomSubject'], ['is_published' => false]);

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/courses/{$ctx['classroomSubject']->id}/materials/{$hidden->id}")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_material_not_yet_available_is_not_found(): void
    {
        $ctx = $this->scaffold();
        $future = $this->makeMaterial($ctx['classroomSubject'], [
            'available_from' => now()->addWeek(),
        ]);

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/courses/{$ctx['classroomSubject']->id}/materials/{$future->id}")
            ->assertStatus(404);
    }

    public function test_material_detail_exposes_download_path_not_web_url(): void
    {
        Storage::fake('local');
        $ctx = $this->scaffold();
        $ctx['material']
            ->addMedia(UploadedFile::fake()->create('modul.pdf', 12))
            ->toMediaCollection('material_files');

        $response = $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/courses/{$ctx['classroomSubject']->id}/materials/{$ctx['material']->id}")
            ->assertOk();

        $file = $response->json('response_data.material.files.0');

        $this->assertNotNull($file, 'File lampiran harus muncul di payload.');
        $this->assertArrayNotHasKey('url', $file, 'URL route web tidak boleh bocor ke klien token.');
        $this->assertStringStartsWith(
            "/api/v1/materials/{$ctx['material']->id}/files/",
            $file['download_path'],
        );
        // Path relatif: aplikasi tidak menyimpan host di cache.
        $this->assertStringStartsNotWith('http', $file['download_path']);
    }

    public function test_download_streams_file_and_logs_completion_with_causer(): void
    {
        Storage::fake('local');
        $ctx = $this->scaffold();
        $media = $ctx['material']
            ->addMedia(UploadedFile::fake()->create('modul.pdf', 12))
            ->toMediaCollection('material_files');

        $this->withToken($this->tokenFor($ctx['student']))
            ->get("/api/v1/materials/{$ctx['material']->id}/files/{$media->getKey()}/download")
            ->assertOk()
            ->assertDownload('modul.pdf');

        // `material_download` dipakai sebagai proxy completion. Tanpa causer
        // yang benar, progres siswa pengguna aplikasi hilang dari laporan guru.
        $activity = Activity::query()->where('log_name', 'material_download')->latest('id')->first();

        $this->assertNotNull($activity, 'Unduhan harus tercatat sebagai activity.');
        $this->assertSame($ctx['student']->id, $activity->causer_id);
        $this->assertSame(Student::class, $activity->causer_type);
    }

    public function test_download_from_another_class_is_not_found(): void
    {
        Storage::fake('local');
        $ctx = $this->scaffold();
        $other = $this->scaffoldStudentWithMaterial();
        $media = $other['material']
            ->addMedia(UploadedFile::fake()->create('rahasia.pdf', 12))
            ->toMediaCollection('material_files');

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/materials/{$other['material']->id}/files/{$media->getKey()}/download")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_viewing_material_is_logged_for_progress(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson("/api/v1/courses/{$ctx['classroomSubject']->id}/materials/{$ctx['material']->id}")
            ->assertOk();

        $activity = Activity::query()->where('log_name', 'material_view')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame($ctx['student']->id, $activity->causer_id);
    }

    public function test_content_endpoints_require_authentication(): void
    {
        $ctx = $this->scaffold();

        $this->getJson("/api/v1/courses/{$ctx['classroomSubject']->id}")
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }
}
