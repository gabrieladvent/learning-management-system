<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Foundation\Inspiring;

class GetStudentDashboard
{
    /**
     * @return array{
     *     courses: array<int, array<string, mixed>>,
     *     stats: array<string, mixed>,
     *     meta: array{
     *         classroom_name: ?string,
     *         academic_year: ?string,
     *         homeroom_teacher_name: ?string,
     *         semester: ?int,
     *         inspire: string,
     *     },
     * }
     */
    public function handle(Student $student): array
    {
        $student->loadMissing([
            'classrooms.teacher',
            'classrooms.classroomSubjects.subject',
            'classrooms.classroomSubjects.teacher',
            'pinnedClassroomSubjects:id',
        ]);

        $pinnedIds = $student->pinnedClassroomSubjects->pluck('id')->all();

        $courses = $student->classrooms
            ->flatMap(fn ($classroom) => $classroom->classroomSubjects->map(fn ($cs) => [
                'id' => $cs->id,
                'subject_name' => $cs->subject?->name,
                'subject_code' => $cs->subject?->code,
                'classroom_name' => $classroom->name,
                'teacher_name' => $cs->teacher?->full_name,
                'semester' => $cs->semester,
                'academic_year' => $cs->academic_year,
                'is_pinned' => in_array($cs->id, $pinnedIds, true),
            ]))
            ->sortByDesc('is_pinned')
            ->values()
            ->all();

        $primaryClassroom = $student->classrooms->first();
        $activeSemester = $primaryClassroom?->classroomSubjects->max('semester');

        return [
            'courses' => $courses,
            'stats' => app(GetStudentStats::class)->handle($student),
            'meta' => [
                'classroom_name' => $primaryClassroom?->name,
                'academic_year' => $primaryClassroom?->academic_year,
                'homeroom_teacher_name' => $primaryClassroom?->teacher?->full_name,
                'semester' => $activeSemester !== null ? (int) $activeSemester : null,
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
