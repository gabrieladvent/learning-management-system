<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Actions\Student\RegisterStudent;
use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(RegisterStudent::class)->handle($data);
    }
}
