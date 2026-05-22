<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Actions\Student\UpdateStudent;
use App\Filament\Resources\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->resetPasswordAction(),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Student $record */
        return app(UpdateStudent::class)->handle($record, $data);
    }

    protected function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reset password siswa')
            ->modalDescription('Password baru akan ditampilkan sekali setelah dikonfirmasi. Pastikan kamu segera menyimpan / memberikannya ke siswa.')
            ->modalSubmitActionLabel('Reset sekarang')
            ->action(function () {
                /** @var Student $student */
                $student = $this->record;

                if (! $student->user) {
                    Notification::make()
                        ->title('Siswa tidak punya akun user')
                        ->danger()
                        ->send();

                    return;
                }

                $newPassword = Str::random(8);

                $student->user->forceFill([
                    'password' => Hash::make($newPassword),
                ])->save();

                activity('student_password_reset')
                    ->performedOn($student)
                    ->log('reset by admin');

                Notification::make()
                    ->title('Password baru')
                    ->body("Password siswa **{$student->full_name}**: `{$newPassword}` — salin sekarang, tidak akan ditampilkan ulang.")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
