<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassroomSubject;
use App\Models\Enums\ExamModeEnum;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Buku nilai satu course (ClassroomSubject): daftar kolom (assignment + exam),
 * daftar siswa, dan matriks nilai [student_id][column_key] => float|null.
 *
 * Diekstrak dari GradeRecap Page supaya logika agregasi nilai bisa dipakai ulang
 * (Page + Export) dan diuji tanpa Filament/Livewire. Memoized per-instance.
 */
class GradeBook
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $columns = null;

    /** @var Collection<int, Student>|null */
    private ?Collection $students = null;

    /** @var array<string, array<string, float|null>>|null */
    private ?array $matrix = null;

    public function __construct(private ClassroomSubject $course) {}

    /**
     * Kolom dinamis: assignment (urut deadline) lalu exam (urut starts_at).
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $columns = [];

        $assignments = Assignment::query()
            ->whereHas('material', fn ($q) => $q->where('classroom_subject_id', $this->course->id))
            ->orderBy('deadline')
            ->get(['id', 'title', 'max_score']);

        foreach ($assignments as $a) {
            $columns[] = [
                'key' => 'a_'.$a->id,
                'type' => 'assignment',
                'id' => $a->id,
                'label' => $a->title,
                'max_score' => (float) $a->max_score,
            ];
        }

        $exams = Exam::query()
            ->whereHas('material', fn ($q) => $q->where('classroom_subject_id', $this->course->id))
            ->orderBy('starts_at')
            ->get(['id', 'title', 'max_score', 'mode']);

        foreach ($exams as $e) {
            $columns[] = [
                'key' => 'e_'.$e->id,
                'type' => $e->mode === ExamModeEnum::OnlineQuiz ? 'exam_quiz' : 'exam_submission',
                'id' => $e->id,
                'label' => $e->title,
                'max_score' => (float) $e->max_score,
            ];
        }

        return $this->columns = $columns;
    }

    /**
     * Semua siswa kelas (urut nama).
     *
     * @return Collection<int, Student>
     */
    public function students(): Collection
    {
        if ($this->students !== null) {
            return $this->students;
        }

        return $this->students = Student::query()
            ->whereHas('classrooms', fn ($q) => $q->whereKey($this->course->classroom_id))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Matriks nilai [student_id][column_key] => float|null.
     *
     * @return array<string, array<string, float|null>>
     */
    public function matrix(): array
    {
        if ($this->matrix !== null) {
            return $this->matrix;
        }

        $studentIds = $this->students()->pluck('id');
        $columns = $this->columns();

        $matrix = [];

        foreach ($studentIds as $studentId) {
            $matrix[$studentId] = [];
            foreach ($columns as $col) {
                $matrix[$studentId][$col['key']] = null;
            }
        }

        $assignmentIds = collect($columns)->where('type', 'assignment')->pluck('id');
        if ($assignmentIds->isNotEmpty()) {
            AssignmentSubmission::query()
                ->whereIn('assignment_id', $assignmentIds)
                ->whereIn('student_id', $studentIds)
                ->get(['assignment_id', 'student_id', 'score'])
                ->each(function ($s) use (&$matrix) {
                    $matrix[$s->student_id]['a_'.$s->assignment_id] = $s->score !== null ? (float) $s->score : null;
                });
        }

        $quizExamIds = collect($columns)->where('type', 'exam_quiz')->pluck('id');
        if ($quizExamIds->isNotEmpty()) {
            ExamSession::query()
                ->whereIn('exam_id', $quizExamIds)
                ->whereIn('student_id', $studentIds)
                ->get(['exam_id', 'student_id', 'total_score', 'submitted_at'])
                ->each(function ($s) use (&$matrix) {
                    if (! $s->submitted_at) {
                        return;
                    }
                    $matrix[$s->student_id]['e_'.$s->exam_id] = $s->total_score !== null ? (float) $s->total_score : null;
                });
        }

        $submissionExamIds = collect($columns)->where('type', 'exam_submission')->pluck('id');
        if ($submissionExamIds->isNotEmpty()) {
            ExamSubmission::query()
                ->whereIn('exam_id', $submissionExamIds)
                ->whereIn('student_id', $studentIds)
                ->get(['exam_id', 'student_id', 'score'])
                ->each(function ($s) use (&$matrix) {
                    $matrix[$s->student_id]['e_'.$s->exam_id] = $s->score !== null ? (float) $s->score : null;
                });
        }

        return $this->matrix = $matrix;
    }
}
