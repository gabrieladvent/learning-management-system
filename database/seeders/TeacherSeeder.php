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
        $teachers = [
            [
                'email' => 'matematika@example.com',
                'name' => 'Budi Santoso',
                'full_name' => 'Budi Santoso, S.Pd.',
                'nip' => '198501012010011001',
                'nik' => '3201010101850001',
                'specialization' => 'Matematika',
                'phone' => '081234567001',
                'birth_date' => '1985-01-01',
                'place_of_birth' => 'Bandung',
                'gender' => GenderEnum::Male,
            ],
            [
                'email' => 'bindo@example.com',
                'name' => 'Sari Wulandari',
                'full_name' => 'Sari Wulandari, S.Pd.',
                'nip' => '198703152011022002',
                'nik' => '3201020203870002',
                'specialization' => 'Bahasa Indonesia',
                'phone' => '081234567002',
                'birth_date' => '1987-03-15',
                'place_of_birth' => 'Yogyakarta',
                'gender' => GenderEnum::Female,
            ],
            [
                'email' => 'bing@example.com',
                'name' => 'Andi Pratama',
                'full_name' => 'Andi Pratama, S.S.',
                'nip' => '198909202012011003',
                'nik' => '3201030305890003',
                'specialization' => 'Bahasa Inggris',
                'phone' => '081234567003',
                'birth_date' => '1989-09-20',
                'place_of_birth' => 'Surabaya',
                'gender' => GenderEnum::Male,
            ],
        ];

        foreach ($teachers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('password'),
                    'is_active' => true,
                ]
            );

            $user->assignRole('teacher');

            Teacher::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $data['full_name'],
                    'nip' => $data['nip'],
                    'specialization' => $data['specialization'],
                    'phone' => $data['phone'],
                    'nik' => $data['nik'],
                    'birth_date' => $data['birth_date'],
                    'place_of_birth' => $data['place_of_birth'],
                    'gender' => $data['gender'],
                ]
            );
        }
    }
}
