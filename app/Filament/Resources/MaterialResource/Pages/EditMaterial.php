<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Filament\Resources\MaterialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getRedirectUrl(): string
    {
        return CourseResource::getUrl('view', ['record' => $this->record->classroom_subject_id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
