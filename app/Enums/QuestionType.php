<?php

namespace App\Enums;

enum QuestionType: string
{
    case Mcq = 'mcq';
    case TrueFalse = 'true_false';
    case Identification = 'identification';
    case Matching = 'matching';
    case Sequence = 'sequence';

    public function label(): string
    {
        return match ($this) {
            self::Mcq => 'Multiple choice',
            self::TrueFalse => 'True / False',
            self::Identification => 'Identification',
            self::Matching => 'Matching',
            self::Sequence => 'Sequence',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Mcq => 'MCQ',
            self::TrueFalse => 'True-False',
            self::Identification => 'Identify',
            self::Matching => 'Matching',
            self::Sequence => 'Sequence',
        };
    }
}
