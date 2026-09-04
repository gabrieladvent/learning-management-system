<?php

namespace Tests\Feature\Api\V1\Student;

use App\Actions\Student\RegisterStudent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
    }

    /**
     * Guard auth di-cache per proses; request kedua dalam satu test memakai
     * model user yang sudah di-resolve, jadi password_changed_at yang baru
     * saja diubah tidak terlihat. Artefak test, bukan lubang produksi —
     * lihat AuthApiTest::test_deleted_token_is_rejected_on_a_fresh_request().
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function makeStudent(): Student
    {
        return app(RegisterStudent::class)->handle([
            'full_name' => 'Budi Santoso',
            'school_id' => $this->makeSchool()->id,
            'nisn' => '1234567890',
            'class' => 'X IPA 1',
            'gender' => 'male',
            'birth_date' => '2008-05-10',
            'is_active' => true,
        ]);
    }

    private function login(string $password = '2008-05-10'): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => $password,
        ])->json('response_data.token');
    }

    public function test_student_can_change_password(): void
    {
        $student = $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.must_change_password', false);

        $user = $student->fresh()->user;

        $this->assertNotNull($user->password_changed_at, 'Flag password_changed_at harus terisi.');
        $this->assertTrue(Hash::check('rahasia-baru-123', $user->password));
    }

    public function test_new_password_works_for_the_next_login(): void
    {
        // Yang benar-benar diuji: password baru BENAR-BENAR tersimpan, bukan
        // sekadar flag-nya berubah.
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])->assertOk();

        $this->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => 'rahasia-baru-123',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.must_change_password', false);

        $this->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => '2008-05-10',
        ])->assertStatus(422);
    }

    public function test_endpoint_is_reachable_while_password_change_is_still_required(): void
    {
        // Regresi terpenting di berkas ini. Kalau route ini ikut masuk
        // EnsureStudentPasswordChangedApi, siswa terkunci permanen: endpoint
        // konten menolaknya, dan satu-satunya jalan keluar ikut menolak juga.
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('response_code', 'password_change_required');

        $this->forgetGuards();
        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])->assertOk();
    }

    public function test_content_becomes_reachable_after_the_password_is_changed(): void
    {
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])->assertOk();

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dashboard')->assertOk();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => 'bukan-password-saya',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed')
            ->assertJsonPath('response_data.fields.current_password.0', 'Password saat ini tidak sesuai.');
    }

    public function test_new_password_cannot_equal_the_current_one(): void
    {
        // Tanpa aturan `different`, siswa bisa "mengganti" password ke tanggal
        // lahirnya sendiri: flag terisi, guard lepas, password tetap mudah ditebak.
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => '2008-05-10',
            'password_confirmation' => '2008-05-10',
        ])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed')
            ->assertJsonStructure(['response_data' => ['fields' => ['password']]]);
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'salah-ketik-456',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['response_data' => ['fields' => ['password']]]);
    }

    public function test_short_password_is_rejected(): void
    {
        $this->makeStudent();
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['response_data' => ['fields' => ['password']]]);
    }

    public function test_requires_authentication(): void
    {
        $this->patchJson('/api/v1/profile/password', [
            'current_password' => '2008-05-10',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }
}
