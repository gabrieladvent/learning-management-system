<?php

namespace Tests\Feature\Student;

use App\Actions\Student\RegisterStudent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class StudentActiveMiddlewareTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
    }

    private function registerStudent(array $overrides = []): Student
    {
        $school = $this->makeSchool();

        return app(RegisterStudent::class)->handle(array_merge([
            'full_name' => 'Budi', 'school_id' => $school->id, 'nisn' => '1234567890',
            'gender' => 'male', 'birth_date' => '2008-05-10', 'is_active' => true,
        ], $overrides));
    }

    /**
     * H2: siswa yang di-nonaktifkan dilogout & diblok pada request berikutnya.
     */
    public function test_deactivated_student_is_blocked_and_redirected_to_login(): void
    {
        $student = $this->registerStudent();
        $student->user->update(['password_changed_at' => now()]); // lewati gate H1
        $student->update(['is_active' => false]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.login'));

        $this->assertGuest('student');
    }

    /**
     * H1: siswa dengan password default (password_changed_at null) dipaksa ke
     * halaman profil untuk ganti password sebelum bisa akses fitur lain.
     */
    public function test_student_with_default_password_is_forced_to_change_it(): void
    {
        $student = $this->registerStudent(); // password_changed_at = null

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.profile.edit'));
    }
}
