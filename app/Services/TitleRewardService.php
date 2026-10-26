<?php

namespace App\Services;

use App\Models\User;
use App\Support\NotificationService;
use App\Support\PointsRank;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Title-ladder promotion ceremony (T-title slice, gamification S1/S8).
 *
 * Awarded when a user's points cross one or more Title Ladder tiers: the badge
 * for each freshly reached tier plus a small escalating per-tier keys grant.
 *
 * Callers:
 *  - RewardService's points-credit settles (quiz, story-clear, drill, game,
 *    daily task) invoke it inside their own locked transaction after crediting
 *    points;
 *  - the reading summary page invokes it once per load (reading points accrue
 *    via per-minute heartbeats, which never pay here to avoid mid-read ceremony).
 *
 * It is safe under BOTH contexts because it re-locks the user row and re-derives
 * the promotion decision from the fresh, committed points + rank_title inside
 * that lock — so two racing callers (e.g. concurrent summary loads) can never
 * double-grant keys or double-write a badge. The user's `rank_title` records the
 * highest tier already celebrated, making the whole thing idempotent: a settle
 * only pays the tiers actually crossed since the last celebration, and replays /
 * re-heartbeats can never re-reward. Bookworm (tier 1) is the lowest paying tier;
 * the New Reader baseline never pays. Dev Tools write users.points directly and
 * never reach this class, so they can never farm the keys.
 */
class TitleRewardService
{
    /**
     * Detect and pay one title promotion, once per crossed tier, atomically.
     *
     * Returns the ceremony payload (empty array when nothing promoted) for the
     * summary / quiz-results views to surface:
     *
     *     ['promoted' => true, 'title' => string, 'icon' => string,
     *      'keys' => int, 'titles' => array<int, array{name:string,icon:string}>,
     *      'badges' => array<int, array{name:string,rarity:string,asset_path:string}>]
     *
     * @return array<string, mixed>
     */
    public static function congratulate(User $user, Carbon $now): array
    {
        return DB::transaction(function () use ($user, $now): array {
            // Re-lock the user row so the promotion decision, key grant, badge
            // awards and rank_title write are atomic against concurrent callers.
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($fresh === null) {
                return [];
            }

            $rank = PointsRank::forPoints((int) $fresh->points);
            $tier = (int) $rank['tier'];
            $currentTitle = (string) $rank['title'];

            $freshRankTitle = $fresh->rank_title;
            $reachedTier = $freshRankTitle === null || $freshRankTitle === ''
                ? 0
                : self::tierForTitle($freshRankTitle);

            // No crossing (tier at/below the celebrated highest, or still on the
            // New Reader baseline) → nothing to do. Early return keeps this a
            // cheap no-op on the common "points didn't move tiers" path.
            if ($tier <= $reachedTier || $tier < 1) {
                return [];
            }

            // Newly crossed tiers in (reachedTier, tier], skipping New Reader (0).
            $schedule = config('gamification.rank.promotion_keys', []);
            $names = self::ladder()['names'];

            $keys = 0;
            $titles = [];
            for ($t = $reachedTier + 1; $t <= $tier; $t++) {
                if ($t < 1) {
                    continue;
                }
                $tierKeys = (int) ($schedule[$t] ?? 0);
                $tierName = $names[$t] ?? $currentTitle;
                $keys += $tierKeys;
                $titles[] = ['name' => $tierName, 'icon' => self::iconForTier($t)];

                // Persist each crossed tier as an inbox row (deduped per tier)
                // so a promotion earned mid-flow is never invisible later.
                NotificationService::rankPromoted($user, $t, $tierName, $tierKeys);
            }

            // Award the badge for every newly reached tier. BadgeService skips
            // already-owned and only awards tiers the user now satisfies, so a
            // multi-jump pays exactly the crossed badges.
            $badges = BadgeService::award($fresh, ['rank'], $now);

            if ($keys > 0) {
                RewardService::grant($fresh, $keys, 'rank_promotion', 'rank', null);
            }

            // Celebrate the highest tier reached so future settles can't re-pay
            // any of the tiers just crossed. Write through the locked row.
            $fresh->forceFill(['rank_title' => $currentTitle])->save();

            // Propagate the fresh balance/ran/streak state back to the caller's
            // model so reward popups read the post-promotion totals.
            $user->setRawAttributes($fresh->getAttributes());

            return [
                'promoted' => true,
                'title' => $currentTitle,
                'icon' => (string) $rank['icon'],
                'keys' => $keys,
                'titles' => $titles,
                'badges' => BadgeService::summarize($badges),
            ];
        });
    }

    /**
     * Build the tier-name lookup from the ladder once per request.
     *
     * @return array{names: array<int, string>, index: array<string, int>}
     */
    private static function ladder(): array
    {
        $steps = config('gamification.points.titles', []);
        usort($steps, fn ($a, $b) => $a['min_points'] <=> $b['min_points']);

        $names = [];
        $index = [];
        foreach ($steps as $i => $step) {
            $names[$i] = (string) $step['name'];
            $index[(string) $step['name']] = $i;
        }

        return ['names' => $names, 'index' => $index];
    }

    /**
     * The tier index a stored rank_title name maps to (0 when unknown).
     */
    private static function tierForTitle(string $title): int
    {
        return self::ladder()['index'][$title] ?? 0;
    }

    private static function iconForTier(int $tier): string
    {
        $steps = config('gamification.points.titles', []);

        return (string) ($steps[$tier]['icon'] ?? 'book-cover');
    }
}
