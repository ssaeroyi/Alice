<?php

namespace App\Support;

use App\Models\User;

/**
 * PointsRank — derives the reading-themed title ladder from a user's total
 * points (the single progression foundation, S1 gamification model).
 *
 * The ladder itself lives in config/gamification.php under 'points.titles'.
 * This helper is a pure, testable read of that config against one number
 * (users.points); it writes nothing and spends nothing.
 *
 * Contract:
 *  - title   : the highest tier whose min_points the user has reached
 *  - tier    : zero-based index of that tier
 *  - icon    : the tier's vendored UI glyph name (config points.titles.icon)
 *  - next_*  : the next tier up (null when already top of the ladder)
 *  - progress: percentage points travelled from current tier floor to the
 *              next floor (1.0 when at the top)
 */
class PointsRank
{
    /** @return array{points:int,title:string,tier:int,icon:string,next_title:?string,points_to_next:?int,progress:float} */
    public static function for(User $user): array
    {
        return self::forPoints((int) ($user->points ?? 0));
    }

    /** @return array{points:int,title:string,tier:int,icon:string,next_title:?string,points_to_next:?int,progress:float} */
    public static function forPoints(int $points): array
    {
        $ladder = config('gamification.points.titles', []);

        usort($ladder, fn ($a, $b) => $a['min_points'] <=> $b['min_points']);

        $current = $ladder[0] ?? ['name' => 'New Reader', 'min_points' => 0];
        $tier = 0;

        foreach ($ladder as $index => $step) {
            if ($points >= (int) $step['min_points']) {
                $current = $step;
                $tier = $index;
            }
        }

        $next = isset($ladder[$tier + 1]) ? $ladder[$tier + 1] : null;

        $floor = (int) $current['min_points'];
        $ceiling = $next !== null ? (int) $next['min_points'] : null;

        if ($ceiling === null || $ceiling <= $floor) {
            $progress = 1.0;
        } else {
            $progress = round(min(1.0, max(0.0, ($points - $floor) / ($ceiling - $floor))), 4);
        }

        return [
            'points' => $points,
            'title' => (string) $current['name'],
            'tier' => $tier,
            'icon' => (string) ($current['icon'] ?? 'book-cover'),
            'next_title' => $next !== null ? (string) $next['name'] : null,
            'points_to_next' => $next !== null ? max(0, (int) $next['min_points'] - $points) : null,
            'progress' => $progress,
        ];
    }
}
