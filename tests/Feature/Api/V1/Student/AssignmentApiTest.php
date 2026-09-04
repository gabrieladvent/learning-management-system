<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Material;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class AssignmentApiTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
        Storage::fake('local');
    }

    /**
     * @return array{student:Student, material:Material, assignment:Assignment, token:string}
     */
    private function scaffold(array $assignmentOverrides = []): array
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

        return [
            'student' => $ctx['student'],
            'material' => $ctx['material'],
            'assignment' => $this->makeAssignment($ctx['material'], $assignmentOverrides),
            'token' => $ctx['student']->createToken('test-device')->plainTextToken,
        ];
    }

    private function submitUrl(array $ctx): string
    {
        return "/api/v1/materials/{$ctx['material']->id}/assignments/{$ctx['assignment']->id}/submit";
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_detail_returns_assignment_with_download_paths(): void
    {
        $ctx = $this->scaffold();
        $ctx['assignment']
            ->addMedia(UploadedFile::fake()->create('soal.pdf', 12))
            ->toMediaCollection('assignment_attachments');

        $response = $this->withToken($ctx['token'])
            ->getJson("/api/v1/materials/{$ctx['material']->id}/assignments/{$ctx['assignment']->id}")
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.assignment.id', $ctx['assignment']->id)
            ->assertJsonStructure([
                'response_data' => [
                    'course', 'material',
                    'assignment' => ['id', 'title', 'deadline', 'max_score', 'allowed_file_types', 'max_file_size_mb', 'status', 'attachments'],
                    'activities',
                ],
            ]);

        $attachment = $response->json('response_data.assignment.attachments.0');

        $this->assertArrayNotHasKey('url', $attachment);
        $this->assertStringStartsWith('/api/v1/materials/', $attachment['download_path']);
        $this->assertStringContainsString('/attachments/', $attachment['download_path']);
    }

    public function test_submit_creates_submission(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Jawaban saya',
        ])
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.submission.content', 'Jawaban saya');

        $this->assertSame(1, AssignmentSubmission::count());
    }

    public function test_replaying_the_same_key_does_not_create_a_second_submission(): void
    {
        // Inti proteksi antrian offline: request yang sampai server tapi
        // responsnya hilang akan dikirim ulang dengan kunci yang sama.
        $ctx = $this->scaffold();
        $key = (string) Str::uuid();

        $first = $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => $key,
            'content' => 'Jawaban pertama',
        ])->assertOk();

        $this->forgetGuards();

        $replay = $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => $key,
            'content' => 'Jawaban pertama',
        ])->assertOk();

        $this->assertSame(1, AssignmentSubmission::count(), 'Tidak boleh ada submission kedua.');
        $this->assertSame($first->json('response_data'), $replay->json('response_data'));
        $this->assertSame('true', $replay->headers->get('Idempotent-Replay'));
    }

    public function test_a_different_key_is_treated_as_a_new_edit(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Versi 1',
        ])->assertOk();

        $this->forgetGuards();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Versi 2',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.submission.content', 'Versi 2');

        // Satu submission per siswa per tugas — kirim ulang = update.
        $this->assertSame(1, AssignmentSubmission::count());
    }

    public function test_submit_without_idempotency_key_is_rejected(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), ['content' => 'Jawaban'])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed')
            ->assertJsonStructure(['response_data' => ['fields' => ['idempotency_key']]]);

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_failed_validation_is_not_cached_so_it_can_be_retried(): void
    {
        // Hanya respons SUKSES yang disimpan: siswa harus bisa memperbaiki
        // kesalahan lalu mengirim ulang dengan kunci yang sama.
        $ctx = $this->scaffold();
        $key = (string) Str::uuid();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => $key,
            'link_url' => 'bukan-url',
        ])->assertStatus(422);

        $this->forgetGuards();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => $key,
            'content' => 'Sudah diperbaiki',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.submission.content', 'Sudah diperbaiki');
    }

    public function test_submit_after_deadline_is_rejected_when_late_not_allowed(): void
    {
        $ctx = $this->scaffold([
            'deadline' => now()->subDay(),
            'accepts_late_submission' => false,
        ]);

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Telat',
        ])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed');

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_submit_after_deadline_is_accepted_and_flagged_when_allowed(): void
    {
        $ctx = $this->scaffold([
            'deadline' => now()->subDay(),
            'accepts_late_submission' => true,
        ]);

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Telat tapi masuk',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.submission.is_late', true);

        $this->assertTrue(AssignmentSubmission::firstOrFail()->is_late);
    }

    public function test_on_time_submission_is_not_flagged_late(): void
    {
        $ctx = $this->scaffold(['deadline' => now()->addWeek()]);

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Tepat waktu',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.submission.is_late', false);
    }

    public function test_new_assignments_accept_late_submission_by_default(): void
    {
        // Default kolom `true` — tugas lama di-backfill `false` oleh migrasi.
        $ctx = $this->scaffold();

        $this->assertTrue($ctx['assignment']->refresh()->accepts_late_submission);

        $this->withToken($ctx['token'])
            ->getJson("/api/v1/materials/{$ctx['material']->id}/assignments/{$ctx['assignment']->id}")
            ->assertOk()
            ->assertJsonPath('response_data.assignment.accepts_late_submission', true);
    }

    public function test_editing_an_on_time_submission_after_deadline_marks_it_late(): void
    {
        // `is_late` mengikuti `submitted_at`, dan keduanya ditimpa saat disunting.
        $ctx = $this->scaffold(['deadline' => now()->addHour()]);

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Versi tepat waktu',
        ])->assertOk()->assertJsonPath('response_data.submission.is_late', false);

        $this->travel(2)->hours();
        $this->forgetGuards();

        $this->withToken($ctx['token'])->postJson($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'content' => 'Disunting setelah deadline',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.submission.is_late', true);

        $this->assertSame(1, AssignmentSubmission::count());
    }

    public function test_disallowed_file_type_is_rejected_with_server_message(): void
    {
        $ctx = $this->scaffold(['allowed_file_types' => ['pdf'], 'max_file_size_mb' => 5]);

        $this->withToken($ctx['token'])->post($this->submitUrl($ctx), [
            'idempotency_key' => (string) Str::uuid(),
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed');

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_cannot_read_assignment_from_another_class(): void
    {
        $ctx = $this->scaffold();
        $other = $this->scaffoldStudentWithMaterial();
        $otherAssignment = $this->makeAssignment($other['material']);

        $this->withToken($ctx['token'])
            ->getJson("/api/v1/materials/{$other['material']->id}/assignments/{$otherAssignment->id}")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_cannot_download_another_students_submission_file(): void
    {
        $ctx = $this->scaffold();
        $classmate = $this->makeStudent($ctx['material']->classroomSubject->classroom->school, $ctx['material']->classroomSubject->classroom);

        $theirSubmission = AssignmentSubmission::create([
            'assignment_id' => $ctx['assignment']->id,
            'student_id' => $classmate->id,
            'content' => 'Punya teman',
            'submitted_at' => now(),
        ]);
        $media = $theirSubmission
            ->addMedia(UploadedFile::fake()->create('punya-teman.pdf', 12))
            ->toMediaCollection('submission_files');

        $this->withToken($ctx['token'])
            ->getJson("/api/v1/materials/{$ctx['material']->id}/assignments/{$ctx['assignment']->id}/submission-files/{$media->uuid}/download")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_can_download_own_attachment_and_submission_file(): void
    {
        $ctx = $this->scaffold();
        $attachment = $ctx['assignment']
            ->addMedia(UploadedFile::fake()->create('soal.pdf', 12))
            ->toMediaCollection('assignment_attachments');

        $this->withToken($ctx['token'])
            ->get("/api/v1/materials/{$ctx['material']->id}/assignments/{$ctx['assignment']->id}/attachments/{$attachment->uuid}/download")
            ->assertOk()
            ->assertDownload('soal.pdf');
    }

    public function test_submit_requires_authentication(): void
    {
        $ctx = $this->scaffold();

        $this->postJson($this->submitUrl($ctx), ['idempotency_key' => (string) Str::uuid()])
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }
}
