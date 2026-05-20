<?php

namespace App\Filament\Pages;

use App\Exports\GradeRecapExport;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassroomSubject;
use App\Models\Enums\ExamModeEnum;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeRecap extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Pengajaran';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'Rekap Nilai';

    protected static ?string $navigationLabel = 'Rekap Nilai';

    protected static string $view = 'filament.pages.grade-recap';

    public ?string $classroomSubjectId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('classroomSubjectId')
                ->label('Kelas + Mata Pelajaran')
                ->options(fn () => $this->courseOptions())
                ->searchable()
                ->preload()
                ->placeholder('Pilih kelas + mata pelajaran...')
                ->live(),
        ]);
    }

    protected function courseOptions(): array
    {
        $query = ClassroomSubject::query()
            ->with(['classroom', 'subject', 'teacher']);

        $user = auth()->user();

        if (! $user?->hasRole('super_admin') && $user?->teacher) {
            $query->where('teacher_id', $user->teacher->id);
        }

        return $query->get()
            ->mapWithKeys(fn (ClassroomSubject $cs) => [
                $cs->id => sprintf(
                    '%s · %s (%s sem %s)',
                    $cs->classroom?->name ?? '—',
                    $cs->subject?->name ?? '—',
                    $cs->academic_year ?? '—',
                    $cs->semester ?? '—',
                ),
            ])
            ->toArray();
    }

    public function getCourse(): ?ClassroomSubject
    {
        if (! $this->classroomSubjectId) {
            return null;
        }

        return ClassroomSubject::query()
            ->with(['classroom', 'subject', 'teacher.user'])
            ->find($this->classroomSubjectId);
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        $course = $this->getCourse();
        if (! $course) {
            return collect();
        }

        return Student::query()
            ->whereHas('classrooms', fn ($q) => $q->whereKey($course->classroom_id))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Daftar kolom dinamis: tiap kolom satu assignment / exam.
     *
     * @return array<int, array{key: string, type: 'assignment'|'exam_quiz'|'exam_submission', id: string, label: string, max_score: float}>
     */
    public function getDynamicColumns(): array
    {
        $course = $this->getCourse();
        if (! $course) {
            return [];
        }

        $columns = [];

        $assignments = Assignment::query()
            ->whereHas('material', fn ($q) => $q->where('classroom_subject_id', $course->id))
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
            ->whereHas('material', fn ($q) => $q->where('classroom_subject_id', $course->id))
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

        return $columns;
    }

    /**
     * Matrix nilai [student_id][column_key] => float|null.
     *
     * @return array<string, array<string, float|null>>
     */
    public function getGradeMatrix(): array
    {
        $course = $this->getCourse();
        if (! $course) {
            return [];
        }

        $studentIds = $this->getStudents()->pluck('id');
        $columns = $this->getDynamicColumns();

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

        return $matrix;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $this->classroomSubjectId !== null)
                ->action(fn () => $this->exportExcel()),
        ];
    }

    public function exportExcel(): ?BinaryFileResponse
    {
        $course = $this->getCourse();
        if (! $course) {
            return null;
        }

        $filename = sprintf(
            'rekap-nilai-%s-%s-%s.xlsx',
            str()->slug($course->classroom?->name ?? 'kelas'),
            str()->slug($course->subject?->name ?? 'mapel'),
            now()->format('Ymd-His'),
        );

        return Excel::download(
            new GradeRecapExport(
                $course,
                $this->getStudents(),
                $this->getDynamicColumns(),
                $this->getGradeMatrix(),
            ),
            $filename,
        );
    }
}
