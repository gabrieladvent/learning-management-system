<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Exports\StudentTemplateExport;
use App\Filament\Resources\StudentResource;
use App\Imports\StudentImport;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Unduh Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(new StudentTemplateExport, 'template-siswa.xlsx')),

            Action::make('import')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    Excel::import(new StudentImport, $data['file']->getRealPath());

                    Notification::make()
                        ->title('Import berhasil')
                        ->success()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
