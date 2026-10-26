<?php

namespace App\Enums;

enum CorrectOption: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';

    public function letter(): string
    {
        return strtoupper($this->value);
    }

    public function label(): string
    {
        return $this->letter();
    }
}
