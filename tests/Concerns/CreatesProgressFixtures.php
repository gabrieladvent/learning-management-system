<?php

namespace Tests\Concerns;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Enums\GenderEnum;
use App\Models\Exam;
use App\Models\Material;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait CreatesProgressFixtures
{
    protected function makeSchool(): School
    {
        return School::create([
            'name' => 'Test School '.Str::random(6),
            'address' => 'Jl. Test 1',
            'phone' => '021000',
            'email' => 'school-'.Str::random(4).'@test.local',
            'is_active' => true,
        ]);
    }

    protected function makeTeacher(?User $user = null): Teacher
    {
        $user ??= User::create([
            'name' => 'Teacher User '.Str::random(4),
            'email' => 'teacher-'.Str::random(8).'@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        return Teacher::create([
            'user_id' => $user->id,
            'full_name' => 'Test Teacher '.Str::random(4),
            'nip' => (string) random_int(1000000000, 9999999999),
            'specialization' => 'Matematika',
            'phone' => '0810',
            'nik' => (string) random_int(1000000000000000, 9999999999999999),
            'birth_date' => '1990-01-01',
            'place_of_birth' => 'Jakarta',
            'gender' => GenderEnum::Male->value,
        ]);
    }

    protected function makeSubject(): Subject
    {
        return Subject::create([
            'name' => 'Mata Uji '.Str::random(4),
            'code' => strtoupper(Str::random(4)),
            'description' => 'Test',
        ]);
    }

    protected function makeClassroom(School $school, ?Teacher $teacher = null): Classroom
    {
        return Classroom::create([
            'school_id' => $school->id,
            'teacher_id' => $teacher?->id,
            'name' => 'X TEST '.Str::random(3),
            'grade_level' => '10',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }

    protected function makeClassroomSubject(Classroom $classroom, Subject $subject, Teacher $teacher): ClassroomSubject
    {
        return ClassroomSubject::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026/2027',
            'semester' => 1,
        ]);
    }

    protected function makeStudent(School $school, ?Classroom $classroom = null, array $overrides = []): Student
    {
        $student = Student::create(array_merge([
            'school_id' => $school->id,
            'nisn' => (string) random_int(1000000000, 9999999999),
            'full_name' => 'Test Student '.Str::random(4),
            'class' => $classroom?->name,
            'gender' => GenderEnum::Male->value,
            'place_of_birth' => 'Jakarta',
            'birth_date' => '2008-01-01',
            'is_active' => true,
            'tracking_opt_out' => false,
        ], $overrides));

        if ($classroom) {
            $classroom->students()->attach($student->id, ['enrolled_at' => now()]);
        }

        return $student;
    }

    protected function makeMaterial(ClassroomSubject $cs, array $overrides = []): Material
    {
        return Material::create(array_merge([
            'classroom_subject_id' => $cs->id,
            'title' => 'Material '.Str::random(4),
            'description' => 'Desc',
            'content' => '<p>Hello</p>',
            'is_published' => true,
            'available_from' => Carbon::now()->subDay(),
        ], $overrides));
    }

    protected function makeAssignment(Material $material, array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'material_id' => $material->id,
            'title' => 'Assignment '.Str::random(4),
            'description' => 'Desc',
            'deadline' => Carbon::now()->addWeek(),
            'max_score' => 100,
            'is_published' => true,
            'available_from' => Carbon::now()->subDay(),
        ], $overrides));
    }

    protected function makeExam(Material $material, array $overrides = []): Exam
    {
        return Exam::create(array_merge([
            'material_id' => $material->id,
            'title' => 'Exam '.Str::random(4),
            'description' => 'Desc',
            'mode' => 'online_quiz',
            'starts_at' => Carbon::now()->addDay(),
            'duration_minutes' => 60,
            'max_score' => 100,
            'shuffle_questions' => false,
            'status' => 'draft',
            'is_published' => true,
            'available_from' => Carbon::now()->subDay(),
        ], $overrides));
    }

    /**
     * One-call scaffold for the most common test scenario.
     *
     * @return array{student:Student, material:Material, classroomSubject:ClassroomSubject, classroom:Classroom}
     */
    protected function scaffoldStudentWithMaterial(array $studentOverrides = []): array
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $subject = $this->makeSubject();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $subject, $teacher);
        $material = $this->makeMaterial($cs);
        $student = $this->makeStudent($school, $classroom, $studentOverrides);

        return [
            'student' => $student,
            'material' => $material,
            'classroomSubject' => $cs,
            'classroom' => $classroom,
        ];
    }
}
