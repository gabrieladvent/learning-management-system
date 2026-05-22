<?php

namespace App\Exports;

use App\Models\Assignment;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AssignmentExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(protected Assignment $assignment) {}

    public function title(): string
    {
        return mb_substr(preg_replace('/[^\w\s-]/u', '', $this->assignment->title) ?: 'Tugas', 0, 31);
    }

    public function array(): array
    {
        $assignment = $this->assignment->loadMissing([
            'material.classroomSubject.subject',
            'material.classroomSubject.classroom.students',
            'submissions',
        ]);

        $students = $assignment->material?->classroomSubject?->classroom?->students ?? collect();
        $submissions = $assignment->submissions->keyBy('student_id');
        $deadline = $assignment->deadline;

        $rows = [];
        $rows[] = [sprintf(
            'Export Tugas — %s · %s · %s',
            $assignment->title,
            $assignment->material?->classroomSubject?->subject?->name ?? '—',
            $assignment->material?->classroomSubject?->classroom?->name ?? '—',
        )];
        $rows[] = [
            'Deadline',
            $deadline?->format('Y-m-d H:i') ?? '—',
            'Max score',
            $assignment->max_score,
        ];
        $rows[] = [];

        $header = [
            'No', 'NISN', 'Nama Siswa',
            'Status', 'Submit', 'Selisih Deadline',
            'Skor', 'Dinilai', 'Feedback', 'Link',
        ];
        $rows[] = $header;

        $no = 1;
        foreach ($students as $student) {
            $sub = $submissions->get($student->id);

            // Recompute status dari submitted_at vs deadline (tidak tergantung flag is_late
            // yang bisa stale untuk legacy data).
            $status = match (true) {
                $sub === null, $sub->submitted_at === null => 'Belum mengumpulkan',
                $deadline === null => 'Dikumpulkan (tanpa batas)',
                $sub->submitted_at->greaterThan($deadline) => 'Terlambat',
                default => 'Tepat waktu',
            };

            $diff = '—';
            if ($sub?->submitted_at && $deadline) {
                $minutes = (int) $deadline->diffInMinutes($sub->submitted_at, false);
                $diff = $minutes <= 0
                    ? sprintf('%d menit sebelum deadline', abs($minutes))
                    : sprintf('%d menit setelah deadline', $minutes);
            }

            $rows[] = [
                $no++,
                $student->nisn ?? '—',
                $student->full_name,
                $status,
                $sub?->submitted_at?->format('Y-m-d H:i') ?? '—',
                $diff,
                $sub?->score !== null ? (float) $sub->score : '—',
                $sub?->graded_at?->format('Y-m-d H:i') ?? '—',
                $sub?->feedback ?? '—',
                $sub?->link_url ?? '—',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A4:Z4')->getFont()->setBold(true);
            },
        ];
    }
}
