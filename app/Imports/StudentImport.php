<?php

namespace App\Imports;

use App\Actions\Student\RegisterStudent;
use App\Models\Enums\GenderEnum;
use App\Models\School;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

/**
 * Import siswa dari Excel. Setiap baris disalurkan lewat RegisterStudent supaya
 * TIDAK ada divergensi dengan pembuatan via form: siswa hasil import juga
 * mendapat record User + role `student` + password default sehingga BISA login.
 */
class StudentImport implements OnEachRow, SkipsOnError, WithHeadingRow, WithValidation
{
    use SkipsErrors;

    private array $schoolCache = [];

    public function onRow(Row $row): void
    {
        $data = $row->toArray();

        if (empty($data['nama_lengkap'])) {
            return;
        }

        $birthDate = null;
        if (! empty($data['tanggal_lahir'])) {
            try {
                $birthDate = Carbon::createFromFormat('d/m/Y', $data['tanggal_lahir']);
            } catch (\Exception $e) {
                $birthDate = Carbon::parse($data['tanggal_lahir']);
            }
        }

        // RegisterStudent membungkus User+Student dalam satu transaksi, jadi bila
        // gagal (mis. sekolah tidak ketemu → school_id null), tidak ada User yatim.
        // try/catch memastikan satu baris buruk tidak membatalkan seluruh import.
        try {
            app(RegisterStudent::class)->handle([
                'school_id' => $this->resolveSchool($data['sekolah'] ?? ''),
                'full_name' => $data['nama_lengkap'],
                'nisn' => $data['nisn'] ?? null,
                'class' => $data['kelas'] ?? null,
                'gender' => $this->parseGender($data['jenis_kelamin'] ?? ''),
                'place_of_birth' => $data['tempat_lahir'] ?? null,
                'birth_date' => $birthDate?->format('Y-m-d'),
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            // Rekam lewat SkipsErrors supaya bisa dilaporkan ke admin (lihat ->errors()).
            $this->onError($e);
        }
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
            // Exact match (case-insensitive). LIKE %name% berbahaya: "SMA 1" akan
            // cocok dengan "SMA 10"/"SMA 11" dan hit pertama menang (salah sekolah).
            $this->schoolCache[$name] = School::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
                ->value('id');
        }

        return $this->schoolCache[$name];
    }

    private function parseGender(string $value): string
    {
        return strtolower($value) === 'perempuan' ? GenderEnum::Female->value : GenderEnum::Male->value;
    }
}
