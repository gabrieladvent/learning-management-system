<?php

namespace App\Actions\Student;

use App\Models\AssignmentSubmission;
use App\Models\ExamSession;
use App\Models\Student;

class GetStudentProgress
{
    /**
     * Ringkasan progress belajar siswa: statistik keseluruhan + progress per mata pelajaran.
     *
     * @return array{
     *     stats: array<string, mixed>,
     *     courses: array<int, array<string, mixed>>,
     * }
     */
    public function handle(Student $student): array
    {
        $student->loadMissing([
            'classrooms.classroomSubjects.subject',
            'classrooms.classroomSubjects.teacher',
            'classrooms.classroomSubjects.materials.assignments',
            'classrooms.classroomSubjects.materials.exams',
        ]);

        $completedAssignmentIds = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->pluck('assignment_id')
            ->all();

        $completedExamIds = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->pluck('exam_id')
            ->unique()
            ->all();

        $courses = $student->classrooms
            ->flatMap(fn ($classroom) => $classroom->classroomSubjects->map(fn ($cs) => [
                'classroom' => $classroom,
                'cs' => $cs,
            ]))
            ->unique(fn ($item) => $item['cs']->id)
            ->map(function ($item) use ($completedAssignmentIds, $completedExamIds) {
                $classroom = $item['classroom'];
                $cs = $item['cs'];

                $assignments = $cs->materials->flatMap->assignments->where('is_published', true);
                $exams = $cs->materials->flatMap->exams->where('is_published', true);

                $totalAssignments = $assignments->count();
                $doneAssignments = $assignments->whereIn('id', $completedAssignmentIds)->count();
                $totalExams = $exams->count();
                $doneExams = $exams->whereIn('id', $completedExamIds)->count();

                $total = $totalAssignments + $totalExams;
                $done = $doneAssignments + $doneExams;

                return [
                    'id' => $cs->id,
                    'subject_name' => $cs->subject?->name,
                    'subject_code' => $cs->subject?->code,
                    'classroom_name' => $classroom->name,
                    'teacher_name' => $cs->teacher?->full_name,
                    'assignments_total' => $totalAssignments,
                    'assignments_completed' => $doneAssignments,
                    'exams_total' => $totalExams,
                    'exams_completed' => $doneExams,
                    'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
                ];
            })
            ->sortByDesc('progress_percent')
            ->values()
            ->all();

        return [
            'stats' => app(GetStudentStats::class)->handle($student),
            'courses' => $courses,
        ];
    }
}
