<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Matematika',             'code' => 'MTK'],
            ['name' => 'Bahasa Indonesia',        'code' => 'BIND'],
            ['name' => 'Bahasa Inggris',          'code' => 'BING'],
            ['name' => 'Fisika',                  'code' => 'FIS'],
            ['name' => 'Kimia',                   'code' => 'KIM'],
            ['name' => 'Biologi',                 'code' => 'BIO'],
            ['name' => 'Sejarah',                 'code' => 'SEJ'],
            ['name' => 'Pendidikan Kewarganegaraan', 'code' => 'PKN'],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(['code' => $subject['code']], $subject);
        }
    }
}
