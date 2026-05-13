<?php

namespace App\Filament\Resources\AssignmentResource\Pages;

use App\Filament\Resources\AssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAssignment extends ViewRecord
{
    protected static string $resource = AssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
