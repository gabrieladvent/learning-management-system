<?php

namespace App\Actions\Student;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GetStudentStats
{
    /**
     * Quick stats personal siswa untuk dashboard:
     *  - assignments_pending: jumlah tugas aktif yang belum submit (deadline >= now)
     *  - assignments_completed: jumlah submission yang punya `submitted_at`
     *  - exams_completed: jumlah exam_session yang `submitted_at`
     *  - avg_score: rata-rata score submission yang sudah dinilai
     *  - upcoming_exam: detail ujian terdekat (starts_at) untuk countdown
     *
     * @return array{
     *     assignments_pending: int,
     *     assignments_completed: int,
     *     exams_completed: int,
     *     avg_score: float|null,
     *     upcoming_exam: array<string, mixed>|null,
     * }
     */
    public function handle(Student $student): array
    {
        $classroomIds = $student->classrooms()->pluck('classrooms.id')->all();
        $now = Carbon::now();

        $assignmentsPending = Assignment::query()
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', $now))
            ->where(fn (Builder $q) => $q->whereNull('deadline')->orWhere('deadline', '>=', $now))
            ->whereHas('material.classroomSubject.classroom', fn (Builder $q) => $q->whereIn('id', $classroomIds))
            ->whereDoesntHave('submissions', fn (Builder $q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at'))
            ->count();

        $assignmentsCompleted = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->count();

        $examsCompleted = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->count();

        $avgScore = AssignmentSubmission::query()
            ->where('student_id', $student->id)
            ->whereNotNull('score')
            ->avg('score');

        $upcomingExam = Exam::query()
            ->where('is_published', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', $now)
            ->whereHas('material.classroomSubject.classroom', fn (Builder $q) => $q->whereIn('id', $classroomIds))
            ->whereDoesntHave('sessions', fn (Builder $q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at'))
            ->with('material.classroomSubject.subject', 'material')
            ->orderBy('starts_at')
            ->first();

        return [
            'assignments_pending' => $assignmentsPending,
            'assignments_completed' => $assignmentsCompleted,
            'exams_completed' => $examsCompleted,
            'avg_score' => $avgScore !== null ? round((float) $avgScore, 2) : null,
            'upcoming_exam' => $upcomingExam ? [
                'id' => $upcomingExam->id,
                'title' => $upcomingExam->title,
                'subject_name' => $upcomingExam->material?->classroomSubject?->subject?->name,
                'starts_at' => $upcomingExam->starts_at?->toIso8601String(),
                'duration_minutes' => $upcomingExam->duration_minutes,
                'url' => $upcomingExam->material
                    ? route('student.exams.show', [
                        'material' => $upcomingExam->material->id,
                        'exam' => $upcomingExam->id,
                    ])
                    : null,
            ] : null,
        ];
    }
}
