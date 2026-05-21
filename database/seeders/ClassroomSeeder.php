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
        if (! $school) {
            return;
        }

        $academicYear = date('Y').'/'.(date('Y') + 1);

        // Wali kelas: dipasangkan berdasarkan spesialisasi guru, supaya saat
        // siswa diundang ke kelas, wali kelas-nya sudah jelas.
        $homeroomA = Teacher::where('specialization', 'Matematika')->first()
            ?? Teacher::first();
        $homeroomB = Teacher::where('specialization', 'Bahasa Indonesia')->first()
            ?? Teacher::first();

        if (! $homeroomA || ! $homeroomB) {
            return;
        }

        $classes = [
            ['name' => 'X IPA 1', 'grade_level' => 'X', 'teacher_id' => $homeroomA->id],
            ['name' => 'X IPA 2', 'grade_level' => 'X', 'teacher_id' => $homeroomB->id],
        ];

        foreach ($classes as $class) {
            Classroom::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'name' => $class['name'],
                    'academic_year' => $academicYear,
                ],
                [
                    'teacher_id' => $class['teacher_id'],
                    'grade_level' => $class['grade_level'],
                    'is_active' => true,
                ]
            );
        }
    }
}
