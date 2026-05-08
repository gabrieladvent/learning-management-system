<?php

namespace App\Models\Enums;

enum MaterialTypeEnum: string
{
    case Text = 'text';
    case File = 'file';
    case Link = 'link';

    public function label(): string
    {
        return match($this) {
            self::Text => 'Teks',
            self::File => 'File',
            self::Link => 'Link',
        };
    }
}
