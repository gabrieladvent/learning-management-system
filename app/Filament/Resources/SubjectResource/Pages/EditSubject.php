<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubject extends EditRecord
{
    protected static string $resource = SubjectResource::class;

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
}
