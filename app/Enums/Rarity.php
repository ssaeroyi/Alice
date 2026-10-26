<?php

namespace App\Enums;

enum Rarity: string
{
    case Common = 'common';
    case Rare = 'rare';
    case Epic = 'epic';

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Common',
            self::Rare => 'Rare',
            self::Epic => 'Epic',
        };
    }
}
