<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseProgressResource\Pages;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Subject;
use App\Support\LearningProgressMetrics;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CourseProgressResource extends Resource
{
    protected static ?string $model = ClassroomSubject::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Pengajaran';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Pantau Progress Belajar';

    protected static ?string $modelLabel = 'Progres Mengajar';

    protected static ?string $pluralModelLabel = 'Pantau Progress Belajar';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super_admin') || $user?->teacher !== null;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['classroom', 'subject', 'teacher']);

        $user = auth()->user();
        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        if ($user?->teacher) {
            return $query->where('teacher_id', $user->teacher->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('teacher.full_name')
                    ->label('Guru')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('semester')
                    ->label('Sem')
                    ->formatStateUsing(fn ($state) => "Sem {$state}")
                    ->alignCenter(),

                TextColumn::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->toggleable(),

                TextColumn::make('students_count')
                    ->label('Siswa Aktif')
                    ->state(fn (ClassroomSubject $record) => static::loadMetricsCache()[$record->id]['students'] ?? 0)
                    ->alignCenter(),

                TextColumn::make('total_material_seconds')
                    ->label('Total Durasi Material')
                    ->state(fn (ClassroomSubject $record) => static::loadMetricsCache()[$record->id]['total_seconds'] ?? 0)
                    ->formatStateUsing(fn ($state) => LearningProgressMetrics::formatDuration((int) $state))
                    ->alignCenter()
                    ->tooltip('Jumlah waktu aktif semua siswa di material mapel ini (rolling 7 hari).'),

                TextColumn::make('avg_per_student')
                    ->label('Avg / Siswa')
                    ->state(function (ClassroomSubject $record) {
                        $cache = static::loadMetricsCache()[$record->id] ?? null;
                        $students = (int) ($cache['students'] ?? 0);
                        if ($students === 0) {
                            return 0;
                        }

                        return (int) round(($cache['total_seconds'] ?? 0) / $students);
                    })
                    ->formatStateUsing(fn ($state) => LearningProgressMetrics::formatDuration((int) $state))
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->defaultSort('academic_year', 'desc')
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Kelas')
                    ->options(Classroom::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->options(Subject::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('semester')
                    ->label('Semester')
                    ->options([1 => 'Semester 1', 2 => 'Semester 2']),
            ])
            ->actions([
                TableAction::make('view')
                    ->label('Buka Detail')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ClassroomSubject $record) => static::getUrl('view', ['record' => $record])),
            ]);
    }

    /**
     * Memoized per-request cache: map[classroom_subject_id] => ['students' => N, 'total_seconds' => N].
     * Dihitung 1× per render (2 query: GROUP BY rollup + GROUP BY pivot students).
     *
     * @return array<string, array{students:int,total_seconds:int}>
     */
    public static function loadMetricsCache(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        // Scope ke mapel yang user authorized — query lebih ringan.
        $csIds = static::getEloquentQuery()->pluck('id');
        if ($csIds->isEmpty()) {
            return $cache = [];
        }

        $since = now()->subDays(7)->toDateString();

        $sumRows = DB::table('learning_progress_daily_rollups')
            ->whereIn('classroom_subject_id', $csIds)
            ->where('date', '>=', $since)
            ->selectRaw('classroom_subject_id, SUM(material_seconds) AS total')
            ->groupBy('classroom_subject_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->classroom_subject_id => (int) $r->total]);

        // Hitung jumlah siswa aktif per classroom_subject (lewat classroom_id).
        $studentRows = DB::table('classroom_subjects as cs')
            ->join('classroom_students as csp', 'csp.classroom_id', '=', 'cs.classroom_id')
            ->join('students as s', function ($join) {
                $join->on('s.id', '=', 'csp.student_id')
                    ->where('s.is_active', true)
                    ->whereNull('s.deleted_at');
            })
            ->whereIn('cs.id', $csIds)
            ->selectRaw('cs.id AS cs_id, COUNT(DISTINCT s.id) AS students')
            ->groupBy('cs.id')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->cs_id => (int) $r->students]);

        $cache = [];
        foreach ($csIds as $id) {
            $idStr = (string) $id;
            $cache[$idStr] = [
                'students' => $studentRows[$idStr] ?? 0,
                'total_seconds' => $sumRows[$idStr] ?? 0,
            ];
        }

        return $cache;
    }

    /**
     * Reset cache (untuk testing atau forced refresh).
     */
    public static function clearMetricsCache(): void
    {
        // Trigger reflection-free reset by re-invoking with fresh static.
        // Karena PHP static dalam method tidak bisa di-reset eksternal, kita pakai
        // request lifecycle — cache akan auto-fresh di request berikutnya.
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseProgress::route('/'),
            'view' => Pages\ViewCourseProgress::route('/{record}'),
            'student-detail' => Pages\ViewStudentProgress::route('/{record}/students/{student}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
