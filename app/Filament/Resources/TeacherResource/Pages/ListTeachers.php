<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Exports\TeacherTemplateExport;
use App\Filament\Resources\TeacherResource;
use App\Imports\TeacherImport;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Unduh Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(new TeacherTemplateExport, 'template-guru.xlsx')),

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
                    Excel::import(new TeacherImport, $data['file']->getRealPath());

                    Notification::make()
                        ->title('Import berhasil')
                        ->success()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
