<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class ClassroomSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = Teacher::first();
        if (! $teacher) {
            return;
        }

        // Cari mapel sesuai spesialisasi guru, fallback ke Matematika
        $subject = Subject::where('name', $teacher->specialization)
            ->orWhere('name', 'Matematika')
            ->first();

        if (! $subject) {
            return;
        }

        $academicYear = date('Y').'/'.(date('Y') + 1);

        // Assign mapel ke beberapa kelas untuk semester 1
        $classrooms = Classroom::take(3)->get();

        foreach ($classrooms as $classroom) {
            ClassroomSubject::firstOrCreate(
                [
                    'classroom_id' => $classroom->id,
                    'subject_id' => $subject->id,
                    'academic_year' => $academicYear,
                    'semester' => 1,
                ],
                [
                    'teacher_id' => $teacher->id,
                ]
            );
        }
    }
}
