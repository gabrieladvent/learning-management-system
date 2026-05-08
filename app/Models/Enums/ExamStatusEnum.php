<?php

namespace App\Models\Enums;

enum ExamStatusEnum: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Closed    = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Draft',
            self::Published => 'Dipublikasikan',
            self::Closed    => 'Ditutup',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft     => 'gray',
            self::Published => 'success',
            self::Closed    => 'danger',
        };
    }
}
