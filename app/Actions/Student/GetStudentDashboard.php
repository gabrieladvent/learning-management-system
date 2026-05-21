<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Foundation\Inspiring;

class GetStudentDashboard
{
    /**
     * @return array{
     *     courses: array<int, array<string, mixed>>,
     *     meta: array{classroom_name: ?string, academic_year: ?string, inspire: string},
     * }
     */
    public function handle(Student $student): array
    {
        $student->loadMissing([
            'classrooms.classroomSubjects.subject',
            'classrooms.classroomSubjects.teacher',
        ]);

        $courses = $student->classrooms
            ->flatMap(fn ($classroom) => $classroom->classroomSubjects->map(fn ($cs) => [
                'id' => $cs->id,
                'subject_name' => $cs->subject?->name,
                'subject_code' => $cs->subject?->code,
                'classroom_name' => $classroom->name,
                'teacher_name' => $cs->teacher?->full_name,
                'semester' => $cs->semester,
                'academic_year' => $cs->academic_year,
            ]))
            ->values()
            ->all();

        $primaryClassroom = $student->classrooms->first();

        return [
            'courses' => $courses,
            'meta' => [
                'classroom_name' => $primaryClassroom?->name,
                'academic_year' => $primaryClassroom?->academic_year,
                'inspire' => $this->cleanQuote(Inspiring::quote()),
            ],
        ];
    }

    private function cleanQuote(string $raw): string
    {
        $stripped = preg_replace('/<[^>]+>/', '', $raw) ?? $raw;

        return trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);
    }
}
