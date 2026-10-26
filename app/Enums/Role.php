<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Teacher => 'Teacher',
            self::Admin => 'Administrator',
        };
    }
}
