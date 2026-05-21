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
        // 3 mata pelajaran inti untuk skenario penelitian — masing-masing
        // dipegang guru sesuai spesialisasinya, dan diberikan ke kedua kelas.
        $subjectCodes = ['MTK', 'BIND', 'BING'];

        $subjects = Subject::whereIn('code', $subjectCodes)->get()->keyBy('code');
        $teachers = Teacher::all()->keyBy('specialization');
        $classrooms = Classroom::whereIn('name', ['X IPA 1', 'X IPA 2'])->get();

        if ($classrooms->isEmpty() || $subjects->isEmpty() || $teachers->isEmpty()) {
            return;
        }

        $academicYear = date('Y').'/'.(date('Y') + 1);

        // Map: subject code → spesialisasi guru pemegang mapel.
        $teacherForSubject = [
            'MTK' => 'Matematika',
            'BIND' => 'Bahasa Indonesia',
            'BING' => 'Bahasa Inggris',
        ];

        foreach ($classrooms as $classroom) {
            foreach ($subjectCodes as $code) {
                $subject = $subjects->get($code);
                $teacher = $teachers->get($teacherForSubject[$code]) ?? $teachers->first();

                if (! $subject || ! $teacher) {
                    continue;
                }

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
}
