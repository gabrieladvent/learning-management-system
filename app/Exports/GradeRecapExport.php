<?php

namespace App\Exports;

use App\Models\ClassroomSubject;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class GradeRecapExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  Collection<int, Student>  $students
     * @param  array<int, array{key: string, type: string, id: string, label: string, max_score: float}>  $columns
     * @param  array<string, array<string, float|null>>  $grades
     */
    public function __construct(
        protected ClassroomSubject $course,
        protected Collection $students,
        protected array $columns,
        protected array $grades,
    ) {}

    public function array(): array
    {
        $rows = [];

        $title = sprintf(
            'Rekap Nilai — %s · %s (TA %s sem %s)',
            $this->course->classroom?->name ?? '—',
            $this->course->subject?->name ?? '—',
            $this->course->academic_year ?? '—',
            $this->course->semester ?? '—',
        );
        $rows[] = [$title];
        $rows[] = [];

        $header = ['No', 'NISN', 'Nama Siswa'];
        foreach ($this->columns as $col) {
            $header[] = sprintf('%s (max %s)', $col['label'], rtrim(rtrim(number_format($col['max_score'], 2, '.', ''), '0'), '.'));
        }
        $header[] = 'Total';
        $rows[] = $header;

        $no = 1;
        foreach ($this->students as $student) {
            $row = [
                $no++,
                $student->nisn ?? '—',
                $student->full_name,
            ];

            $total = 0.0;
            $hasScore = false;
            foreach ($this->columns as $col) {
                $score = $this->grades[$student->id][$col['key']] ?? null;
                if ($score !== null) {
                    $total += $score;
                    $hasScore = true;
                }
                $row[] = $score !== null ? $score : '—';
            }

            $row[] = $hasScore ? $total : '—';
            $rows[] = $row;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A3:Z3')->getFont()->setBold(true);
            },
        ];
    }
}
