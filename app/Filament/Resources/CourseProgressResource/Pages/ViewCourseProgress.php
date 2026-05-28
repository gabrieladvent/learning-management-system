<?php

namespace App\Filament\Resources\CourseProgressResource\Pages;

use App\Filament\Resources\CourseProgressResource;
use App\Filament\Resources\CourseProgressResource\Actions\ExportProgressAction;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassroomSubject;
use App\Models\Exam;
use App\Models\Material;
use App\Models\Student;
use App\Support\LearningProgressMetrics;
use App\Support\MaterialCompletion;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Level 2 — list siswa di 1 mata pelajaran (ringkas).
 *
 * Kolom sengaja minimal (nama, %, durasi, status risiko). Detail lengkap per siswa
 * ada di Level 3 (ViewStudentProgress) lewat tombol "Lihat Detail".
 *
 * Perf: agregat per-siswa di-precompute SEKALI di mount() pakai GROUP BY query;
 * kolom tabel hanya lookup map (0 query/row). Konstan terhadap jumlah siswa.
 */
class ViewCourseProgress extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CourseProgressResource::class;

    protected static string $view = 'filament.resources.course-progress.view';

    public ?int $totalMaterials = null;

    public ?int $totalAssignments = null;

    public ?int $totalExams = null;

    public ?float $classAvgMaterialSeconds = null;

    /** @var array<string,int> student_id → SUM(material_seconds) dari rollup */
    public array $materialSecondsMap = [];

    /** @var array<string,int> student_id → jumlah material SELESAI (per aturan §7.1) */
    public array $materialsCompletedMap = [];

    /** @var array<string,int> student_id → count of overdue (deadline lewat, belum submit) */
    public array $overdueMap = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->authorizeAccess();
        $this->precomputeContext();
        $this->precomputeStudentMetrics();
    }

    public function getTitle(): string
    {
        /** @var ClassroomSubject $cs */
        $cs = $this->record;

        return "Progres — {$cs->classroom?->name} · {$cs->subject?->name}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            CourseProgressResource::getUrl('index') => 'Pantau Progress Belajar',
            $this->getTitle(),
        ];
    }

    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if ($user?->hasRole('super_admin')) {
            return;
        }

        /** @var ClassroomSubject $cs */
        $cs = $this->record;
        abort_unless(
            $user?->teacher && $cs->teacher_id === $user->teacher->id,
            403,
            'Bukan mapel yang Anda ampu.',
        );
    }

    private function precomputeContext(): void
    {
        /** @var ClassroomSubject $cs */
        $cs = $this->record;
        $csId = $cs->id;

        $materialIdsSub = Material::query()
            ->where('classroom_subject_id', $csId)
            ->where('is_published', true)
            ->select('id');

        $this->totalMaterials = (clone $materialIdsSub)->count();

        $this->totalAssignments = Assignment::query()
            ->whereIn('material_id', $materialIdsSub)
            ->where('is_published', true)
            ->count();

        $this->totalExams = Exam::query()
            ->whereIn('material_id', $materialIdsSub)
            ->where('is_published', true)
            ->count();

        $window = now()->subDays(7)->toDateString();

        $row = DB::table('learning_progress_daily_rollups')
            ->where('classroom_subject_id', $csId)
            ->where('date', '>=', $window)
            ->selectRaw('AVG(material_seconds) AS avg_mat')
            ->first();

        $this->classAvgMaterialSeconds = (float) ($row->avg_mat ?? 0);
    }

    /**
     * Metric Level 2: durasi material, % SELESAI (konsisten dgn Level 3), risk.
     * Total query: ~6 (constant, independent of student count).
     */
    private function precomputeStudentMetrics(): void
    {
        /** @var ClassroomSubject $cs */
        $cs = $this->record;
        $csId = $cs->id;

        // 1. SUM(material_seconds) per student
        $this->materialSecondsMap = DB::table('learning_progress_daily_rollups')
            ->where('classroom_subject_id', $csId)
            ->selectRaw('student_id, SUM(material_seconds) AS total')
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->student_id => (int) $r->total])
            ->all();

        // 2. material SELESAI per student — batch, pakai aturan §7.1 yg sama dgn detail.
        $this->materialsCompletedMap = $this->computeCompletionMap($csId);

        // 3. overdue per student (deadline lewat & belum submit)
        $materialIds = Material::query()
            ->where('classroom_subject_id', $csId)
            ->where('is_published', true)
            ->pluck('id');

        $overdueAssignmentIds = $materialIds->isEmpty() ? collect() : Assignment::query()
            ->whereIn('material_id', $materialIds)
            ->where('is_published', true)
            ->where('deadline', '<', now())
            ->pluck('id');

        if ($overdueAssignmentIds->isEmpty()) {
            $this->overdueMap = [];

            return;
        }

        $enrolledIds = Student::query()
            ->whereHas('classrooms', fn (Builder $q) => $q->whereKey($cs->classroom_id))
            ->where('is_active', true)
            ->pluck('id');

        $submittedByStudent = AssignmentSubmission::query()
            ->whereIn('assignment_id', $overdueAssignmentIds)
            ->whereIn('student_id', $enrolledIds)
            ->whereNotNull('submitted_at')
            ->selectRaw('student_id, COUNT(DISTINCT assignment_id) AS done')
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->student_id => (int) $r->done])
            ->all();

        $totalOverdue = $overdueAssignmentIds->count();
        foreach ($enrolledIds as $sid) {
            $this->overdueMap[(string) $sid] = max(0, $totalOverdue - ($submittedByStudent[(string) $sid] ?? 0));
        }
    }

    /**
     * Hitung jumlah material "selesai" per siswa, pakai aturan §7.1 (file/link/text).
     * Batch — ~3 query (sessions, downloads, materials), loop in-memory. Konstan thd jumlah siswa.
     *
     * @return array<string,int> student_id → jumlah material selesai
     */
    private function computeCompletionMap(string $csId): array
    {
        $materials = Material::query()
            ->where('classroom_subject_id', $csId)
            ->where('is_published', true)
            ->get();

        if ($materials->isEmpty()) {
            return [];
        }

        $materialMorph = (new Material)->getMorphClass();
        $studentMorph = (new Student)->getMorphClass();
        $materialIds = $materials->pluck('id');

        // active_seconds per (student, material) — 1 query
        $secondsByPair = DB::table('learning_progress_sessions')
            ->where('classroom_subject_id', $csId)
            ->where('trackable_type', $materialMorph)
            ->whereIn('trackable_id', $materialIds)
            ->selectRaw('student_id, trackable_id, SUM(active_seconds) AS secs')
            ->groupBy('student_id', 'trackable_id')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->student_id}|{$r->trackable_id}" => (int) $r->secs])
            ->all();

        // download flag per (student, material) — 1 query
        $downloadedPairs = Activity::query()
            ->where('log_name', 'material_download')
            ->where('subject_type', $materialMorph)
            ->whereIn('subject_id', $materialIds)
            ->where('causer_type', $studentMorph)
            ->get(['subject_id', 'causer_id'])
            ->mapWithKeys(fn ($a) => ["{$a->causer_id}|{$a->subject_id}" => true])
            ->all();

        // Pre-klasifikasi material sekali (bukan per siswa).
        $meta = $materials->mapWithKeys(fn (Material $m) => [
            $m->id => ['model' => $m, 'type' => MaterialCompletion::classify($m)],
        ]);

        $enrolledIds = Student::query()
            ->whereHas('classrooms', fn (Builder $q) => $q->whereKey($this->record->classroom_id))
            ->where('is_active', true)
            ->pluck('id');

        $map = [];
        foreach ($enrolledIds as $sid) {
            $sid = (string) $sid;
            $completed = 0;
            foreach ($meta as $mid => $info) {
                $seconds = $secondsByPair["{$sid}|{$mid}"] ?? 0;
                $downloaded = isset($downloadedPairs["{$sid}|{$mid}"]);
                if (MaterialCompletion::isCompleted($info['model'], $info['type'], $seconds, $downloaded)) {
                    $completed++;
                }
            }
            $map[$sid] = $completed;
        }

        return $map;
    }

    public function table(Table $table): Table
    {
        /** @var ClassroomSubject $cs */
        $cs = $this->record;
        $classroomId = $cs->classroom_id;
        $totalMaterials = max(1, (int) $this->totalMaterials);

        return $table
            ->query(
                Student::query()
                    ->whereHas('classrooms', fn (Builder $q) => $q->whereKey($classroomId))
                    ->where('is_active', true),
            )
            ->columns([
                TextColumn::make('full_name')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('nisn')
                    ->label('NISN')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('material_pct')
                    ->label('% Material Selesai')
                    ->tooltip('Persentase material yang sudah "selesai" (file: diunduh; teks/link: durasi baca memenuhi ambang). Konsisten dgn angka di halaman Detail.')
                    ->state(fn (Student $student) => (int) round((($this->materialsCompletedMap[$student->id] ?? 0) / $totalMaterials) * 100))
                    ->badge()
                    ->color(fn ($state) => $state >= 80 ? 'success' : ($state >= 50 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->alignCenter(),

                TextColumn::make('material_duration')
                    ->label('Durasi Material')
                    ->state(fn (Student $student) => $this->materialSecondsMap[$student->id] ?? 0)
                    ->formatStateUsing(fn ($state) => LearningProgressMetrics::formatDuration((int) $state))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('risk_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Student $student) => LearningProgressMetrics::riskStatus(
                        $this->overdueMap[$student->id] ?? 0,
                        $this->materialSecondsMap[$student->id] ?? 0,
                        $this->classAvgMaterialSeconds ?? 0,
                    ))
                    ->color(fn ($state) => LearningProgressMetrics::riskBadgeColor((string) $state))
                    ->formatStateUsing(fn ($state) => LearningProgressMetrics::riskLabel((string) $state)),
            ])
            ->defaultSort('full_name')
            ->actions([
                TableAction::make('detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->button()
                    ->url(fn (Student $student) => CourseProgressResource::getUrl('student-detail', [
                        'record' => $cs->id,
                        'student' => $student->id,
                    ])),
            ])
            ->headerActions([
                ExportProgressAction::make($cs),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
