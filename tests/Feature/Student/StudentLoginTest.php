<?php

namespace Tests\Feature\Student;

use App\Actions\Student\RegisterStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class StudentLoginTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
    }

    /**
     * Regresi H3: siswa yang dibuat lewat RegisterStudent (jalur yang kini juga
     * dipakai StudentImport) mendapat User + role + password dan BISA login.
     */
    public function test_registered_student_gets_user_role_and_can_login(): void
    {
        $school = $this->makeSchool();

        $student = app(RegisterStudent::class)->handle([
            'full_name' => 'Budi Santoso',
            'school_id' => $school->id,
            'nisn' => '1234567890',
            'gender' => 'male',
            'birth_date' => '2008-05-10',
            'is_active' => true,
        ]);

        // User terbentuk + role student
        $this->assertInstanceOf(User::class, $student->user);
        $this->assertTrue($student->user->hasRole('student'));

        // Login pakai NISN + password default (birth_date Y-m-d)
        $response = $this->post(route('student.login.attempt'), [
            'nisn' => '1234567890',
            'password' => '2008-05-10',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $school = $this->makeSchool();
        app(RegisterStudent::class)->handle([
            'full_name' => 'Siti', 'school_id' => $school->id, 'nisn' => '9999999999',
            'gender' => 'female', 'birth_date' => '2009-01-01', 'is_active' => true,
        ]);

        $response = $this->from(route('student.login'))->post(route('student.login.attempt'), [
            'nisn' => '9999999999',
            'password' => 'salah-password',
        ]);

        $response->assertSessionHasErrors('nisn');
        $this->assertGuest('student');
    }
}
