<?php

namespace App\Filament\Resources\AssignmentResource\Pages;

use App\Exports\AssignmentExport;
use App\Filament\Resources\AssignmentResource;
use App\Models\Assignment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewAssignment extends ViewRecord
{
    protected static string $resource = AssignmentResource::class;

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
                /** @var Assignment $assignment */
                $assignment = $this->record;

                $filename = sprintf(
                    'tugas-%s-%s.xlsx',
                    Str::slug($assignment->title),
                    now()->format('Ymd-Hi'),
                );

                return Excel::download(new AssignmentExport($assignment), $filename);
            });
    }
}
