<?php

namespace App\Filament\Widgets;

use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClassroomStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $teacher = $user?->teacher;

        if ($user?->hasRole('super_admin')) {
            $classroomCount = Classroom::query()->count();
            $courseCount = ClassroomSubject::query()->count();
            $studentCount = Student::query()->where('is_active', true)->count();
        } elseif ($teacher) {
            $courses = ClassroomSubject::query()
                ->where('teacher_id', $teacher->id);

            $classroomCount = (clone $courses)->distinct('classroom_id')->count('classroom_id');
            $courseCount = (clone $courses)->count();

            $studentCount = Student::query()
                ->whereHas('classrooms', fn ($q) => $q->whereIn(
                    'classrooms.id',
                    (clone $courses)->select('classroom_id')
                ))
                ->where('is_active', true)
                ->count();
        } else {
            $classroomCount = 0;
            $courseCount = 0;
            $studentCount = 0;
        }

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
