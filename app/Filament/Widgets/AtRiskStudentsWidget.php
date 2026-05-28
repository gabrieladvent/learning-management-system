<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CourseProgressResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassroomSubject;
use App\Models\Material;
use App\Models\Student;
use App\Support\LearningProgressMetrics;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Top-N siswa berisiko di mapel yang guru ampu (atau semua kalau super_admin).
 *
 * Perf strategy: batch query — pull semua data agregat dalam beberapa GROUP BY query,
 * lalu compute risk_status di-memory. TIDAK loop per (student × cs) dengan query masing-masing.
 *
 * Worst case sebelumnya: 80 siswa × 3 mapel × 3 query = ~720 query. Sekarang ~6 query total.
 */
class AtRiskStudentsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    protected static ?string $heading = '⚠️ Siswa Berisiko';

    protected static ?string $pollingInterval = null;

    /** @var array<string, array<string,mixed>> map student_id → row data */
    private ?array $rowsCache = null;

    public function table(Table $table): Table
    {
        $rows = $this->getRows();
        $ids = array_keys($rows);

        return $table
            ->query(
                Student::query()
                    ->when($ids === [], fn (Builder $q) => $q->whereRaw('1 = 0'))
                    ->whereIn('id', $ids === [] ? ['__none__'] : $ids),
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('full_name')
                    ->label('Siswa')
                    ->searchable(),

                TextColumn::make('nisn')
                    ->label('NISN')
                    ->toggleable(),

                TextColumn::make('classroom_subject_label')
                    ->label('Mapel')
                    ->state(fn (Student $student) => $rows[$student->id]['cs_label'] ?? '—'),

                TextColumn::make('overdue_count')
                    ->label('Tugas Overdue')
                    ->state(fn (Student $student) => $rows[$student->id]['overdue'] ?? 0)
                    ->alignCenter()
                    ->color('warning'),

                TextColumn::make('material_seconds')
                    ->label('Durasi Material')
                    ->state(fn (Student $student) => $rows[$student->id]['mat_sec'] ?? 0)
                    ->formatStateUsing(fn ($state) => LearningProgressMetrics::formatDuration((int) $state))
                    ->alignCenter(),

                TextColumn::make('risk_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Student $student) => $rows[$student->id]['status'] ?? 'aman')
                    ->color(fn ($state) => LearningProgressMetrics::riskBadgeColor((string) $state))
                    ->formatStateUsing(fn ($state) => LearningProgressMetrics::riskLabel((string) $state)),
            ])
            ->actions([
                Action::make('open_course')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Student $student) => $rows[$student->id]['cs_url'] ?? null),
            ]);
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    private function getRows(): array
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        $user = auth()->user();
        $isAdmin = $user?->hasRole('super_admin') ?? false;

        $csQuery = ClassroomSubject::query()->with(['classroom', 'subject']);
        if (! $isAdmin) {
            if (! $user?->teacher) {
                return $this->rowsCache = [];
            }
            $csQuery->where('teacher_id', $user->teacher->id);
        }

        $courses = $csQuery->get();
        if ($courses->isEmpty()) {
            return $this->rowsCache = [];
        }

        $csIds = $courses->pluck('id');
        $classroomIds = $courses->pluck('classroom_id')->unique();
        $sinceWindow = now()->subDays(7)->toDateString();

        // Class average material_seconds per (classroom_subject) — 1 query.
        $classAvg = DB::table('learning_progress_daily_rollups')
            ->whereIn('classroom_subject_id', $csIds)
            ->where('date', '>=', $sinceWindow)
            ->selectRaw('classroom_subject_id, AVG(material_seconds) AS avg_mat')
            ->groupBy('classroom_subject_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->classroom_subject_id => (float) $r->avg_mat])
            ->all();

        // Total material_seconds per (student × classroom_subject) — 1 query.
        $matSecByPair = DB::table('learning_progress_daily_rollups')
            ->whereIn('classroom_subject_id', $csIds)
            ->selectRaw('student_id, classroom_subject_id, SUM(material_seconds) AS total')
            ->groupBy('student_id', 'classroom_subject_id')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->student_id}|{$r->classroom_subject_id}" => (int) $r->total])
            ->all();

        // Overdue counts per (student × classroom_subject).
        // Step 1: pull overdue assignments grouped by classroom_subject_id (via material).
        $materialMap = Material::query()
            ->whereIn('classroom_subject_id', $csIds)
            ->where('is_published', true)
            ->get(['id', 'classroom_subject_id'])
            ->groupBy('classroom_subject_id')
            ->map(fn ($materials) => $materials->pluck('id')->all())
            ->all();

        $overduePairCounts = [];      // ["student_id|cs_id" => N]
        $overdueCountByCs = [];        // [cs_id => N]
        $allOverdueAssignmentIds = collect();

        foreach ($csIds as $csId) {
            $matIds = $materialMap[(string) $csId] ?? [];
            if ($matIds === []) {
                $overdueCountByCs[(string) $csId] = 0;

                continue;
            }
            $overdueIds = Assignment::query()
                ->whereIn('material_id', $matIds)
                ->where('is_published', true)
                ->where('deadline', '<', now())
                ->pluck('id');

            $overdueCountByCs[(string) $csId] = $overdueIds->count();
            foreach ($overdueIds as $aid) {
                $allOverdueAssignmentIds->push(['cs_id' => (string) $csId, 'assignment_id' => $aid]);
            }
        }

        // Submitted pairs untuk overdue assignments — 1 query, lookup by (student, assignment).
        $submittedByPair = [];
        if ($allOverdueAssignmentIds->isNotEmpty()) {
            $allIds = $allOverdueAssignmentIds->pluck('assignment_id');
            $submittedByPair = AssignmentSubmission::query()
                ->whereIn('assignment_id', $allIds)
                ->whereNotNull('submitted_at')
                ->get(['student_id', 'assignment_id'])
                ->groupBy(fn ($row) => "{$row->student_id}|{$row->assignment_id}")
                ->map(fn ($g) => true)
                ->all();
        }

        // Daftar enrolled students per classroom — 1 query.
        $enrolledByCs = [];
        $studentRows = DB::table('classroom_students as csp')
            ->join('students as s', function ($join) {
                $join->on('s.id', '=', 'csp.student_id')
                    ->where('s.is_active', true)
                    ->whereNull('s.deleted_at');
            })
            ->whereIn('csp.classroom_id', $classroomIds)
            ->get(['csp.classroom_id', 's.id as student_id']);

        $byClassroom = $studentRows->groupBy('classroom_id');

        foreach ($courses as $cs) {
            $enrolledByCs[(string) $cs->id] = $byClassroom->get($cs->classroom_id, collect())->pluck('student_id')->all();
        }

        // Compute risk per (student × cs); keep highest severity per student.
        $out = [];
        foreach ($courses as $cs) {
            $csId = (string) $cs->id;
            $csAvg = (float) ($classAvg[$csId] ?? 0);
            $totalOverdueInCs = (int) ($overdueCountByCs[$csId] ?? 0);
            $assignmentsForCs = collect($allOverdueAssignmentIds)->where('cs_id', $csId)->pluck('assignment_id');

            foreach ($enrolledByCs[$csId] as $sid) {
                $matSec = (int) ($matSecByPair["{$sid}|{$csId}"] ?? 0);

                $studentSubmittedOfOverdue = 0;
                foreach ($assignmentsForCs as $aid) {
                    if (isset($submittedByPair["{$sid}|{$aid}"])) {
                        $studentSubmittedOfOverdue++;
                    }
                }
                $overdue = max(0, $totalOverdueInCs - $studentSubmittedOfOverdue);

                $status = LearningProgressMetrics::riskStatus($overdue, $matSec, $csAvg);
                if ($status === 'aman') {
                    continue;
                }

                $existing = $out[$sid] ?? null;
                if ($existing && $existing['status'] === 'berisiko' && $status === 'pantau') {
                    continue;
                }

                $out[$sid] = [
                    'cs_label' => sprintf('%s · %s', $cs->classroom?->name ?? '', $cs->subject?->name ?? ''),
                    'overdue' => $overdue,
                    'mat_sec' => $matSec,
                    'status' => $status,
                    'cs_url' => CourseProgressResource::getUrl('view', ['record' => $csId]),
                ];
            }
        }

        // Sort: berisiko > pantau, take 10.
        uasort($out, fn ($a, $b) => ($b['status'] === 'berisiko' ? 2 : 1) <=> ($a['status'] === 'berisiko' ? 2 : 1));

        return $this->rowsCache = array_slice($out, 0, 10, true);
    }
}
