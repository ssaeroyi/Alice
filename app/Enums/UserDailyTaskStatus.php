<?php

namespace App\Enums;

enum UserDailyTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Claimed = 'claimed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In progress',
            self::Completed => 'Ready to claim',
            self::Claimed => 'Claimed',
        };
    }
}
