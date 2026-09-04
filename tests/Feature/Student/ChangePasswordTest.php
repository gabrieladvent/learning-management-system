<?php

namespace Tests\Feature\Student;

use App\Actions\Student\RegisterStudent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Jalur WEB untuk ganti password siswa.
 *
 * Padanan API-nya ada di Tests\Feature\Api\V1\Student\ProfileApiTest. Aturan
 * validasinya sengaja dijaga sama di kedua berkas: kalau salah satu longgar,
 * siswa tinggal memakai jalur yang lebih longgar itu.
 */
class ChangePasswordTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
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

    public function test_student_can_change_password(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student, 'student')
            ->patch(route('student.profile.password'), [
                'current_password' => '2008-05-10',
                'password' => 'rahasia-baru-123',
                'password_confirmation' => 'rahasia-baru-123',
            ])
            ->assertSessionHasNoErrors();

        $user = $student->fresh()->user;

        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('rahasia-baru-123', $user->password));
    }

    public function test_new_password_cannot_equal_the_current_one(): void
    {
        // Tanpa aturan `different`, ini LOLOS: password_changed_at terisi,
        // gate ganti-password lepas, tapi password siswa tetap tanggal lahirnya
        // yang bisa ditebak siapa pun yang mengenalnya.
        $student = $this->makeStudent();

        $this->actingAs($student, 'student')
            ->patch(route('student.profile.password'), [
                'current_password' => '2008-05-10',
                'password' => '2008-05-10',
                'password_confirmation' => '2008-05-10',
            ])
            ->assertSessionHasErrors('password');

        $this->assertNull(
            $student->fresh()->user->password_changed_at,
            'Gate ganti-password TIDAK boleh lepas kalau passwordnya tidak benar-benar berubah.',
        );
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student, 'student')
            ->patch(route('student.profile.password'), [
                'current_password' => 'bukan-password-saya',
                'password' => 'rahasia-baru-123',
                'password_confirmation' => 'rahasia-baru-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($student->fresh()->user->password_changed_at);
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student, 'student')
            ->patch(route('student.profile.password'), [
                'current_password' => '2008-05-10',
                'password' => 'rahasia-baru-123',
                'password_confirmation' => 'salah-ketik-456',
            ])
            ->assertSessionHasErrors('password');
    }
}
