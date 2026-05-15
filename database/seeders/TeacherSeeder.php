<?php

namespace Database\Seeders;

use App\Models\Enums\GenderEnum;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name' => 'Budi Santoso',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $user->assignRole('teacher');

        Teacher::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => 'Budi Santoso, S.Pd.',
                'nip' => '198501012010011001',
                'specialization' => 'Matematika',
                'phone' => '08123456789',
                'nik' => '3201010101850001',
                'birth_date' => '1985-01-01',
                'place_of_birth' => 'Bandung',
                'gender' => GenderEnum::Male,
            ]
        );
    }
}
