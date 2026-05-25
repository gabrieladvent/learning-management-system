<?php

namespace App\Models\Enums;

enum LearningProgressEventType: string
{
    case Open = 'open';
    case Focus = 'focus';
    case Blur = 'blur';
    case Heartbeat = 'heartbeat';
    case Idle = 'idle';
    case Close = 'close';

    public function isActiveState(): bool
    {
        return match ($this) {
            self::Open, self::Focus => true,
            default => false,
        };
    }

    public function isIdleState(): bool
    {
        return match ($this) {
            self::Blur, self::Idle => true,
            default => false,
        };
    }
}
