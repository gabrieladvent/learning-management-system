<?php

namespace App\Imports;

use App\Models\Enums\GenderEnum;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TeacherImport implements SkipsOnError, ToModel, WithHeadingRow, WithValidation
{
    use SkipsErrors;

    public function model(array $row): ?Teacher
    {
        if (empty($row['nama_lengkap']) || empty($row['email'])) {
            return null;
        }

        if (User::where('email', $row['email'])->exists()) {
            return null;
        }

        $birthDate = null;
        if (! empty($row['tanggal_lahir'])) {
            try {
                $birthDate = Carbon::createFromFormat('d/m/Y', $row['tanggal_lahir']);
            } catch (\Exception $e) {
                $birthDate = Carbon::parse($row['tanggal_lahir']);
            }
        }

        $password = $birthDate ? $birthDate->format('dmY') : 'teacher123';

        $user = User::create([
            'name' => $row['nama_lengkap'],
            'email' => $row['email'],
            'password' => $password,
            'is_active' => true,
        ]);

        $user->assignRole('teacher');

        return new Teacher([
            'user_id' => $user->id,
            'full_name' => $row['nama_lengkap'],
            'nip' => $row['nip'] ?? null,
            'nik' => $row['nik'] ?? null,
            'specialization' => $row['spesialisasi'] ?? null,
            'phone' => $row['telepon'] ?? null,
            'gender' => $this->parseGender($row['jenis_kelamin'] ?? ''),
            'place_of_birth' => $row['tempat_lahir'] ?? null,
            'birth_date' => $birthDate,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_lengkap' => 'required|string',
            'email' => 'required|email',
        ];
    }

    private function parseGender(string $value): string
    {
        return strtolower($value) === 'perempuan' ? GenderEnum::Female->value : GenderEnum::Male->value;
    }
}
