<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Actions\Student\UpdateStudent;
use App\Filament\Resources\StudentResource;
use App\Models\Student;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Student $record */
        return app(UpdateStudent::class)->handle($record, $data);
    }
}
