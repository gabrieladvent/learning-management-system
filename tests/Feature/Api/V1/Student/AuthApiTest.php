<?php

namespace Tests\Feature\Api\V1\Student;

use App\Actions\Student\RegisterStudent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
        RateLimiter::clear('student-login:1234567890|127.0.0.1');
    }

    /**
     * Guard auth di-cache per proses; dalam satu test, request kedua akan
     * memakai user yang sudah di-resolve dan MELEWATI pengecekan token di DB.
     * Di produksi tiap request proses baru, jadi ini murni artefak test —
     * dibuktikan oleh test_deleted_token_is_rejected_on_a_fresh_request().
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function makeStudent(array $overrides = []): Student
    {
        return app(RegisterStudent::class)->handle(array_merge([
            'full_name' => 'Budi Santoso',
            'school_id' => $this->makeSchool()->id,
            'nisn' => '1234567890',
            'class' => 'X IPA 1',
            'gender' => 'male',
            'birth_date' => '2008-05-10',
            'is_active' => true,
        ], $overrides));
    }

    public function test_login_returns_token_in_envelope(): void
    {
        $this->makeStudent();

        $response = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => '2008-05-10',
            'device_name' => 'Pixel 7a',
        ]);

        $response->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.must_change_password', true)
            ->assertJsonPath('response_data.student.nisn', '1234567890')
            ->assertJsonStructure([
                'response_code',
                'response_message',
                'response_data' => ['token', 'must_change_password', 'student'],
            ]);

        $this->assertNotEmpty($response->json('response_data.token'));
    }

    public function test_token_actually_resolves_the_student(): void
    {
        // Regresi: migrasi bawaan Sanctum memakai morphs() (bigint) sedangkan
        // students ber-PK uuid. Kalau tokenable_id bukan uuidMorphs, token
        // tersimpan tapi TIDAK PERNAH cocok — gagal diam-diam.
        $this->makeStudent();

        $token = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => '2008-05-10',
        ])->json('response_data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.student.nisn', '1234567890');
    }

    public function test_wrong_password_returns_validation_envelope(): void
    {
        $this->makeStudent();

        $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => 'salah-banget',
        ])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed')
            ->assertJsonPath('response_message', 'NISN atau password salah.')
            ->assertJsonPath('response_data.fields.nisn.0', 'NISN atau password salah.');
    }

    public function test_unknown_nisn_gives_identical_message_to_wrong_password(): void
    {
        // Membedakan keduanya = membocorkan NISN mana yang terdaftar.
        $this->makeStudent();

        $unknown = $this->postJson('/api/v1/auth/login', [
            'nisn' => '9999999999',
            'password' => 'apa-saja',
        ]);

        $unknown->assertStatus(422)
            ->assertJsonPath('response_message', 'NISN atau password salah.');
    }

    public function test_inactive_student_cannot_login(): void
    {
        $this->makeStudent(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => '2008-05-10',
        ])->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed');
    }

    public function test_student_deactivated_after_login_is_rejected_with_account_inactive(): void
    {
        $student = $this->makeStudent();

        $token = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890',
            'password' => '2008-05-10',
        ])->json('response_data.token');

        $student->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('response_code', 'account_inactive');
    }

    public function test_request_without_token_returns_unauthenticated_code(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $this->makeStudent();

        $phone = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890', 'password' => '2008-05-10', 'device_name' => 'Pixel 7a',
        ])->json('response_data.token');

        $tablet = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890', 'password' => '2008-05-10', 'device_name' => 'iPad',
        ])->json('response_data.token');

        $this->withToken($phone)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonMissingPath('response_data');

        $this->assertSame(['iPad'], PersonalAccessToken::pluck('name')->all());

        $this->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->forgetGuards();
        $this->withToken($tablet)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->makeStudent();

        // Password WAJIB >= 6 karakter: kalau lebih pendek, gagal di
        // $request->validate() sebelum blok try — RateLimiter::hit() tak jalan.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'nisn' => '1234567890', 'password' => 'salah-banget',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890', 'password' => '2008-05-10',
        ])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed')
            ->assertJsonFragment(['response_message' => 'Terlalu banyak percobaan login. Coba lagi dalam 60 detik.']);
    }

    public function test_missing_fields_return_validation_envelope(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed')
            ->assertJsonStructure(['response_data' => ['fields' => ['nisn', 'password']]]);
    }

    public function test_deleted_token_is_rejected_on_a_fresh_request(): void
    {
        $this->makeStudent();

        $token = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890', 'password' => '2008-05-10',
        ])->json('response_data.token');

        PersonalAccessToken::query()->delete();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }

    public function test_token_gets_expiry_thirty_days_out(): void
    {
        $this->makeStudent();

        $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890', 'password' => '2008-05-10',
        ])->assertOk();

        $token = PersonalAccessToken::firstOrFail();

        $this->assertNotNull($token->expires_at, 'Token harus punya expires_at.');
        $this->assertEqualsWithDelta(
            30 * 24 * 60,
            now()->diffInMinutes($token->expires_at, absolute: true),
            5,
        );
    }

    public function test_token_expiry_slides_forward_when_used(): void
    {
        // ADR-0004: 30 hari sejak pemakaian TERAKHIR, bukan sejak dibuat.
        $this->makeStudent();

        $token = $this->postJson('/api/v1/auth/login', [
            'nisn' => '1234567890', 'password' => '2008-05-10',
        ])->json('response_data.token');

        // Mundurkan expiry seolah token dibuat 10 hari lalu.
        $stored = PersonalAccessToken::firstOrFail();
        $stored->forceFill(['expires_at' => now()->addDays(20)])->save();

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertEqualsWithDelta(
            30 * 24 * 60,
            now()->diffInMinutes($stored->fresh()->expires_at, absolute: true),
            5,
            'Token yang dipakai harus diperpanjang jadi 30 hari lagi.',
        );
    }

    public function test_unknown_api_route_returns_not_found_envelope(): void
    {
        $this->getJson('/api/v1/tidak-ada')
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }
}
