<?php

namespace App\Enums;

enum GradeLevel: string
{
    case Grade7 = '7';
    case Grade8 = '8';
    case Grade9 = '9';
    case Grade10 = '10';

    public function label(): string
    {
        return 'Grade '.$this->value;
    }
}
