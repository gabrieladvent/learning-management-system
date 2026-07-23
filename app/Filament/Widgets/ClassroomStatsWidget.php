<?php

namespace App\Filament\Widgets;

use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ClassroomStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        $isSuperAdmin = $user?->hasRole('super_admin') ?? false;

        [$classroomCount, $courseCount, $studentCount] = Cache::remember(
            'dash:classstats:'.($user?->id ?? 'guest'),
            now()->addSeconds(60),
            function () use ($teacher, $isSuperAdmin) {
                if ($isSuperAdmin) {
                    return [
                        Classroom::query()->count(),
                        ClassroomSubject::query()->count(),
                        Student::query()->where('is_active', true)->count(),
                    ];
                }

                if (! $teacher) {
                    return [0, 0, 0];
                }

                $courses = ClassroomSubject::query()->where('teacher_id', $teacher->id);

                return [
                    (clone $courses)->distinct('classroom_id')->count('classroom_id'),
                    (clone $courses)->count(),
                    Student::query()
                        ->whereHas('classrooms', fn ($q) => $q->whereIn(
                            'classrooms.id',
                            (clone $courses)->select('classroom_id')
                        ))
                        ->where('is_active', true)
                        ->count(),
                ];
            }
        );

        return [
            Stat::make('Kelas Diampu', $classroomCount)
                ->description('Total kelas')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color('primary'),

            Stat::make('Course (Mapel × Kelas)', $courseCount)
                ->description('Total subject assignment')
                ->descriptionIcon('heroicon-o-book-open')
                ->color('info'),

            Stat::make('Siswa Aktif', $studentCount)
                ->description('Di semua kelas yang diampu')
                ->descriptionIcon('heroicon-o-users')
                ->color('success'),
        ];
    }
}
