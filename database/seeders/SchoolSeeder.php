<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        School::firstOrCreate(
            ['name' => 'SMA Negeri 1 Contoh'],
            [
                'address'   => 'Jl. Pendidikan No. 1, Kota Contoh',
                'phone'     => '021-1234567',
                'email'     => 'info@sman1contoh.sch.id',
                'is_active' => true,
            ]
        );
    }
}
