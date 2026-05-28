<?php

namespace App\Filament\Resources\CourseProgressResource\Actions;

use App\Exports\LearningProgressExport;
use App\Models\ClassroomSubject;
use Filament\Forms\Components\Radio;
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportProgressAction
{
    public static function make(ClassroomSubject $cs): Action
    {
        return Action::make('export_progress')
            ->label('Export ke Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->form([
                Radio::make('mode')
                    ->label('Mode Export')
                    ->options([
                        'raw' => 'Raw (nama + NISN penuh)',
                        'anonim' => 'Anonim (HMAC pseudo_id)',
                    ])
                    ->descriptions([
                        'raw' => 'Untuk internal sekolah / guru. Identitas siswa terlihat utuh.',
                        'anonim' => 'Untuk publikasi penelitian. student_id diganti hash deterministik (LEARNING_PROGRESS_PSEUDO_SECRET).',
                    ])
                    ->default('raw')
                    ->required()
                    ->inline(false),
            ])
            ->action(function (array $data) use ($cs): BinaryFileResponse {
                $mode = $data['mode'] === 'anonim' ? 'anonim' : 'raw';

                $filename = sprintf(
                    'learning_progress_%s_%s_%s_%s.xlsx',
                    $cs->classroom?->name ? str($cs->classroom->name)->slug() : 'kelas',
                    $cs->subject?->code ? strtolower($cs->subject->code) : 'mapel',
                    $mode,
                    now('Asia/Jakarta')->format('Ymd_His'),
                );

                return Excel::download(new LearningProgressExport($cs, $mode), $filename);
            });
    }
}
