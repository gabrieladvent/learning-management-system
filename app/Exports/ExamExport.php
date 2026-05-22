<?php

namespace App\Exports;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ExamExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(protected Exam $exam) {}

    public function title(): string
    {
        return mb_substr(preg_replace('/[^\w\s-]/u', '', $this->exam->title) ?: 'Exam', 0, 31);
    }

    public function array(): array
    {
        $exam = $this->exam->loadMissing([
            'questions',
            'material.classroomSubject.classroom.students',
            'sessions.answers',
        ]);

        $students = $exam->material?->classroomSubject?->classroom?->students ?? collect();
        $isSubmission = $exam->mode?->value === 'submission';

        $rows = [];

        // Title
        $rows[] = [sprintf(
            'Export Ujian — %s · %s · %s',
            $exam->title,
            $exam->material?->classroomSubject?->subject?->name ?? '—',
            $exam->material?->classroomSubject?->classroom?->name ?? '—',
        )];
        $rows[] = ['Mode', $exam->mode?->value, 'Max score', $exam->max_score];
        $rows[] = [];

        if ($isSubmission) {
            $rows = array_merge($rows, $this->submissionRows($exam, $students));
        } else {
            $rows = array_merge($rows, $this->sessionRows($exam, $students));
        }

        return $rows;
    }

    /**
     * Mode online_quiz: 1 baris per siswa, kolom = soal #1, #2, ..., total, started, submitted, reason.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array<int, mixed>>
     */
    private function sessionRows(Exam $exam, $students): array
    {
        $questions = $exam->questions->sortBy('order')->values();

        $header = ['No', 'NISN', 'Nama Siswa'];
        foreach ($questions as $i => $q) {
            $header[] = sprintf('Q%d jawaban', $i + 1);
            $header[] = sprintf('Q%d skor (max %s)', $i + 1, $q->score);
        }
        $header[] = 'Total Skor';
        $header[] = 'Mulai';
        $header[] = 'Submit';
        $header[] = 'Alasan Submit';

        $rows = [$header];

        $sessionsByStudent = $exam->sessions->keyBy('student_id');

        $no = 1;
        foreach ($students as $student) {
            $row = [$no++, $student->nisn ?? '—', $student->full_name];

            $session = $sessionsByStudent->get($student->id);
            $answersByQuestion = $session?->answers->keyBy('exam_question_id') ?? collect();

            foreach ($questions as $q) {
                $answer = $answersByQuestion->get($q->id);
                $row[] = $answer?->answer ?? '—';
                $row[] = $answer?->score !== null ? (float) $answer->score : '—';
            }

            $row[] = $session?->total_score !== null ? (float) $session->total_score : '—';
            $row[] = $session?->started_at?->format('Y-m-d H:i') ?? '—';
            $row[] = $session?->submitted_at?->format('Y-m-d H:i') ?? '—';
            $row[] = $session?->submission_reason ?? '—';

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Mode submission: 1 baris per siswa, kolom = file count, content, link, score, feedback.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array<int, mixed>>
     */
    private function submissionRows(Exam $exam, $students): array
    {
        $submissions = ExamSubmission::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $header = ['No', 'NISN', 'Nama Siswa', 'Submit', 'Konten Teks', 'Link', 'Skor', 'Feedback'];
        $rows = [$header];

        $no = 1;
        foreach ($students as $student) {
            $sub = $submissions->get($student->id);
            $rows[] = [
                $no++,
                $student->nisn ?? '—',
                $student->full_name,
                $sub?->submitted_at?->format('Y-m-d H:i') ?? '—',
                $sub?->content ? mb_substr($sub->content, 0, 500) : '—',
                $sub?->link_url ?? '—',
                $sub?->score !== null ? (float) $sub->score : '—',
                $sub?->feedback ?? '—',
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
                $sheet->getStyle('A4:ZZ4')->getFont()->setBold(true);
            },
        ];
    }
}
