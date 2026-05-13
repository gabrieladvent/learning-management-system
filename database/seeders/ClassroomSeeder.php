<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\School;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        $teacher = Teacher::first();

        if (! $school || ! $teacher) {
            return;
        }

        $academicYear = date('Y').'/'.(date('Y') + 1);

        $classes = [
            ['name' => 'X IPA 1',  'grade_level' => 'X'],
            ['name' => 'X IPA 2',  'grade_level' => 'X'],
            ['name' => 'XI IPA 1', 'grade_level' => 'XI'],
            ['name' => 'XII IPA 1', 'grade_level' => 'XII'],
        ];

        foreach ($classes as $class) {
            Classroom::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'name' => $class['name'],
                    'academic_year' => $academicYear,
                ],
                [
                    'teacher_id' => $teacher->id,
                    'grade_level' => $class['grade_level'],
                    'is_active' => true,
                ]
            );
        }
    }
}
