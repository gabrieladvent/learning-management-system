<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Roles
        $adminRole = Role::firstOrCreate([
            'name' => 'super_admin',
        ]);

        $teacherRole = Role::firstOrCreate([
            'name' => 'teacher',
        ]);

        $studentRole = Role::firstOrCreate([
            'name' => 'student',
        ]);

        // Create Admin User
        $admin = User::firstOrCreate(
            [
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password'),
            ]
        );

        // Assign Role
        $admin->assignRole($adminRole);
    }
}
