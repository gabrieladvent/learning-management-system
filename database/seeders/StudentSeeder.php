<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Enums\GenderEnum;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            return;
        }

        $students = [
            ['name' => 'Ahmad Fauzi',       'nisn' => '0012345001', 'gender' => GenderEnum::Male,   'birth' => '2008-01-15', 'place' => 'Jakarta'],
            ['name' => 'Siti Nurhaliza',    'nisn' => '0012345002', 'gender' => GenderEnum::Female, 'birth' => '2008-03-20', 'place' => 'Bandung'],
            ['name' => 'Budi Pratama',      'nisn' => '0012345003', 'gender' => GenderEnum::Male,   'birth' => '2008-05-10', 'place' => 'Surabaya'],
            ['name' => 'Dewi Lestari',      'nisn' => '0012345004', 'gender' => GenderEnum::Female, 'birth' => '2008-07-22', 'place' => 'Yogyakarta'],
            ['name' => 'Eko Saputra',       'nisn' => '0012345005', 'gender' => GenderEnum::Male,   'birth' => '2008-09-08', 'place' => 'Medan'],
            ['name' => 'Fitri Handayani',   'nisn' => '0012345006', 'gender' => GenderEnum::Female, 'birth' => '2008-11-12', 'place' => 'Semarang'],
        ];

        $created = [];

        foreach ($students as $data) {
            $student = Student::firstOrCreate(
                ['nisn' => $data['nisn']],
                [
                    'school_id' => $school->id,
                    'full_name' => $data['name'],
                    'class' => 'X IPA 1',
                    'gender' => $data['gender'],
                    'birth_date' => $data['birth'],
                    'place_of_birth' => $data['place'],
                    'is_active' => true,
                ]
            );

            $created[] = $student;
        }

        // Enroll semua siswa ke kelas "X IPA 1" jika ada
        $classroom = Classroom::where('name', 'X IPA 1')->first();
        if ($classroom) {
            $classroom->students()->syncWithoutDetaching(
                collect($created)->mapWithKeys(fn ($s) => [$s->id => ['enrolled_at' => now()]])->all()
            );
        }
    }
}
