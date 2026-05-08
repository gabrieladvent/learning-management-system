<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTeacher extends ViewRecord
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label('Reset Password')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Password ke Default?')
                ->modalDescription(fn () => $this->record->birth_date
                    ? "Password akan direset ke tanggal lahir: {$this->record->birth_date->format('dmY')}"
                    : 'Tanggal lahir belum diisi. Tidak dapat mereset password.')
                ->action(function () {
                    if (! $this->record->birth_date || ! $this->record->user) {
                        Notification::make()
                            ->title('Gagal mereset password')
                            ->body('Tanggal lahir atau akun guru tidak ditemukan.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->record->user->update([
                        'password' => Carbon::parse($this->record->birth_date)->format('dmY'),
                    ]);

                    Notification::make()
                        ->title('Password berhasil direset')
                        ->body("Password direset ke: {$this->record->birth_date->format('dmY')}")
                        ->success()
                        ->send();
                }),

            EditAction::make(),
        ];
    }
}
