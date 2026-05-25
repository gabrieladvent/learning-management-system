<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Exports\ExamExport;
use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewExam extends ViewRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            EditAction::make(),
        ];
    }

    protected function exportAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Export ke Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->action(function () {
                /** @var Exam $exam */
                $exam = $this->record;

                $filename = sprintf(
                    'ujian-%s-%s.xlsx',
                    Str::slug($exam->title),
                    now()->format('Ymd-Hi'),
                );

                return Excel::download(new ExamExport($exam), $filename);
            });
    }
}
