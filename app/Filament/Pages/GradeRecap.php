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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeRecap extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Pengajaran';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'Rekap Nilai';

    protected static ?string $navigationLabel = 'Rekap Nilai';

    protected static string $view = 'filament.pages.grade-recap';

    public ?string $classroomSubjectId = null;

    /**
     * Cache nilai per request supaya tidak query ulang per cell.
     *
     * @var array<string, array<string, float|null>>|null
     */
    private ?array $gradeMatrixCache = null;

    /**
     * Cache daftar kolom dinamis (assignment + exam) per request.
     * Dipanggil banyak kali (Total column closure per baris siswa) — tanpa cache jadi N+1.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $dynamicColumnsCache = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function updatedClassroomSubjectId(): void
    {
        $this->gradeMatrixCache = null;
        $this->dynamicColumnsCache = null;
        $this->resetTable();
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

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->studentsQuery())
            ->columns($this->buildColumns())
            ->paginated([10, 20, 50, 100])
            ->defaultPaginationPageOption(20)
            ->defaultSort('full_name', 'asc')
            ->emptyStateHeading(
                $this->getCourse()
                    ? 'Belum ada siswa terdaftar di kelas ini.'
                    : 'Pilih kelas + mata pelajaran dulu untuk menampilkan rekap.'
            )
            ->emptyStateIcon('heroicon-o-table-cells');
    }

    private function studentsQuery(): Builder
    {
        $course = $this->getCourse();

        if (! $course) {
            return Student::query()->whereRaw('1=0');
        }

        return Student::query()
            ->whereHas('classrooms', fn (Builder $q) => $q->whereKey($course->classroom_id));
    }

    /**
     * @return array<int, \Filament\Tables\Columns\Column>
     */
    private function buildColumns(): array
    {
        $columns = [
            TextColumn::make('nisn')
                ->label('NISN')
                ->searchable()
                ->fontFamily('mono')
                ->size('xs')
                ->color('gray'),

            TextColumn::make('full_name')
                ->label('Nama Siswa')
                ->searchable()
                ->sortable()
                ->weight('medium'),
        ];

        $dynamicColumns = $this->getDynamicColumns();

        foreach ($dynamicColumns as $col) {
            $maxScore = rtrim(rtrim(number_format($col['max_score'], 2, '.', ''), '0'), '.');
            $typeLabel = match ($col['type']) {
                'exam_quiz' => 'quiz',
                'exam_submission' => 'ujian',
                default => 'tugas',
            };

            $columns[] = TextColumn::make($col['key'])
                ->label($col['label'])
                ->description("max {$maxScore} · {$typeLabel}")
                ->alignCenter()
                ->state(fn (Student $record) => $this->gradeFor($record->id, $col['key']))
                ->formatStateUsing(fn ($state) => $state === null ? '—' : $this->formatScore((float) $state))
                ->placeholder('—');
        }

        $columns[] = TextColumn::make('total')
            ->label('Total')
            ->alignCenter()
            ->weight('bold')
            ->color('primary')
            ->state(function (Student $record) {
                $sum = 0.0;
                $hasAny = false;
                foreach ($this->getDynamicColumns() as $c) {
                    $score = $this->gradeFor($record->id, $c['key']);
                    if ($score !== null) {
                        $sum += $score;
                        $hasAny = true;
                    }
                }
                return $hasAny ? $sum : null;
            })
            ->formatStateUsing(fn ($state) => $state === null ? '—' : $this->formatScore((float) $state));

        return $columns;
    }

    private function formatScore(float $score): string
    {
        return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }

    private function gradeFor(string $studentId, string $columnKey): ?float
    {
        $this->gradeMatrixCache ??= $this->computeGradeMatrix();

        return $this->gradeMatrixCache[$studentId][$columnKey] ?? null;
    }

    /**
     * Daftar kolom dinamis: tiap kolom satu assignment / exam.
     * Cached per request supaya Total column closure tidak trigger query ulang per baris siswa.
     *
     * @return array<int, array{key: string, type: 'assignment'|'exam_quiz'|'exam_submission', id: string, label: string, max_score: float}>
     */
    public function getDynamicColumns(): array
    {
        if ($this->dynamicColumnsCache !== null) {
            return $this->dynamicColumnsCache;
        }

        $course = $this->getCourse();
        if (! $course) {
            return $this->dynamicColumnsCache = [];
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

        return $this->dynamicColumnsCache = $columns;
    }

    /**
     * SEMUA students kelas (untuk export Excel).
     *
     * @return Collection<int, Student>
     */
    public function getAllStudents(): Collection
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
     * Matrix nilai [student_id][column_key] => float|null untuk SEMUA siswa di kelas.
     *
     * @return array<string, array<string, float|null>>
     */
    private function computeGradeMatrix(): array
    {
        $course = $this->getCourse();
        if (! $course) {
            return [];
        }

        $studentIds = $this->getAllStudents()->pluck('id');
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

    /**
     * Public accessor untuk view (header summary).
     */
    public function getStudentCount(): int
    {
        return $this->studentsQuery()->count();
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
                $this->getAllStudents(),
                $this->getDynamicColumns(),
                $this->computeGradeMatrix(),
            ),
            $filename,
        );
    }
}
