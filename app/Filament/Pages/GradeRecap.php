<?php

namespace App\Filament\Pages;

use App\Exports\GradeRecapExport;
use App\Models\ClassroomSubject;
use App\Models\Student;
use App\Support\GradeBook;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\Column;
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
     * Buku nilai course terpilih (kolom + siswa + matriks), memoized per request.
     * Logika agregasi dipindah ke App\Support\GradeBook (testable, reusable).
     */
    private ?GradeBook $gradeBook = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function updatedClassroomSubjectId(): void
    {
        $this->gradeBook = null;
        $this->resetTable();
    }

    private function gradeBook(): ?GradeBook
    {
        if ($this->gradeBook !== null) {
            return $this->gradeBook;
        }

        $course = $this->getCourse();

        return $course ? ($this->gradeBook = new GradeBook($course)) : null;
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

    /**
     * Hanya super_admin dan guru (yang punya record Teacher) yang boleh membuka
     * halaman rekap nilai. Tanpa ini, Page tidak punya proteksi policy/route-binding.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('super_admin') || $user?->teacher);
    }

    public function getCourse(): ?ClassroomSubject
    {
        if (! $this->classroomSubjectId) {
            return null;
        }

        // Scope kepemilikan WAJIB di sini — `classroomSubjectId` adalah public
        // Livewire property yang bisa di-set klien ke id course guru lain (IDOR).
        // Dropdown di courseOptions() hanya kosmetik; enforcement ada di query ini.
        $query = ClassroomSubject::query()
            ->with(['classroom', 'subject', 'teacher.user'])
            ->whereKey($this->classroomSubjectId);

        $user = auth()->user();

        if (! $user?->hasRole('super_admin') && $user?->teacher) {
            $query->where('teacher_id', $user->teacher->id);
        }

        return $query->first();
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
     * @return array<int, Column>
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
        return $this->gradeBook()?->matrix()[$studentId][$columnKey] ?? null;
    }

    /**
     * Daftar kolom dinamis (assignment + exam) untuk course terpilih.
     *
     * @return array<int, array{key: string, type: 'assignment'|'exam_quiz'|'exam_submission', id: string, label: string, max_score: float}>
     */
    public function getDynamicColumns(): array
    {
        return $this->gradeBook()?->columns() ?? [];
    }

    /**
     * SEMUA students kelas (untuk export Excel).
     *
     * @return Collection<int, Student>
     */
    public function getAllStudents(): Collection
    {
        return $this->gradeBook()?->students() ?? collect();
    }

    /**
     * Matrix nilai [student_id][column_key] => float|null untuk SEMUA siswa di kelas.
     *
     * @return array<string, array<string, float|null>>
     */
    private function computeGradeMatrix(): array
    {
        return $this->gradeBook()?->matrix() ?? [];
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
