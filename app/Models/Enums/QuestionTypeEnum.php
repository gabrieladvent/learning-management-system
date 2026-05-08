<?php

namespace App\Models\Enums;

enum QuestionTypeEnum: string
{
    case MultipleChoice = 'multiple_choice';
    case ShortAnswer    = 'short_answer';
    case Essay          = 'essay';

    public function label(): string
    {
        return match($this) {
            self::MultipleChoice => 'Pilihan Ganda',
            self::ShortAnswer    => 'Jawaban Singkat',
            self::Essay          => 'Essay',
        };
    }
}
