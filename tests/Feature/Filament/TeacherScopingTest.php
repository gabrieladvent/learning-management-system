<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AssignmentResource;
use App\Filament\Resources\CourseProgressResource;
use App\Filament\Resources\CourseResource;
use App\Filament\Resources\ExamResource;
use App\Filament\Resources\MaterialResource;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Jaring pengaman isolasi data antar-guru (M5/H10). getEloquentQuery tiap
 * Resource "Pengajaran" harus meng-scope ke guru yang login. Test ini mengunci
 * perilaku SEBELUM refactor trait — supaya refactor tidak diam-diam membocorkan
 * data guru lain.
 */
class TeacherScopingTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    /** @var array<string,mixed> */
    private array $a;

    /** @var array<string,mixed> */
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');

        $this->a = $this->makeCourseTree();
        $this->b = $this->makeCourseTree();
    }

    /** @return array{teacher:Teacher, cs, material, exam, assignment} */
    private function makeCourseTree(): array
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);
        $exam = $this->makeExam($material);
        $assignment = $this->makeAssignment($material);

        return compact('teacher', 'cs', 'material', 'exam', 'assignment');
    }

    public function test_teacher_only_sees_own_records(): void
    {
        $this->actingAs($this->a['teacher']->user);

        $this->assertSame([$this->a['exam']->id], ExamResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$this->a['material']->id], MaterialResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$this->a['assignment']->id], AssignmentResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$this->a['cs']->id], CourseResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$this->a['cs']->id], CourseProgressResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_super_admin_sees_all_records(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.Str::random(6).'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $this->assertEqualsCanonicalizing(
            [$this->a['exam']->id, $this->b['exam']->id],
            ExamResource::getEloquentQuery()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$this->a['cs']->id, $this->b['cs']->id],
            CourseResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    public function test_non_teacher_non_admin_sees_nothing(): void
    {
        $plain = User::create([
            'name' => 'Plain', 'email' => 'plain-'.Str::random(6).'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->actingAs($plain);

        $this->assertCount(0, ExamResource::getEloquentQuery()->get());
        $this->assertCount(0, MaterialResource::getEloquentQuery()->get());
        $this->assertCount(0, AssignmentResource::getEloquentQuery()->get());
        $this->assertCount(0, CourseResource::getEloquentQuery()->get());
    }
}
