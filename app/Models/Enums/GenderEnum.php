<?php

namespace App\Models\Enums;

enum GenderEnum: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match($this) {
            self::Male   => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }
}
