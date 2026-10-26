<?php

namespace App\Enums;

use App\Support\SeededShuffle;

enum ComprehensionLevel: string
{
    case Independent = 'independent';
    case Instructional = 'instructional';
    case Frustration = 'frustration';
    case Critical = 'critical';

    /**
     * Deterministically pick a motivational phrase for this level. The seed
     * keeps the same phrase for the same attempt across page revisits.
     */
    public function motivation(string $seed): string
    {
        $phrases = config('placement.motivational_phrases.'.$this->value, []);

        return SeededShuffle::pick($phrases, $seed) ?? '';
    }

    public function label(): string
    {
        return match ($this) {
            self::Independent => 'Independent',
            self::Instructional => 'Instructional',
            self::Frustration => 'Frustration',
            self::Critical => 'Critical',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Independent => 'Reads comfortably on their own.',
            self::Instructional => 'Reads well with a little support.',
            self::Frustration => 'Needs more practice and support.',
            self::Critical => 'Needs intensive reading support.',
        };
    }
}
