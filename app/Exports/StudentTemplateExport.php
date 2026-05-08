<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Ahmad Fauzi', '1234567890', 'SMA Negeri 1 Contoh', 'X IPA 1', 'Laki-laki', 'Jakarta', '15/06/2008'],
        ];
    }

    public function headings(): array
    {
        return [
            'nama_lengkap',
            'nisn',
            'sekolah',
            'kelas',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
        ];
    }

    public function title(): string
    {
        return 'Template Siswa';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
