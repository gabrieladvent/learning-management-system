<?php

namespace App\Filament\Resources\AssignmentResource\Pages;

use App\Filament\Resources\AssignmentResource;
use App\Filament\Resources\MaterialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssignment extends EditRecord
{
    protected static string $resource = AssignmentResource::class;

    protected function getRedirectUrl(): string
    {
        return MaterialResource::getUrl('view', ['record' => $this->record->material_id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
