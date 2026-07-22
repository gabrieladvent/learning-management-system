<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\GradeRecap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class GradeRecapAccessTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    /**
     * Regresi C1 (IDOR): guru tidak boleh membaca course/rekap nilai milik guru lain
     * dengan menyetel classroomSubjectId secara manual.
     */
    public function test_teacher_cannot_resolve_another_teachers_course(): void
    {
        $school = $this->makeSchool();
        $subject = $this->makeSubject();

        $teacherA = $this->makeTeacher();
        $classroomA = $this->makeClassroom($school, $teacherA);
        $csA = $this->makeClassroomSubject($classroomA, $subject, $teacherA);

        $teacherB = $this->makeTeacher();

        $this->actingAs($teacherB->user);

        $page = new GradeRecap;
        $page->classroomSubjectId = $csA->id; // id course milik guru A

        $this->assertNull($page->getCourse(), 'guru B tidak boleh me-resolve course guru A');
    }

    public function test_teacher_can_resolve_own_course(): void
    {
        $school = $this->makeSchool();
        $subject = $this->makeSubject();
        $teacherA = $this->makeTeacher();
        $classroomA = $this->makeClassroom($school, $teacherA);
        $csA = $this->makeClassroomSubject($classroomA, $subject, $teacherA);

        $this->actingAs($teacherA->user);

        $page = new GradeRecap;
        $page->classroomSubjectId = $csA->id;

        $this->assertNotNull($page->getCourse());
        $this->assertSame($csA->id, $page->getCourse()->id);
    }

    public function test_non_teacher_non_admin_cannot_access_page(): void
    {
        $plainUser = User::create([
            'name' => 'Plain', 'email' => 'plain-'.Str::random(6).'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->actingAs($plainUser);
        $this->assertFalse(GradeRecap::canAccess());

        $teacher = $this->makeTeacher();
        $this->actingAs($teacher->user);
        $this->assertTrue(GradeRecap::canAccess());
    }
}
