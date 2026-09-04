<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\ClassroomSubject;
use App\Models\Material;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
    }

    /**
     * scaffoldStudentWithMaterial() membuat Student TANPA User, sedangkan
     * middleware password dan payload profil butuh User-nya. Helper ini
     * melengkapinya.
     *
     * @return array{student:Student, classroomSubject:ClassroomSubject, material:Material}
     */
    private function scaffold(bool $passwordChanged = true): array
    {
        $ctx = $this->scaffoldStudentWithMaterial();

        $user = User::create([
            'name' => $ctx['student']->full_name,
            'email' => null,
            'password' => bcrypt('rahasia-banget'),
            'is_active' => true,
            'password_changed_at' => $passwordChanged ? now() : null,
        ]);
        $user->assignRole('student');

        $ctx['student']->forceFill(['user_id' => $user->id])->save();

        return $ctx;
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test-device')->plainTextToken;
    }

    /**
     * Guard auth menyimpan instance Student yang sudah di-resolve untuk seluruh
     * proses test, sehingga request berikutnya memakai objek yang sama —
     * termasuk relasi yang sudah ter-load, yang membuat loadMissing() di
     * GetStudentDashboard melewatkan data baru. Di produksi tiap request punya
     * instance sendiri, jadi ini murni artefak test.
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_dashboard_returns_courses_stats_and_meta(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($this->tokenFor($ctx['student']))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonStructure([
                'response_data' => [
                    'courses' => [['id', 'subject_name', 'classroom_name', 'teacher_name', 'is_pinned']],
                    'stats' => ['assignments_pending', 'assignments_completed', 'exams_completed', 'avg_score'],
                    'meta' => ['classroom_name', 'academic_year', 'homeroom_teacher_name', 'semester'],
                ],
            ])
            ->assertJsonPath('response_data.courses.0.id', $ctx['classroomSubject']->id);
    }

    public function test_dashboard_strips_web_url_from_upcoming_exam(): void
    {
        // `url` berisi hasil route() Laravel — tidak berarti di aplikasi dan
        // menyeret ketergantungan ke struktur route web. Lihat ADR-0003.
        $ctx = $this->scaffold();
        $this->makeExam($ctx['material'], ['starts_at' => now()->addDays(2)]);

        $response = $this->withToken($this->tokenFor($ctx['student']))
            ->getJson('/api/v1/dashboard')
            ->assertOk();

        $upcoming = $response->json('response_data.stats.upcoming_exam');

        $this->assertNotNull($upcoming, 'Ujian mendatang harus muncul di stats.');
        $this->assertArrayNotHasKey('url', $upcoming);
        $this->assertArrayHasKey('material_id', $upcoming);
        $this->assertSame($ctx['material']->id, $upcoming['material_id']);
    }

    public function test_todo_items_carry_material_id_and_no_web_url(): void
    {
        $ctx = $this->scaffold();
        $assignment = $this->makeAssignment($ctx['material'], ['deadline' => now()->addDay()]);

        $response = $this->withToken($this->tokenFor($ctx['student']))
            ->getJson('/api/v1/todo')
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonStructure(['response_data' => ['today', 'this_week', 'later', 'count_this_week']]);

        $data = $response->json('response_data');
        $all = array_merge($data['today'], $data['this_week'], $data['later']);

        $found = collect($all)->firstWhere('id', $assignment->id);

        $this->assertNotNull($found, 'Tugas harus muncul di to-do list.');
        $this->assertArrayNotHasKey('url', $found);
        $this->assertSame($ctx['material']->id, $found['material_id']);
        $this->assertSame('assignment', $found['kind']);
    }

    public function test_pin_moves_course_to_top_and_is_idempotent(): void
    {
        $ctx = $this->scaffold();
        $token = $this->tokenFor($ctx['student']);
        $courseId = $ctx['classroomSubject']->id;

        $this->withToken($token)->postJson("/api/v1/courses/{$courseId}/pin")
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonMissingPath('response_data');

        // Pin dua kali tidak boleh error.
        $this->withToken($token)->postJson("/api/v1/courses/{$courseId}/pin")->assertOk();

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertJsonPath('response_data.courses.0.is_pinned', true);

        $this->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/courses/{$courseId}/pin")->assertOk();
        $this->withToken($token)->deleteJson("/api/v1/courses/{$courseId}/pin")->assertOk();

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertJsonPath('response_data.courses.0.is_pinned', false);
    }

    public function test_pinning_a_course_from_another_class_returns_not_found(): void
    {
        $ctx = $this->scaffold();
        $other = $this->scaffoldStudentWithMaterial();

        $this->withToken($this->tokenFor($ctx['student']))
            ->postJson("/api/v1/courses/{$other['classroomSubject']->id}/pin")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_default_password_blocks_content_but_not_auth_endpoints(): void
    {
        $ctx = $this->scaffold(passwordChanged: false);
        $token = $this->tokenFor($ctx['student']);

        foreach (['/api/v1/dashboard', '/api/v1/todo'] as $url) {
            $this->withToken($token)->getJson($url)
                ->assertStatus(403)
                ->assertJsonPath('response_code', 'password_change_required');
        }

        // Siswa tetap harus bisa mengecek sesinya dan keluar.
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('response_data.must_change_password', true);
    }

    public function test_content_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }

    public function test_deactivated_student_is_rejected_before_password_check(): void
    {
        $ctx = $this->scaffold(passwordChanged: false);
        $token = $this->tokenFor($ctx['student']);

        $ctx['student']->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('response_code', 'account_inactive');
    }
}
