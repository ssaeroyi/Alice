<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\AuthoredActions;
use App\Games\Journal\StoryProfile;
use App\Models\Story;

/**
 * Auto tiled-arrange move: story words with shuffled letter tiles, rebuilt by
 * tapping. Shares AuthoredActions::arrangeRow with the teacher-authored
 * version so there is one code path; the auto move always uses its full
 * item count.
 */
class ArrangeGenerator implements ActionGenerator
{
    private const ITEMS = 3;

    public function key(): string
    {
        return 'arrange';
    }

    public function feasibility(StoryProfile $profile): int
    {
        return count(AuthoredActions::scramblePool($profile));
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        return AuthoredActions::arrangeRow(['need' => self::ITEMS], $profile)
            ?? throw new \LogicException('ArrangeGenerator::generate called without a scramble pool.');
    }
}
