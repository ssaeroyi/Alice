<?php

namespace App\Enums;

enum StoryProgressStatus: string
{
    case Reading = 'reading';
    case Read = 'read';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Reading => 'Reading',
            self::Read => 'Read',
            self::Completed => 'Completed',
        };
    }
}
