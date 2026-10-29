<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Models\Badge;
use App\Models\GameRound;
use App\Models\SavedWord;
use App\Models\StoryBattle;
use App\Models\StoryBattleAction;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use App\Support\NotificationService;
use App\Support\PointsRank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Badge subsystem (gamification model, slice S8).
 *
 * Badges are read-only trophies — they never move keys or points, so the S1
 * "RewardService is the ONLY consumer of economy constants" mandate does not
 * apply to the award itself; the award only writes `user_badges`. Eligibility
 * is evaluated against real domain data (completed stories, genres, saved
 * words, perfect quizzes, streaks, stamps, games, reading minutes, drills),
 * never client claims. The catalog lives in config/badges.php and is seeded
 * by BadgeSeeder. `progress()` reports 0–100 toward each key so the shelf can
 * render progress bars + a per-category "next up".
 */
class BadgeService
{
    /**
     * Per-request memo of every count aggregate a badge reads, keyed by user.
     * Shared by eligible(), progress(), progressMap() and award() so a hub
     * render (or a multi-key award sweep) resolves each aggregate exactly
     * once instead of re-counting per badge key — the same per-request memo
     * convention as Assignment::$quizMemo. award() clears it immediately
     * before evaluating so a just-saved quiz/round/stamp feeds fresh counts.
     *
     * @var array<int, array<string, int|array<string, int>>>
     */
    private static array $aggregateMemo = [];

    /**
     * Which badge keys to re-evaluate for a given lifecycle event. Controllers
     * fire the narrowest set of events so we only run the relevant aggregates.
     */
    private const EVENT_KEYS = [
        'rank' => ['title_bookworm', 'title_page_turner', 'title_story_explorer', 'title_chapter_master', 'title_bibliophile', 'title_word_sage', 'title_literary_ace', 'title_tale_weaver', 'title_benevolent'],
        'story' => ['first_story', 'stories_5', 'stories_10', 'stories_25', 'stories_50', 'genre_explorer', 'favorite_story'],
        'reading' => ['first_read', 'reading_60', 'reading_300', 'reading_1200', 'reading_3000', 'reading_10000'],
        'quiz' => ['quiz_first', 'quiz_10', 'quiz_50', 'quiz_100', 'quiz_250', 'perfect_quiz', 'perfect_quiz_3', 'perfect_quiz_10'],
        'word' => ['first_word', 'word_collector_25', 'word_collector_100', 'word_collector_500', 'word_collector_1000'],
        'game' => ['game_first', 'game_10', 'game_50', 'game_100', 'ws_5', 'match_5', 'sentence_5', 'game_all_types'],
        'stamp' => ['first_stamp', 'stamp_30', 'stamp_100', 'stamp_365', 'streak_3', 'streak_7', 'streak_14', 'streak_30', 'streak_100', 'perfect_week', 'perfect_month'],
        'freeze' => ['freeze_first', 'freeze_10', 'freeze_25'],
        'freeze_buy' => ['freeze_buy_1'],
        'read_stamp' => ['early_bird', 'night_owl'],
        'drill' => ['drill_10', 'drill_50', 'drill_200'],
        'battle' => ['battle_first', 'battle_5', 'battle_10', 'battle_25', 'battle_flawless', 'battle_flawless_5', 'journal_25', 'journal_100', 'journal_all_kinds', 'battle_days_3', 'battle_variety_5', 'battle_dawn', 'battle_owl'],
    ];

    /**
     * Title badge key => index into config('gamification.points.titles'),
     * ascending (index 0 is the free 'New Reader' tier, so no badge). Shared by
     * progressData()'s per-card point pairs and unlockStats()'s stale-row guard
     * so the ladder order lives in exactly one place.
     */
    private const TITLE_TIER = [
        'title_bookworm' => 1,
        'title_page_turner' => 2,
        'title_story_explorer' => 3,
        'title_chapter_master' => 4,
        'title_bibliophile' => 5,
        'title_word_sage' => 6,
        'title_literary_ace' => 7,
        'title_tale_weaver' => 8,
        'title_benevolent' => 9,
    ];

    /**
     * Evaluate the badges named by $events against the user's domain data and
     * persist any newly-earned ones. Returns the Badge models awarded this
     * call (possibly empty). Idempotent: a badge is only ever awarded once.
     */
    public static function award(User $user, array $events, Carbon $now): Collection
    {
        self::$aggregateMemo = [];

        $keys = collect($events)
            ->flatMap(fn ($event) => self::EVENT_KEYS[$event] ?? [])
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        $badges = Badge::query()->whereIn('key', $keys)->where('active', true)->get();

        if ($badges->isEmpty()) {
            return collect();
        }

        return DB::transaction(function () use ($user, $badges, $events, $now): Collection {
            $owned = UserBadge::query()->where('user_id', $user->id)->pluck('badge_id')->all();
            $awarded = collect();

            foreach ($badges as $badge) {
                if (in_array($badge->id, $owned, true)) {
                    continue;
                }

                if (! self::eligible($user, $badge->key, $now)) {
                    continue;
                }

                UserBadge::create([
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'awarded_at' => $now,
                ]);

                $awarded->push($badge);
            }

            // Badges are trophies, but an unlock is worth an inbox row so the
            // student can find it again. Rank badges are covered by the title
            // promotion's own notification (achievement.title), so the rank
            // event never double-notifies here. Idempotency carries over: a
            // replay finds every badge owned, $awarded stays empty, and no rows
            // stack.
            if (! in_array('rank', $events, true)) {
                foreach ($awarded as $badge) {
                    NotificationService::badgeUnlocked($user, (int) $badge->id, (string) $badge->name);
                }
            }

            return $awarded;
        });
    }

    /**
     * Compact, serialisation-safe summary of awarded badges for the one-shot
     * game banner. Plain arrays round-trip a database session safely and keep
     * live Eloquent models out of the session — only the banner consumes this
     * flash, so nothing ever rebuilds full models from it.
     */
    public static function summarize(Collection $awarded): array
    {
        return $awarded
            ->map(fn (Badge $badge) => [
                'name' => $badge->name,
                'rarity' => $badge->rarity?->value ?? 'common',
                'asset_path' => $badge->asset_path,
            ])
            ->values()
            ->all();
    }

    /**
     * Whether the user's stored/aggregate state satisfies a badge key.
     */
    public static function eligible(User $user, string $key, Carbon $now): bool
    {
        $t = config('badges.thresholds', []);

        return match ($key) {
            'title_bookworm' => PointsRank::for($user)['tier'] >= 1,
            'title_page_turner' => PointsRank::for($user)['tier'] >= 2,
            'title_story_explorer' => PointsRank::for($user)['tier'] >= 3,
            'title_chapter_master' => PointsRank::for($user)['tier'] >= 4,
            'title_bibliophile' => PointsRank::for($user)['tier'] >= 5,
            'title_word_sage' => PointsRank::for($user)['tier'] >= 6,
            'title_literary_ace' => PointsRank::for($user)['tier'] >= 7,
            'title_tale_weaver' => PointsRank::for($user)['tier'] >= 8,
            'title_benevolent' => PointsRank::for($user)['tier'] >= 9,

            'first_story' => self::completedCount($user->id) >= 1,
            'stories_5' => self::completedCount($user->id) >= ($t['stories'][0] ?? 5),
            'stories_10' => self::completedCount($user->id) >= ($t['stories'][1] ?? 10),
            'stories_25' => self::completedCount($user->id) >= ($t['stories'][2] ?? 25),
            'stories_50' => self::completedCount($user->id) >= ($t['stories'][3] ?? 50),
            'genre_explorer' => self::completedGenreCount($user->id) >= ($t['genres'] ?? 5),
            'favorite_story' => self::favoriteCount($user->id) >= 1,

            'first_read' => self::readingSeconds($user->id) > 0,
            'reading_60' => self::readingMinutes($user->id) >= ($t['reading_minutes'][0] ?? 60),
            'reading_300' => self::readingMinutes($user->id) >= ($t['reading_minutes'][1] ?? 300),
            'reading_1200' => self::readingMinutes($user->id) >= ($t['reading_minutes'][2] ?? 1200),
            'reading_3000' => self::readingMinutes($user->id) >= ($t['reading_minutes'][3] ?? 3000),
            'reading_10000' => self::readingMinutes($user->id) >= ($t['reading_minutes'][4] ?? 10000),

            'quiz_first' => self::quizCount($user->id) >= 1,
            'quiz_10' => self::quizCount($user->id) >= ($t['quizzes'][0] ?? 10),
            'quiz_50' => self::quizCount($user->id) >= ($t['quizzes'][1] ?? 50),
            'quiz_100' => self::quizCount($user->id) >= ($t['quizzes'][2] ?? 100),
            'quiz_250' => self::quizCount($user->id) >= ($t['quizzes'][3] ?? 250),
            'perfect_quiz' => self::perfectQuizCount($user->id) >= 1,
            'perfect_quiz_3' => self::perfectQuizCount($user->id) >= ($t['perfect_quizzes'][0] ?? 3),
            'perfect_quiz_10' => self::perfectQuizCount($user->id) >= ($t['perfect_quizzes'][1] ?? 10),

            'first_word' => self::savedWordCount($user->id) >= 1,
            'word_collector_25' => self::savedWordCount($user->id) >= ($t['words'][0] ?? 25),
            'word_collector_100' => self::savedWordCount($user->id) >= ($t['words'][1] ?? 100),
            'word_collector_500' => self::savedWordCount($user->id) >= ($t['words'][2] ?? 500),
            'word_collector_1000' => self::savedWordCount($user->id) >= ($t['words'][3] ?? 1000),

            'game_first' => self::gameCount($user->id) >= 1,
            'game_10' => self::gameCount($user->id) >= ($t['games'][0] ?? 10),
            'game_50' => self::gameCount($user->id) >= ($t['games'][1] ?? 50),
            'game_100' => self::gameCount($user->id) >= ($t['games'][2] ?? 100),
            'ws_5' => self::gameTypeCount($user->id, 'wordsearch') >= ($t['game_type'] ?? 5),
            'match_5' => self::gameTypeCount($user->id, 'match') >= ($t['game_type'] ?? 5),
            'sentence_5' => self::gameTypeCount($user->id, 'sentence') >= ($t['game_type'] ?? 5),
            'game_all_types' => self::playedAllGameTypes($user->id),

            'first_stamp' => self::stampCount($user->id) >= 1,
            'stamp_30' => self::stampCount($user->id) >= ($t['stamps'][0] ?? 30),
            'stamp_100' => self::stampCount($user->id) >= ($t['stamps'][1] ?? 100),
            'stamp_365' => self::stampCount($user->id) >= ($t['stamps'][2] ?? 365),
            'streak_3' => self::maxStreak($user) >= ($t['streaks'][0] ?? 3),
            'streak_7' => self::maxStreak($user) >= ($t['streaks'][1] ?? 7),
            'streak_14' => self::maxStreak($user) >= ($t['streaks'][2] ?? 14),
            'streak_30' => self::maxStreak($user) >= ($t['streaks'][3] ?? 30),
            'streak_100' => self::maxStreak($user) >= ($t['streaks'][4] ?? 100),
            'perfect_week' => self::currentWeekFullyStamped($user->id, $now),
            'perfect_month' => self::previousMonthFullyStamped($user->id, $now),

            'freeze_first' => self::freezeCount($user) >= 1,
            'freeze_10' => self::freezeCount($user) >= ($t['freezes'][0] ?? 10),
            'freeze_25' => self::freezeCount($user) >= ($t['freezes'][1] ?? 25),
            'freeze_buy_1' => self::freezeBoughtCount($user) >= 1,

            'drill_10' => self::drillCount($user->id) >= ($t['drills'][0] ?? 10),
            'drill_50' => self::drillCount($user->id) >= ($t['drills'][1] ?? 50),
            'drill_200' => self::drillCount($user->id) >= ($t['drills'][2] ?? 200),

            'early_bird' => self::hasStampAtHour($user->id, '<', 7),
            'night_owl' => self::hasStampAtHour($user->id, '>=', 21),

            'battle_first' => self::battlesWon($user->id) >= 1,
            'battle_5' => self::battlesWon($user->id) >= 5,
            'battle_10' => self::battlesWon($user->id) >= 10,
            'battle_25' => self::battlesWon($user->id) >= 25,
            'battle_flawless' => self::battlesFlawless($user->id) >= 1,
            'battle_flawless_5' => self::battlesFlawless($user->id) >= 5,
            'journal_25' => self::battleActionsDone($user->id) >= 25,
            'journal_100' => self::battleActionsDone($user->id) >= 100,
            'journal_all_kinds' => self::battleActionKinds($user->id) >= 3,
            'battle_days_3' => self::battleWinDays($user->id) >= 3,
            'battle_variety_5' => self::battleStoryVariety($user->id) >= 5,
            'battle_dawn' => self::battleWonAtHour($user->id, '<', 8),
            'battle_owl' => self::battleWonAtHour($user->id, '>=', 21),

            default => false,
        };
    }

    /**
     * How far (0–100) the user is toward earning a badge key, for shelf
     * progress bars. Boolean/existence badges read as 0 or 100.
     */
    public static function progress(User $user, string $key): int
    {
        $t = config('badges.thresholds', []);

        $counts = [
            'stories_5' => $t['stories'][0] ?? 5,
            'stories_10' => $t['stories'][1] ?? 10,
            'stories_25' => $t['stories'][2] ?? 25,
            'stories_50' => $t['stories'][3] ?? 50,

            'reading_60' => $t['reading_minutes'][0] ?? 60,
            'reading_300' => $t['reading_minutes'][1] ?? 300,
            'reading_1200' => $t['reading_minutes'][2] ?? 1200,
            'reading_3000' => $t['reading_minutes'][3] ?? 3000,
            'reading_10000' => $t['reading_minutes'][4] ?? 10000,

            'quiz_10' => $t['quizzes'][0] ?? 10,
            'quiz_50' => $t['quizzes'][1] ?? 50,
            'quiz_100' => $t['quizzes'][2] ?? 100,
            'quiz_250' => $t['quizzes'][3] ?? 250,
            'perfect_quiz_3' => $t['perfect_quizzes'][0] ?? 3,
            'perfect_quiz_10' => $t['perfect_quizzes'][1] ?? 10,

            'word_collector_25' => $t['words'][0] ?? 25,
            'word_collector_100' => $t['words'][1] ?? 100,
            'word_collector_500' => $t['words'][2] ?? 500,
            'word_collector_1000' => $t['words'][3] ?? 1000,

            'game_10' => $t['games'][0] ?? 10,
            'game_50' => $t['games'][1] ?? 50,
            'game_100' => $t['games'][2] ?? 100,
            'ws_5' => $t['game_type'] ?? 5,
            'match_5' => $t['game_type'] ?? 5,
            'sentence_5' => $t['game_type'] ?? 5,

            'stamp_30' => $t['stamps'][0] ?? 30,
            'stamp_100' => $t['stamps'][1] ?? 100,
            'stamp_365' => $t['stamps'][2] ?? 365,
            'streak_3' => $t['streaks'][0] ?? 3,
            'streak_7' => $t['streaks'][1] ?? 7,
            'streak_14' => $t['streaks'][2] ?? 14,
            'streak_30' => $t['streaks'][3] ?? 30,
            'streak_100' => $t['streaks'][4] ?? 100,

            'freeze_10' => $t['freezes'][0] ?? 10,
            'freeze_25' => $t['freezes'][1] ?? 25,

            'drill_10' => $t['drills'][0] ?? 10,
            'drill_50' => $t['drills'][1] ?? 50,
            'drill_200' => $t['drills'][2] ?? 200,

            'battle_5' => $t['battles'][0] ?? 5,
            'battle_10' => $t['battles'][1] ?? 10,
            'battle_25' => $t['battles'][2] ?? 25,
            'battle_flawless_5' => $t['battle_flawless'] ?? 5,
            'journal_25' => $t['journal_actions'][0] ?? 25,
            'journal_100' => $t['journal_actions'][1] ?? 100,
            'battle_days_3' => $t['battle_days'] ?? 3,
            'battle_variety_5' => $t['battle_variety'] ?? 5,
        ];

        if (array_key_exists($key, $counts)) {
            $value = match (true) {
                str_starts_with($key, 'reading_') => self::readingMinutes($user->id),
                $key === 'ws_5' => self::gameTypeCount($user->id, 'wordsearch'),
                $key === 'match_5' => self::gameTypeCount($user->id, 'match'),
                $key === 'sentence_5' => self::gameTypeCount($user->id, 'sentence'),
                str_starts_with($key, 'streak_') => self::maxStreak($user),
                str_starts_with($key, 'stories_') => self::completedCount($user->id),
                str_starts_with($key, 'quiz_') => self::quizCount($user->id),
                str_starts_with($key, 'perfect_quiz_') => self::perfectQuizCount($user->id),
                str_starts_with($key, 'word_collector_') => self::savedWordCount($user->id),
                str_starts_with($key, 'game_') => self::gameCount($user->id),
                str_starts_with($key, 'stamp_') => self::stampCount($user->id),
                str_starts_with($key, 'freeze_') => self::freezeCount($user),
                str_starts_with($key, 'drill_') => self::drillCount($user->id),
                $key === 'battle_days_3' => self::battleWinDays($user->id),
                $key === 'battle_variety_5' => self::battleStoryVariety($user->id),
                $key === 'battle_flawless_5' => self::battlesFlawless($user->id),
                str_starts_with($key, 'battle_') => self::battlesWon($user->id),
                str_starts_with($key, 'journal_') => self::battleActionsDone($user->id),
                default => 0,
            };

            return (int) min(100, round(($value / $counts[$key]) * 100));
        }

        $booleans = ['first_story', 'genre_explorer', 'favorite_story', 'first_read', 'quiz_first', 'perfect_quiz', 'first_word', 'game_first', 'game_all_types', 'first_stamp', 'perfect_week', 'perfect_month', 'early_bird', 'night_owl', 'freeze_first', 'freeze_buy_1', 'title_bookworm', 'title_page_turner', 'title_story_explorer', 'title_chapter_master', 'title_bibliophile', 'title_word_sage', 'title_literary_ace', 'title_tale_weaver', 'title_benevolent', 'battle_first', 'battle_flawless', 'journal_all_kinds', 'battle_dawn', 'battle_owl'];
        if (in_array($key, $booleans, true)) {
            return self::eligible($user, $key, Carbon::now()) ? 100 : 0;
        }

        return 0;
    }

    /**
     * Batch equivalent of {@see progress()} for the whole shelf, as percentages:
     * `key => pct (0–100)` for every badge in the catalog, matching `progress()`
     * key-for-key. Count keys read as value/target; title keys follow the point
     * ladder, so a locked title climbs instead of sitting at 0.
     *
     * @return array<string, int>
     */
    public static function progressMap(User $user, Carbon $now): array
    {
        $pct = static fn (int $value, int $target): int => $target > 0 ? (int) min(100, round(($value / $target) * 100)) : 0;

        $map = [];
        foreach (self::progressData($user, $now) as $key => $value) {
            $map[$key] = $pct($value['current'], $value['target']);
        }

        return $map;
    }

    /**
     * The shelf's raw progress pairs: `key => ['current', 'target', 'unit']`
     * for every badge with a measurable requirement. Cards render these as
     * "65 / 150 pts" (titles) or "2 / 5" (counts) instead of a bare percentage,
     * so a locked badge shows how far the reader has actually come.
     *
     * @return array<string, array{current: int, target: int, unit: string|null}>
     */
    public static function progressValues(User $user, Carbon $now): array
    {
        return self::progressData($user, $now);
    }

    /**
     * Shelf-wide reach for the badge detail modal: how many real students hold
     * each badge and how many real students exist to hold it, so a card can
     * say "6% of students got this achievement" (Steam style).
     *
     * Both sides count ONLY real student accounts — role student, not
     * suspended, not deleted, and passing the shared realAccounts() scope
     * (User::scopeRealAccounts), which drops scratch/preview, demo and test
     * accounts. Two aggregates total, never one query per badge.
     *
     * A title badge also only counts when its holder's CURRENT points still
     * back the tier: points can be lowered after the award (see
     * DevTools::setPoints), and a rank nobody holds must never be advertised.
     *
     * @return array{holders: array<int, int>, students: int}
     */
    public static function unlockStats(): array
    {
        $rows = UserBadge::query()
            ->whereHas('user', fn ($q) => self::realStudents($q))
            ->with(['user:id,points', 'badge:id,key'])
            ->get(['id', 'user_id', 'badge_id']);

        $holders = [];
        foreach ($rows as $row) {
            if (self::isUnearnedTitle($row->badge?->key, (int) $row->user?->points)) {
                continue;
            }

            $holders[$row->badge_id] = ($holders[$row->badge_id] ?? 0) + 1;
        }

        $students = self::realStudents(User::query())->count();

        return ['holders' => $holders, 'students' => $students];
    }

    /**
     * The single "real student" filter shared by every side of a reach
     * statistic: applies the model's realAccounts() scope on top of the
     * role/suspension/deletion rules, so denominator and numerator can never
     * drift apart.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function realStudents($query)
    {
        return $query
            ->realAccounts()
            ->where('role', Role::Student)
            ->whereNull('suspended_at')
            ->whereNull('deleted_at');
    }

    /**
     * True when a `title_*` row is stale: the tier's min_points (config
     * gamification.points.titles, ordered) is no longer covered by the
     * holder's current points, so the rank was dropped after the award.
     */
    private static function isUnearnedTitle(?string $key, int $points): bool
    {
        if ($key === null || ! isset(self::TITLE_TIER[$key])) {
            return false;
        }

        $ladder = collect(config('gamification.points.titles', []))
            ->sortBy('min_points')
            ->values();
        $target = (int) ($ladder[self::TITLE_TIER[$key]]['min_points'] ?? 0);

        return $target > 0 && $points < $target;
    }

    /**
     * Batch equivalent of {@see progress()} for the whole shelf: every count
     * aggregate is computed ONCE and shared across all count-based and boolean
     * keys, so rendering "X of 58 unlocked" + per-category "next up" chips +
     * one progress bar per locked tile costs a handful of queries instead of
     * one query per badge key per tile. Returns `key => pct (0–100)` for every
     * badge in the catalog, matching `progress()` key-for-key.
     *
     * @return array<string, array{current: int, target: int, unit: string|null}>
     */
    private static function progressData(User $user, Carbon $now): array
    {
        $id = $user->id;
        $t = config('badges.thresholds', []);

        // Aggregates computed once, reused by every key that reads them.
        $completed = self::completedCount($id);
        $readingSeconds = self::readingSeconds($id);
        $readingMinutes = intdiv($readingSeconds, 60);
        $quizzes = self::quizCount($id);
        $perfect = self::perfectQuizCount($id);
        $words = self::savedWordCount($id);
        $games = self::gameCount($id);
        $gameTypes = [
            'wordsearch' => self::gameTypeCount($id, 'wordsearch'),
            'match' => self::gameTypeCount($id, 'match'),
            'sentence' => self::gameTypeCount($id, 'sentence'),
        ];
        $stamps = self::stampCount($id);
        $streak = self::maxStreak($user);
        $freezes = self::freezeCount($user);
        $freezeBought = self::freezeBoughtCount($user);
        $drills = self::drillCount($id);

        // Count-based keys (mirror progress() line-for-line).
        $counts = [
            'stories_5' => $t['stories'][0] ?? 5,
            'stories_10' => $t['stories'][1] ?? 10,
            'stories_25' => $t['stories'][2] ?? 25,
            'stories_50' => $t['stories'][3] ?? 50,

            'reading_60' => $t['reading_minutes'][0] ?? 60,
            'reading_300' => $t['reading_minutes'][1] ?? 300,
            'reading_1200' => $t['reading_minutes'][2] ?? 1200,
            'reading_3000' => $t['reading_minutes'][3] ?? 3000,
            'reading_10000' => $t['reading_minutes'][4] ?? 10000,

            'quiz_10' => $t['quizzes'][0] ?? 10,
            'quiz_50' => $t['quizzes'][1] ?? 50,
            'quiz_100' => $t['quizzes'][2] ?? 100,
            'quiz_250' => $t['quizzes'][3] ?? 250,
            'perfect_quiz_3' => $t['perfect_quizzes'][0] ?? 3,
            'perfect_quiz_10' => $t['perfect_quizzes'][1] ?? 10,

            'word_collector_25' => $t['words'][0] ?? 25,
            'word_collector_100' => $t['words'][1] ?? 100,
            'word_collector_500' => $t['words'][2] ?? 500,
            'word_collector_1000' => $t['words'][3] ?? 1000,

            'game_10' => $t['games'][0] ?? 10,
            'game_50' => $t['games'][1] ?? 50,
            'game_100' => $t['games'][2] ?? 100,
            'ws_5' => $t['game_type'] ?? 5,
            'match_5' => $t['game_type'] ?? 5,
            'sentence_5' => $t['game_type'] ?? 5,

            'stamp_30' => $t['stamps'][0] ?? 30,
            'stamp_100' => $t['stamps'][1] ?? 100,
            'stamp_365' => $t['stamps'][2] ?? 365,
            'streak_3' => $t['streaks'][0] ?? 3,
            'streak_7' => $t['streaks'][1] ?? 7,
            'streak_14' => $t['streaks'][2] ?? 14,
            'streak_30' => $t['streaks'][3] ?? 30,
            'streak_100' => $t['streaks'][4] ?? 100,

            'freeze_10' => $t['freezes'][0] ?? 10,
            'freeze_25' => $t['freezes'][1] ?? 25,

            'drill_10' => $t['drills'][0] ?? 10,
            'drill_50' => $t['drills'][1] ?? 50,
            'drill_200' => $t['drills'][2] ?? 200,
        ];

        $values = [];

        foreach ($counts as $key => $target) {
            $value = match (true) {
                str_starts_with($key, 'reading_') => $readingMinutes,
                $key === 'ws_5' => $gameTypes['wordsearch'],
                $key === 'match_5' => $gameTypes['match'],
                $key === 'sentence_5' => $gameTypes['sentence'],
                str_starts_with($key, 'streak_') => $streak,
                str_starts_with($key, 'stories_') => $completed,
                str_starts_with($key, 'quiz_') => $quizzes,
                str_starts_with($key, 'perfect_quiz_') => $perfect,
                str_starts_with($key, 'word_collector_') => $words,
                str_starts_with($key, 'game_') => $games,
                str_starts_with($key, 'stamp_') => $stamps,
                str_starts_with($key, 'freeze_') => $freezes,
                str_starts_with($key, 'drill_') => $drills,
                default => 0,
            };

            $values[$key] = ['current' => min($value, $target), 'target' => $target, 'unit' => null];
        }

        // Existence keys (mirror progress()'s boolean branch) carry a 0/1 pair,
        // except the two with a natural partial count (genres covered, game
        // types played) which report real progress against their target.
        $tier = PointsRank::for($user)['tier'];
        $flags = [
            'first_story' => [$completed, 1],
            'genre_explorer' => [self::completedGenreCount($id), $t['genres'] ?? 5],
            'favorite_story' => [self::favoriteCount($id), 1],
            'first_read' => [$readingSeconds > 0 ? 1 : 0, 1],
            'quiz_first' => [$quizzes, 1],
            'perfect_quiz' => [$perfect, 1],
            'first_word' => [$words, 1],
            'game_first' => [$games, 1],
            'game_all_types' => [count(array_filter($gameTypes, fn ($total) => $total > 0)), 3],
            'first_stamp' => [$stamps, 1],
            'perfect_week' => [self::currentWeekFullyStamped($id, $now) ? 1 : 0, 1],
            'perfect_month' => [self::previousMonthFullyStamped($id, $now) ? 1 : 0, 1],
            'early_bird' => [self::hasStampAtHour($id, '<', 7) ? 1 : 0, 1],
            'night_owl' => [self::hasStampAtHour($id, '>=', 21) ? 1 : 0, 1],
            'freeze_first' => [$freezes, 1],
            'freeze_buy_1' => [$freezeBought, 1],
        ];

        foreach ($flags as $key => [$current, $target]) {
            $values[$key] = ['current' => min($current, $target), 'target' => $target, 'unit' => null];
        }

        // Title keys follow the point ladder (config gamification.points.titles):
        // the card shows points earned against the tier's min_points, which is
        // exactly what the reader still needs to reach that title.
        $ladder = collect(config('gamification.points.titles', []))
            ->sortBy('min_points')
            ->values();
        $points = (int) ($user->points ?? 0);

        foreach (self::TITLE_TIER as $key => $index) {
            $target = (int) ($ladder[$index]['min_points'] ?? 0);
            $values[$key] = $target > 0
                ? ['current' => min($points, $target), 'target' => $target, 'unit' => 'pts']
                : ['current' => $tier >= $index ? 1 : 0, 'target' => 1, 'unit' => null];
        }

        return $values;
    }

    // ----------------------------------------------------------------------
    // Aggregates
    // ----------------------------------------------------------------------

    /**
     * Resolve an integer aggregate once per request per user. Every count
     * badge reads its aggregate through this, so the first consumer pays the
     * SQL cost and the rest reuse the memo (progressMap + award sweep).
     */
    private static function cachedInt(int $userId, string $metric, callable $resolver): int
    {
        return self::$aggregateMemo[$userId][$metric] ??= (int) $resolver();
    }

    /**
     * Rounds of each mini-game type for the user, one grouped query shared by
     * gameCount, gameTypeCount and playedAllGameTypes (they previously ran
     * three separate COUNT queries plus a full round tally).
     *
     * @return array<string, int>
     */
    private static function gameTypeCounts(int $userId): array
    {
        return self::$aggregateMemo[$userId]['game_types'] ??= GameRound::query()
            ->where('user_id', $userId)
            ->whereIn('game_type', ['wordsearch', 'match', 'sentence'])
            ->selectRaw('game_type, COUNT(*) AS total')
            ->groupBy('game_type')
            ->pluck('total', 'game_type')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** Stories the user has completed (passed that story's quiz). */
    private static function completedCount(int $userId): int
    {
        return self::cachedInt($userId, 'completed', fn () => StoryProgress::query()
            ->where('user_id', $userId)
            ->where('status', StoryProgressStatus::Completed)
            ->count());
    }

    /**
     * Distinct genres covered by the user's completed stories. A single join
     * counts the pivot rows directly — it no longer hydrates every completed
     * StoryProgress row (plus an eager-loaded story.genres tree) into memory.
     * Soft-deleted stories are excluded, mirroring the old $p->story?->genres
     * hydration which never materialised a trashed story's genres.
     */
    private static function completedGenreCount(int $userId): int
    {
        return self::cachedInt($userId, 'completed_genres', fn () => StoryProgress::query()
            ->where('story_progress.user_id', $userId)
            ->where('story_progress.status', StoryProgressStatus::Completed)
            ->join('story_genre', 'story_genre.story_id', '=', 'story_progress.story_id')
            ->join('stories', 'stories.id', '=', 'story_progress.story_id')
            ->whereNull('stories.deleted_at')
            ->distinct()
            ->count('story_genre.genre_id'));
    }

    /** Number of stories the user has favorited. */
    private static function favoriteCount(int $userId): int
    {
        return self::cachedInt($userId, 'favorites', fn () => StoryProgress::query()
            ->where('user_id', $userId)
            ->where('is_favorite', true)
            ->count());
    }

    /** Total accumulated reading seconds across all days. */
    private static function readingSeconds(int $userId): int
    {
        return self::cachedInt($userId, 'reading_seconds', fn () => UserDailyActivity::query()
            ->where('user_id', $userId)
            ->sum('reading_seconds_today'));
    }

    /** Total reading minutes (floor of accumulated seconds / 60). */
    private static function readingMinutes(int $userId): int
    {
        return intdiv(self::readingSeconds($userId), 60);
    }

    /** Number of quiz attempts (all submissions). */
    private static function quizCount(int $userId): int
    {
        return self::cachedInt($userId, 'quizzes', fn () => TestAttempt::query()
            ->where('user_id', $userId)
            ->count());
    }

    /** Number of perfect quiz attempts (passed at 100%). */
    private static function perfectQuizCount(int $userId): int
    {
        return self::cachedInt($userId, 'perfect', fn () => TestAttempt::query()
            ->where('user_id', $userId)
            ->where('passed', true)
            ->where('score_pct', '>=', 100)
            ->count());
    }

    /** Number of saved words in the Word Gym. */
    private static function savedWordCount(int $userId): int
    {
        return self::cachedInt($userId, 'words', fn () => SavedWord::query()
            ->where('user_id', $userId)
            ->count());
    }

    /** Total number of mini-game rounds played (sum of per-type tallies). */
    private static function gameCount(int $userId): int
    {
        return (int) array_sum(self::gameTypeCounts($userId));
    }

    /** Number of mini-game rounds of a single type. */
    private static function gameTypeCount(int $userId, string $type): int
    {
        return self::gameTypeCounts($userId)[$type] ?? 0;
    }

    /** Whether the user has played every mini-game type. */
    private static function playedAllGameTypes(int $userId): bool
    {
        $counts = self::gameTypeCounts($userId);

        return count(array_filter($counts, fn ($total) => $total > 0)) >= 3;
    }

    /** Number of days the user has earned a stamp. */
    private static function stampCount(int $userId): int
    {
        return self::cachedInt($userId, 'stamps', fn () => UserDailyActivity::query()
            ->where('user_id', $userId)
            ->whereNotNull('stamped_at')
            ->count());
    }

    /**
     * Number of words the user has drilled (flashcard-graded), proxied by the
     * count of saved words ever reviewed (`last_drilled_at` set). Flashcard
     * grading is intentionally currency-free (StudyController), so drill
     * progress reads real review activity without touching RewardService.
     */
    private static function drillCount(int $userId): int
    {
        return self::cachedInt($userId, 'drills', fn () => SavedWord::query()
            ->where('user_id', $userId)
            ->whereNotNull('last_drilled_at')
            ->count());
    }

    /** Days of consecutive study (current vs best streak). */
    private static function maxStreak(User $user): int
    {
        return max((int) $user->current_streak, (int) $user->longest_streak);
    }

    /* ---------------------- Field Journal battle counters ------------------ */

    /** Battles won all-time (status = won). */
    private static function battlesWon(int $userId): int
    {
        return self::cachedInt($userId, 'battles_won', fn () => StoryBattle::query()
            ->where('user_id', $userId)
            ->where('status', 'won')
            ->count());
    }

    /** Battles won without losing a single heart. */
    private static function battlesFlawless(int $userId): int
    {
        return self::cachedInt($userId, 'battles_flawless', fn () => StoryBattle::query()
            ->where('user_id', $userId)
            ->where('status', 'won')
            ->where('hearts', '>=', 5)
            ->count());
    }

    /** Journal actions finished, across every battle and story. */
    private static function battleActionsDone(int $userId): int
    {
        return self::cachedInt($userId, 'battle_actions', fn () => StoryBattleAction::query()
            ->where('status', 'done')
            ->whereIn('story_battle_id', StoryBattle::query()->select('id')->where('user_id', $userId))
            ->count());
    }

    /** Distinct action kinds the student has completed (find / fill / choice). */
    private static function battleActionKinds(int $userId): int
    {
        return StoryBattleAction::query()
            ->where('status', 'done')
            ->whereIn('story_battle_id', StoryBattle::query()->select('id')->where('user_id', $userId))
            ->distinct('kind')
            ->count('kind');
    }

    /** Distinct days with at least one won battle. */
    private static function battleWinDays(int $userId): int
    {
        return self::cachedInt($userId, 'battle_days', fn () => (int) DB::table('story_battles')
            ->where('user_id', $userId)
            ->where('status', 'won')
            ->selectRaw('COUNT(DISTINCT DATE(updated_at)) as days')
            ->value('days'));
    }

    /** Distinct stories where the student has at least one won battle. */
    private static function battleStoryVariety(int $userId): int
    {
        return self::cachedInt($userId, 'battle_variety', fn () => StoryBattle::query()
            ->where('user_id', $userId)
            ->where('status', 'won')
            ->distinct('story_id')
            ->count('story_id'));
    }

    /** Whether any won battle finished inside the given hour window. */
    private static function battleWonAtHour(int $userId, string $op, int $hour): bool
    {
        $q = StoryBattle::query()
            ->where('user_id', $userId)
            ->where('status', 'won');

        $cut = sprintf('%02d:00:00', $hour);

        return $op === '<'
            ? $q->whereTime('updated_at', '<', $cut)->exists()
            : $q->whereTime('updated_at', '>=', $cut)->exists();
    }

    /** Streak freezes ever consumed (each shields one missed day). */
    private static function freezeCount(User $user): int
    {
        return (int) $user->streak_freezes_used;
    }

    /** Streak freezes ever bought with keys. */
    private static function freezeBoughtCount(User $user): int
    {
        return (int) $user->streak_freezes_bought;
    }

    /** Whether the user stamped on every calendar day of the current week. */
    private static function currentWeekFullyStamped(int $userId, Carbon $now): bool
    {
        $start = $now->copy()->startOfWeek();
        $end = $now->copy()->endOfWeek();

        $stampedDays = UserDailyActivity::query()
            ->where('user_id', $userId)
            ->whereNotNull('stamped_at')
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('activity_date')
            ->map(fn ($d) => Carbon::parse($d)->day)
            ->unique();

        return $stampedDays->count() >= 7;
    }

    /** Whether every calendar day of the month before $now was stamped. */
    private static function previousMonthFullyStamped(int $userId, Carbon $now): bool
    {
        $prev = $now->copy()->subMonthNoOverflow();

        $stampedDays = UserDailyActivity::query()
            ->where('user_id', $userId)
            ->whereNotNull('stamped_at')
            ->whereYear('activity_date', $prev->year)
            ->whereMonth('activity_date', $prev->month)
            ->pluck('activity_date')
            ->map(fn ($d) => Carbon::parse($d)->day)
            ->unique();

        return $stampedDays->count() >= $prev->daysInMonth;
    }

    /** Whether any stamp was earned at a time matching $operator-hour. */
    private static function hasStampAtHour(int $userId, string $operator, int $hour): bool
    {
        return UserDailyActivity::query()
            ->where('user_id', $userId)
            ->whereNotNull('stamped_at')
            ->whereRaw("HOUR(stamped_at) {$operator} ?", [$hour])
            ->exists();
    }
}
