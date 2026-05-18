<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterStudent
{
    /**
     * Create a Student plus a linked User record.
     *
     * `password` opsional — kalau kosong, dipakai birth_date (Y-m-d). Itulah
     * default yang dishare ke siswa saat onboarding; mereka diharap menggantinya.
     *
     * @param  array{
     *     full_name: string,
     *     school_id: string,
     *     nisn?: ?string,
     *     class?: ?string,
     *     gender: string,
     *     place_of_birth?: ?string,
     *     birth_date?: ?string,
     *     is_active?: bool,
     *     password?: ?string,
     * }  $data
     */
    public function handle(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $rawPassword = $this->resolvePassword($data);

            $user = User::create([
                'name' => $data['full_name'],
                'email' => null,
                'password' => Hash::make($rawPassword),
                'is_active' => $data['is_active'] ?? true,
                'password_changed_at' => null,
            ]);

            $user->assignRole('student');

            return Student::create([
                'user_id' => $user->id,
                'school_id' => $data['school_id'],
                'nisn' => $data['nisn'] ?? null,
                'full_name' => $data['full_name'],
                'class' => $data['class'] ?? null,
                'gender' => $data['gender'],
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    private function resolvePassword(array $data): string
    {
        $explicit = trim((string) ($data['password'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        if (! empty($data['birth_date'])) {
            return Carbon::parse($data['birth_date'])->format('Y-m-d');
        }

        throw new \InvalidArgumentException(
            'Tidak bisa membuat siswa tanpa password atau tanggal lahir.'
        );
    }
}
