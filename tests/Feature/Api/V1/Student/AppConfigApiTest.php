<?php

namespace Tests\Feature\Api\V1\Student;

use Tests\TestCase;


class AppConfigApiTest extends TestCase
{
    private function configure(
        ?string $min = null,
        ?string $latest = null,
        ?string $android = null,
        ?string $ios = null,
    ): void {
        config()->set('mobile_app', [
            'min_version' => $min,
            'latest_version' => $latest,
            'store_url' => ['android' => $android, 'ios' => $ios],
        ]);
    }

    public function test_app_config_is_reachable_without_a_token(): void
    {
        // Dipanggil saat cold start, sebelum siswa login.
        $this->configure(min: '1.4.0', latest: '1.6.1', android: 'https://play.test/app');

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJson([
                'response_code' => 'success',
                'response_data' => [
                    'min_version' => '1.4.0',
                    'latest_version' => '1.6.1',
                    'store_url' => 'https://play.test/app',
                ],
            ]);
    }

    public function test_store_url_follows_the_client_platform_header(): void
    {
        $this->configure(android: 'https://play.test/app', ios: 'https://apps.test/app');

        $this->getJson('/api/v1/app-config', ['X-Client-Platform' => 'ios'])
            ->assertOk()
            ->assertJsonPath('response_data.store_url', 'https://apps.test/app');

        $this->getJson('/api/v1/app-config', ['X-Client-Platform' => 'android'])
            ->assertJsonPath('response_data.store_url', 'https://play.test/app');

        // Tanpa header, Android jadi bawaan — mayoritas siswa memakainya, dan
        // tautan yang mungkin salah lebih berguna daripada tidak ada tautan.
        $this->getJson('/api/v1/app-config')
            ->assertJsonPath('response_data.store_url', 'https://play.test/app');
    }

    public function test_unconfigured_fields_are_null_not_missing(): void
    {
        // Klien membaca field yang sama setiap kali; bentuk payload tidak boleh
        // berubah hanya karena config belum diisi.
        $this->configure();

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJson([
                'response_data' => [
                    'min_version' => null,
                    'latest_version' => null,
                    'store_url' => null,
                ],
            ]);
    }

    public function test_client_below_minimum_is_rejected_with_426(): void
    {
        $this->configure(min: '1.5.0', android: 'https://play.test/app');

        $this->postJson('/api/v1/auth/login', [], ['X-Client-Version' => '1.4.9+120'])
            ->assertStatus(426)
            ->assertJson([
                'response_code' => 'client_too_old',
                'response_data' => [
                    'min_version' => '1.5.0',
                    'store_url' => 'https://play.test/app',
                ],
            ]);
    }

    public function test_rejection_happens_before_validation_and_throttling(): void
    {
        // Body kosong biasanya 422. Versi terlalu tua harus menang duluan —
        // kalau tidak, klien lama menampilkan error form yang membingungkan
        // alih-alih layar "perbarui aplikasi".
        $this->configure(min: '1.5.0');

        $this->postJson('/api/v1/auth/login', [], ['X-Client-Version' => '1.0.0+1'])
            ->assertStatus(426);
    }

    public function test_version_is_compared_per_segment_not_as_text(): void
    {
        // Perbandingan teks bilang '1.10.0' < '1.9.0'. Di sinilah bug versi
        // biasanya bersembunyi.
        $this->configure(min: '1.9.0');

        $this->postJson('/api/v1/auth/login', [], ['X-Client-Version' => '1.10.0+5'])
            ->assertStatus(422);
    }

    public function test_build_number_is_ignored(): void
    {
        $this->configure(min: '1.5.0');

        $this->postJson('/api/v1/auth/login', [], ['X-Client-Version' => '1.5.0+1'])
            ->assertStatus(422);
    }

    public function test_app_config_is_never_blocked_by_the_version_gate(): void
    {
        // Endpoint yang memberi tahu cara memperbarui harus tetap terjawab,
        // justru saat versinya sudah ditolak.
        $this->configure(min: '9.0.0', android: 'https://play.test/app');

        $this->getJson('/api/v1/app-config', ['X-Client-Version' => '1.0.0+1'])
            ->assertOk()
            ->assertJsonPath('response_data.min_version', '9.0.0');
    }

    public function test_gate_fails_open_when_it_cannot_decide(): void
    {
        // Tiga keadaan yang TIDAK boleh mengunci siapa pun. Kalau salah satu
        // berubah jadi 426, satu typo di env bisa mematikan aplikasi untuk
        // seluruh sekolah.
        $this->configure(min: '1.5.0');

        // 1. Header tidak dikirim (curl, monitoring, test manual).
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422);

        // 2. Versi tidak bisa dibaca.
        $this->postJson('/api/v1/auth/login', [], ['X-Client-Version' => 'versi-lama'])
            ->assertStatus(422);

        // 3. min_version belum diisi.
        $this->configure();
        $this->postJson('/api/v1/auth/login', [], ['X-Client-Version' => '0.0.1+1'])
            ->assertStatus(422);
    }
}
