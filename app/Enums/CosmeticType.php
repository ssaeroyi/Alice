<?php

namespace App\Enums;

enum CosmeticType: string
{
    case Stamp = 'stamp';
    case Theme = 'theme';
    case Frame = 'frame';

    public function label(): string
    {
        return match ($this) {
            self::Stamp => 'Stamp skin',
            self::Theme => 'Shelf theme',
            self::Frame => 'Badge frame',
        };
    }
}
