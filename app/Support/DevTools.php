<?php

namespace App\Support;

use App\Enums\StoryStatus;
use App\Models\GameRound;
use App\Models\Story;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Models\WordGymDeck;
use App\Services\RewardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin Dev Tools — a QA/cheat console for speed-testing the student economy
 * and the games (SYSTEM_DESIGN §11.3b, ledger "Dev Tools").
 *
 * SAFETY INVARIANT: this helper can only EVER resolve a disposable scratch
 * preview account — the same account View As switches into. A real learner's
 * keys, points, streak or progress can never be targeted: `target()` returns
 * null for anything that is not the scratch student, and every call site
 * refuses on null.
 *
 * Every key movement still flows through RewardService (locked, ledger row,
 * reason `dev_tool` or a real settlement reason), so balances and the audit
 * trail stay consistent with the rest of the app. The "scenario" buttons run
 * the REAL economic paths (settleQuizAttempt / settleGameRound /
 * settleDrill / creditTaskReward) so the numbers tested are exactly the
 * numbers students see at each same-day decay slot / accuracy tier.
 */
class DevTools
{
    public const LEDGER_REASON = 'dev_tool';

    /**
     * The account every dev action targets: the disposable scratch student.
     * During an active preview the current session user is used (ViewAs only
     * ever switches to scratch accounts); otherwise the scratch student is
     * resolved directly. Returns null when no scratch target is available.
     */
    public static function target(): ?User
    {
        if (ViewAs::isActive()) {
            $current = ViewAs::target();

            return $current !== null && ViewAs::isScratchTarget($current) ? $current : null;
        }

        return ViewAs::scratchStudent();
    }

    /** Live snapshot for the page readout and the overlay. */
    public static function state(User $target): array
    {
        $rank = PointsRank::for($target);
        $day = now()->startOfDay()->toDateString();

        $activity = UserDailyActivity::query()
            ->where('user_id', $target->id)
            ->whereDate('activity_date', $day)
            ->first();

        return [
            'user_id' => $target->id,
            'nickname' => $target->nickname ?? 'Scratch Student',
            'email' => $target->email,
            'scratch' => ViewAs::isScratchTarget($target),
            'total_keys' => (int) $target->total_keys,
            'points' => (int) $target->points,
            'title' => $rank['title'],
            'tier' => (int) $rank['tier'],
            'next_title' => $rank['next_title'],
            'points_to_next' => $rank['points_to_next'],
            'current_streak' => (int) $target->current_streak,
            'longest_streak' => (int) $target->longest_streak,
            'streak_freezes' => (int) $target->streak_freezes,
            'last_stamped_date' => $target->last_stamped_date?->toDateString(),
            'reading_seconds_today' => (int) ($activity->reading_seconds_today ?? 0),
            'farmable_today' => RewardService::farmableActionsToday($target, now()),
            'stamped_today' => $activity?->stamped_at !== null,
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function addKeys(User $target, int $amount): array
    {
        RewardService::grant($target, $amount, self::LEDGER_REASON, null, null, ['by' => (int) auth()->id()]);

        return ['ok' => true, 'message' => "Granted {$amount} key(s)."];
    }

    /** @return array{ok:bool,message:string} */
    public static function addPoints(User $target, int $amount): array
    {
        $target->increment('points', $amount);
        $target->refresh();

        return ['ok' => true, 'message' => "Granted {$amount} point(s)."];
    }

    /** @return array{ok:bool,message:string} */
    public static function setPoints(User $target, int $points): array
    {
        $target->forceFill(['points' => max(0, $points)])->save();
        $target->refresh();

        return ['ok' => true, 'message' => "Points set to {$target->points}."];
    }

    /** @return array{ok:bool,message:string} */
    public static function spendKeys(User $target, int $amount): array
    {
        if (! RewardService::spend($target, $amount, self::LEDGER_REASON, null, null, ['by' => (int) auth()->id()])) {
            return ['ok' => false, 'message' => "Cannot spend {$amount} keys — balance is short."];
        }

        return ['ok' => true, 'message' => "Spent {$amount} key(s)."];
    }

    /**
     * Bump today's reading seconds exactly like the reader does: create the
     * day row if absent, then credit the pure-function reading-points delta
     * and the once-per-day goal reward when the goal is crossed.
     *
     * @return array{ok:bool,message:string}
     */
    public static function addReadingSeconds(User $target, int $seconds): array
    {
        $result = DB::transaction(function () use ($target, $seconds) {
            $day = now()->startOfDay()->toDateString();

            $activity = UserDailyActivity::query()
                ->where('user_id', $target->id)
                ->whereDate('activity_date', $day)
                ->lockForUpdate()
                ->first();

            if ($activity === null) {
                $activity = UserDailyActivity::create([
                    'user_id' => $target->id,
                    'activity_date' => $day,
                    'reading_seconds_today' => 0,
                ]);
            }

            $before = (int) $activity->reading_seconds_today;
            $activity->increment('reading_seconds_today', $seconds);
            $newTotal = $before + $seconds;

            RewardService::awardReadingPoints($target, $newTotal, $before);

            $goalMinutes = (int) $target->daily_reading_goal;
            $goalRewarded = false;
            if ($goalMinutes > 0 && $newTotal >= $goalMinutes * 60) {
                $goalRewarded = RewardService::creditGoalReward($target, 'reading', now()) > 0;
            }

            $activity->refresh();

            return ['total' => (int) $activity->reading_seconds_today, 'goal_rewarded' => $goalRewarded];
        });

        $extra = $result['goal_rewarded'] ? ' Reading goal reward credited.' : '';

        return ['ok' => true, 'message' => "Added {$seconds}s of reading (".(int) round($result['total'] / 60)."m today).{$extra}"];
    }

    /** @return array{ok:bool,message:string} */
    public static function stampToday(User $target): array
    {
        $result = RewardService::stampDay($target, 'read', now());

        if (! $result['stamped']) {
            return ['ok' => true, 'message' => 'Today was already stamped.'];
        }

        $freezeUsed = $result['freeze_used'] ? ' (freeze consumed)' : '';

        return ['ok' => true, 'message' => "Day stamped. Streak now {$result['streak']}.{$freezeUsed}"];
    }

    /** @return array{ok:bool,message:string} */
    public static function setStreak(User $target, int $streak): array
    {
        $target->forceFill([
            'current_streak' => max(0, $streak),
            'longest_streak' => max((int) $target->longest_streak, $streak),
        ])->save();
        $target->refresh();

        return ['ok' => true, 'message' => "Streak set to {$target->current_streak}."];
    }

    /** @return array{ok:bool,message:string} */
    public static function setLastStampToday(User $target): array
    {
        $target->forceFill(['last_stamped_date' => now()->toDateString()])->save();
        $target->refresh();

        return ['ok' => true, 'message' => 'Last stamped date set to today.'];
    }

    /** @return array{ok:bool,message:string} */
    public static function addFreeze(User $target): array
    {
        $cap = (int) config('gamification.freeze.hold_cap');
        $held = (int) $target->streak_freezes;

        if ($held >= $cap) {
            return ['ok' => false, 'message' => "Already holding the cap of {$cap} freeze(s)."];
        }

        $target->forceFill(['streak_freezes' => $held + 1])->save();
        $target->refresh();

        return ['ok' => true, 'message' => "Freezes now {$target->streak_freezes}."];
    }

    /** @return array{ok:bool,message:string} */
    public static function setFreezes(User $target, int $freezes): array
    {
        $target->forceFill(['streak_freezes' => max(0, $freezes)])->save();
        $target->refresh();

        return ['ok' => true, 'message' => "Restores set to {$target->streak_freezes}."];
    }

    /** Buy a Streak Restore with keys — the real student S6 sink. */
    public static function buyFreeze(User $target): array
    {
        $result = RewardService::buyStreakFreeze($target);

        if (! $result['ok']) {
            return ['ok' => false, 'message' => $result['reason'] === 'cap'
                ? 'Already holding the cap.'
                : 'Not enough keys — grant some first.'];
        }

        return ['ok' => true, 'message' => "Bought a Streak Restore (15 keys). Held: {$target->refresh()->streak_freezes}."];
    }

    /**
     * Set today's farmable-action count. The decay ladder keys off the SUM
     * of quiz_submissions + drills_completed + game_rounds on the day row, so
     * the count is written into quiz_submissions as the controlling column
     * (scratch/dev-only convenience — the SUM is what decays rewards).
     *
     * @return array{ok:bool,message:string}
     */
    public static function setFarmableToday(User $target, int $count): array
    {
        $day = now()->startOfDay()->toDateString();

        $activity = UserDailyActivity::query()
            ->where('user_id', $target->id)
            ->whereDate('activity_date', $day)
            ->first();

        if ($activity === null) {
            $activity = UserDailyActivity::create([
                'user_id' => $target->id,
                'activity_date' => $day,
                'quiz_submissions' => 0,
            ]);
        }

        $activity->forceFill(['quiz_submissions' => max(0, $count)])->save();

        return ['ok' => true, 'message' => "Farmable count today set to {$count}."];
    }

    /**
     * Run one REAL quiz-settlement path against a published story. The next
     * scenario's B3 slot is whatever farmableActionsToday() then reports.
     *
     * @return array{ok:bool,message:string}
     */
    public static function quizScenario(User $target, string $kind): array
    {
        $story = Story::query()
            ->where('status', StoryStatus::Published)
            ->whereNotNull('difficulty_label')
            ->orderBy('id')
            ->first();

        if ($story === null) {
            return ['ok' => false, 'message' => 'Publish a story first — quiz scenarios need one to settle against.'];
        }

        [$scorePct, $previousBest] = match ($kind) {
            'poor' => [45.0, null],
            'improvement' => [80.0, 50.0],
            default => [100.0, null],
        };

        $earnings = RewardService::settleQuizAttempt(
            $target,
            $story->id,
            $story->difficulty_label,
            $scorePct,
            $previousBest,
            now(),
        );

        RewardService::stampDay($target, 'quiz', now());

        $label = match ($kind) {
            'poor' => 'poor first attempt',
            'improvement' => 'improvement retake',
            default => 'perfect first clear',
        };

        return [
            'ok' => true,
            'message' => "Quiz scenario ({$label} on \"{$story->title}\"): +{$earnings['keys']} keys, +{$earnings['xp']} points (×{$earnings['multiplier']} decay · {$earnings['reason']}).",
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function gameScenario(User $target, string $gameType, string $scoreTier): array
    {
        $score = match ($scoreTier) {
            'excellent' => 85,
            'good' => 60,
            'poor' => 30,
            default => 60,
        };

        $roundId = (string) Str::uuid();

        GameRound::create([
            'user_id' => $target->id,
            'game_type' => $gameType,
            'score' => $score,
            'words_count' => 10,
            'round_id' => $roundId,
        ]);

        $earnings = RewardService::settleGameRound($target, $gameType, $score, $roundId, 10, now());
        $stamped = $earnings['stamped'] ? 'day stamped · ' : '';

        return [
            'ok' => true,
            'message' => "Game round ({$gameType} · {$scoreTier}): +{$earnings['xp']} points · {$stamped}streak {$earnings['streak']}.",
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function drillScenario(User $target): array
    {
        $deck = WordGymDeck::query()->orderBy('id')->first();
        $earnings = RewardService::settleDrill($target, (int) ($deck->id ?? 0), true, now());
        RewardService::stampDay($target, 'drill', now());

        return [
            'ok' => true,
            'message' => "Drill scenario (first daily): +{$earnings['keys']} keys, +{$earnings['xp']} points.",
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function taskClaimScenario(User $target): array
    {
        $earnings = RewardService::creditTaskReward(
            $target,
            (int) config('gamification.task.min_keys', 5),
            (int) config('gamification.task.min_xp', 10),
            'daily_task',
        );

        return ['ok' => true, 'message' => "Task claim scenario: +{$earnings['keys']} keys, +{$earnings['points']} points."];
    }

    /** @return array{ok:bool,message:string} */
    public static function reset(User $target): array
    {
        $removed = ViewAs::resetTarget($target);

        return ['ok' => true, 'message' => "Reset — removed {$removed} scratch data row(s)."];
    }
}
