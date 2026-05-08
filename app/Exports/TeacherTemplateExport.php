<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeacherTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Budi Santoso', 'budi@sekolah.sch.id', '198501012010011001', '3201010101850001', 'Matematika', '08123456789', 'Laki-laki', 'Bandung', '01/01/1985'],
        ];
    }

    public function headings(): array
    {
        return [
            'nama_lengkap',
            'email',
            'nip',
            'nik',
            'spesialisasi',
            'telepon',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
        ];
    }

    public function title(): string
    {
        return 'Template Guru';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
