<?php

namespace App\Imports;

use App\Models\Enums\GenderEnum;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentImport implements SkipsOnError, ToModel, WithHeadingRow, WithValidation
{
    use SkipsErrors;

    private array $schoolCache = [];

    public function model(array $row): ?Student
    {
        if (empty($row['nama_lengkap'])) {
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

        return new Student([
            'school_id' => $this->resolveSchool($row['sekolah'] ?? ''),
            'full_name' => $row['nama_lengkap'],
            'nisn' => $row['nisn'] ?? null,
            'class' => $row['kelas'] ?? null,
            'gender' => $this->parseGender($row['jenis_kelamin'] ?? ''),
            'place_of_birth' => $row['tempat_lahir'] ?? null,
            'birth_date' => $birthDate,
            'is_active' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_lengkap' => 'required|string',
        ];
    }

    private function resolveSchool(string $name): ?string
    {
        if (! $name) {
            return null;
        }

        if (! isset($this->schoolCache[$name])) {
            $this->schoolCache[$name] = School::where('name', 'like', "%{$name}%")->value('id');
        }

        return $this->schoolCache[$name];
    }

    private function parseGender(string $value): string
    {
        return strtolower($value) === 'perempuan' ? GenderEnum::Female->value : GenderEnum::Male->value;
    }
}
