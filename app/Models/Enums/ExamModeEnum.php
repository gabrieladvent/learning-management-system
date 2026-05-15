<?php

namespace App\Models\Enums;

enum ExamModeEnum: string
{
    case OnlineQuiz = 'online_quiz';
    case Submission = 'submission';

    public function label(): string
    {
        return match ($this) {
            self::OnlineQuiz => 'Soal Interaktif',
            self::Submission => 'Kumpul Jawaban',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OnlineQuiz => 'Siswa kerjakan soal langsung di platform dengan timer (pilihan ganda, essay, dll)',
            self::Submission => 'Siswa kumpul jawaban berupa teks, file, atau link',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OnlineQuiz => 'heroicon-o-clipboard-document-check',
            self::Submission => 'heroicon-o-arrow-up-tray',
        };
    }
}
