<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $password = isset($data['birth_date'])
            ? Carbon::parse($data['birth_date'])->format('dmY')
            : 'teacher123';

        $user = User::create([
            'name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $password,
            'is_active' => true,
        ]);

        $user->assignRole('teacher');

        unset($data['email']);
        $data['user_id'] = $user->id;

        return static::getModel()::create($data);
    }
}
