<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Exports\ActivityLogExport;
use App\Filament\Resources\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export ke Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $filename = sprintf('activity-log-%s.xlsx', now()->format('Ymd-Hi'));
                    $query = $this->getFilteredTableQuery();

                    return Excel::download(new ActivityLogExport($query), $filename);
                }),
        ];
    }
}
