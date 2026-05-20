<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AssignmentResource;
use App\Filament\Resources\ExamResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enums\QuestionTypeEnum;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingGradingWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $teacher = auth()->user()?->teacher;
        $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;

        $assignmentIds = $this->scopedAssignmentIds($teacher, $isSuperAdmin);
        $examIds = $this->scopedExamIds($teacher, $isSuperAdmin);

        $pendingAssignment = AssignmentSubmission::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->whereNotNull('submitted_at')
            ->whereNull('score')
            ->count();

        $pendingExamSubmission = ExamSubmission::query()
            ->whereIn('exam_id', $examIds)
            ->whereNotNull('submitted_at')
            ->whereNull('score')
            ->count();

        $pendingEssaySession = ExamSession::query()
            ->whereIn('exam_id', $examIds)
            ->whereNotNull('submitted_at')
            ->whereHas('answers', function ($q) {
                $q->whereNull('score')
                    ->whereHas('question', fn ($qq) => $qq->where('type', QuestionTypeEnum::Essay->value));
            })
            ->count();

        return [
            Stat::make('Tugas Belum Dinilai', $pendingAssignment)
                ->description('Submission masuk tanpa skor')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color($pendingAssignment > 0 ? 'warning' : 'success')
                ->url($assignmentIds->isNotEmpty() ? AssignmentResource::getUrl('index') : null),

            Stat::make('Ujian (Submission) Belum Dinilai', $pendingExamSubmission)
                ->description('Submission masuk tanpa skor')
                ->descriptionIcon('heroicon-o-arrow-up-tray')
                ->color($pendingExamSubmission > 0 ? 'warning' : 'success')
                ->url($examIds->isNotEmpty() ? ExamResource::getUrl('index') : null),

            Stat::make('Essay Belum Dinilai', $pendingEssaySession)
                ->description('Sesi online quiz dengan essay tertunda')
                ->descriptionIcon('heroicon-o-pencil-square')
                ->color($pendingEssaySession > 0 ? 'warning' : 'success'),
        ];
    }

    protected function scopedAssignmentIds($teacher, bool $isSuperAdmin)
    {
        if ($isSuperAdmin) {
            return Assignment::query()->pluck('id');
        }

        if (! $teacher) {
            return collect();
        }

        return Assignment::query()
            ->whereHas('material.classroomSubject', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->pluck('id');
    }

    protected function scopedExamIds($teacher, bool $isSuperAdmin)
    {
        if ($isSuperAdmin) {
            return Exam::query()->pluck('id');
        }

        if (! $teacher) {
            return collect();
        }

        return Exam::query()
            ->whereHas('material.classroomSubject', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->pluck('id');
    }
}
