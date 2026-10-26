<?php

namespace App\Services;

use App\Models\KeyTransaction;
use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for earning/spending keys, points (the progression
 * foundation, S1), streak stamps, freezes and daily-activity accounting
 * (SYSTEM_DESIGN §11.3 mandate: all controllers call this; no ad-hoc key or
 * points math anywhere else).
 *
 * Economy rules implemented here live in config/gamification.php and are
 * specified in STUDENT_PAGES_PLAN.md §2:
 *  - B1 first-clear economy (retakes pay an XP delta share, never keys)
 *  - B2 read-before-quiz gating is enforced by the quiz controller, not here
 *  - B3 same-day diminishing curve over farmable actions
 *  - B4 self-reported activities never pay (flashcards never call this class)
 *
 * Point-crediting settles (quiz, story-clear, drill, game, daily task) also
 * trigger the one-time title-promotion ceremony via TitleRewardService, which
 * awards the crossed-tier badges + escalating keys inside the same locked
 * transaction. The reading heartbeat (awardReadingPoints) intentionally does
 * not — reading promotions are surfaced once per session on the summary page.
 */
class RewardService
{
    /**
     * Accuracy tier multiplier for a quiz score percentage.
     */
    public static function accuracyTier(float $scorePct): float
    {
        $tiers = config('gamification.accuracy_tiers');

        return match (true) {
            $scorePct >= 100 => (float) $tiers['perfect'],
            $scorePct >= config('gamification.pass_threshold_pct') => (float) $tiers['under_100'],
            $scorePct >= 50 => (float) $tiers['under_70'],
            default => (float) $tiers['under_50'],
        };
    }

    /**
     * Same-day diminishing multiplier (B3). $farmableSoFar is the number of
     * farmable actions already performed today BEFORE the current one.
     */
    public static function sameDayMultiplier(int $farmableSoFar): float
    {
        $ladder = config('gamification.same_day_multipliers');

        return (float) ($ladder[$farmableSoFar] ?? config('gamification.same_day_floor'));
    }

    /**
     * First-clear base rewards for a difficulty band label.
     *
     * @return array{keys: int, xp: int}
     */
    public static function bandRewards(?string $difficultyLabel): array
    {
        $bands = config('gamification.bands');
        $band = $bands[$difficultyLabel] ?? $bands[config('gamification.fallback_band')];

        return ['keys' => (int) $band['keys'], 'xp' => (int) $band['xp']];
    }

    /**
     * Pure economy math for settling one story-quiz attempt.
     *
     * Reasons:
     *  - quiz_first_clear   first attempt that reaches the pass threshold
     *                       (also the only path that pays KEYS)
     *  - quiz_first_attempt first attempt below the threshold: partial XP only
     *  - quiz_improvement   retake beating the stored best: small XP delta only
     *  - quiz_no_reward     retake without improvement
     *
     * @return array{keys: int, xp: int, multiplier: float, reason: string}
     */
    public static function quizEarnings(?string $difficultyLabel, float $scorePct, ?float $previousBestPct, int $farmableSoFar): array
    {
        $base = self::bandRewards($difficultyLabel);
        $multiplier = self::sameDayMultiplier($farmableSoFar);
        $pass = (float) config('gamification.pass_threshold_pct');

        $isFirstAttempt = $previousBestPct === null;

        if ($isFirstAttempt && $scorePct >= $pass) {
            return [
                'keys' => (int) round($base['keys'] * self::accuracyTier($scorePct) * $multiplier),
                'xp' => (int) round($base['xp'] * self::accuracyTier($scorePct) * $multiplier),
                'multiplier' => $multiplier,
                'reason' => 'quiz_first_clear',
            ];
        }

        if ($isFirstAttempt) {
            return [
                'keys' => 0,
                'xp' => (int) round($base['xp'] * self::accuracyTier($scorePct) * $multiplier),
                'multiplier' => $multiplier,
                'reason' => 'quiz_first_attempt',
            ];
        }

        if ($scorePct > $previousBestPct) {
            $deltaXp = ($base['xp'] * ($scorePct - $previousBestPct) / 100.0)
                * (float) config('gamification.retake_delta_share') * $multiplier;

            return [
                'keys' => 0,
                'xp' => (int) round($deltaXp),
                'multiplier' => $multiplier,
                'reason' => 'quiz_improvement',
            ];
        }

        return ['keys' => 0, 'xp' => 0, 'multiplier' => $multiplier, 'reason' => 'quiz_no_reward'];
    }

    /**
     * Pure economy math for one scored gym drill (B4: scored drills only —
     * flashcard self-grading must never reach this method).
     */
    public static function drillEarnings(bool $firstDailyForDeck, int $farmableSoFar): array
    {
        if (! $firstDailyForDeck) {
            return ['keys' => 0, 'xp' => 0, 'multiplier' => self::sameDayMultiplier($farmableSoFar)];
        }

        $multiplier = self::sameDayMultiplier($farmableSoFar);

        return [
            'keys' => (int) round(config('gamification.drill.first_daily_keys') * $multiplier),
            'xp' => (int) round(config('gamification.drill.first_daily_xp') * $multiplier),
            'multiplier' => $multiplier,
        ];
    }

    /**
     * Pure economy math for a scored mini-game round.
     *
     * Games award POINTS only (S1): keys are reserved for quiz first-clears
     * and daily tasks, so game keys are always 0 and only the "xp" amount
     * (which is deposited into the points column) is paid.
     */
    public static function gameEarnings(int $score, int $farmableSoFar): array
    {
        $multiplier = self::sameDayMultiplier($farmableSoFar);
        $cfg = config('gamification.game');

        if ($score >= $cfg['excellent_threshold']) {
            return [
                'keys' => (int) round($cfg['excellent_keys'] * $multiplier),
                'xp' => (int) round($cfg['excellent_xp'] * $multiplier),
                'multiplier' => $multiplier,
            ];
        }

        if ($score >= $cfg['good_threshold']) {
            return [
                'keys' => (int) round($cfg['good_keys'] * $multiplier),
                'xp' => (int) round($cfg['good_xp'] * $multiplier),
                'multiplier' => $multiplier,
            ];
        }

        return [
            'keys' => (int) round($cfg['poor_keys'] * $multiplier),
            'xp' => (int) round($cfg['poor_xp'] * $multiplier),
            'multiplier' => $multiplier,
        ];
    }

    /**
     * Apply a scored mini-game round: credits keys/XP and writes the audit
     * ledger. Idempotent via round_id unique constraint.
     *
     * @return array{keys: int, xp: int, multiplier: float, stamped: bool, streak: int, promotion: array<string, mixed>}
     */
    public static function settleGameRound(User $user, string $gameType, int $score, string $roundId, int $wordsCount, Carbon $now): array
    {
        $earnings = self::gameEarnings($score, self::farmableActionsToday($user, $now));

        $stamp = self::stampDay($user, 'game', $now);

        if ($earnings['keys'] === 0 && $earnings['xp'] === 0) {
            return $earnings + ['stamped' => $stamp['stamped'], 'streak' => $stamp['streak']];
        }

        return DB::transaction(function () use ($user, $earnings, $stamp, $now) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $changes = ['points' => $fresh->points + $earnings['xp']];

            if ($earnings['keys'] > 0) {
                $changes['total_keys'] = $fresh->total_keys + $earnings['keys'];
            }

            $fresh->fill($changes)->save();

            if ($earnings['keys'] > 0) {
                self::recordTransaction($fresh, $earnings['keys'], 'game_round', 'game_round', null);
            }

            $user->setRawAttributes($fresh->getAttributes());

            $promotion = TitleRewardService::congratulate($user, $now);

            return $earnings + ['stamped' => $stamp['stamped'], 'streak' => $stamp['streak'], 'promotion' => $promotion];
        });
    }

    /**
     * Story-clear economy (C2, user-approved): reading a story to the end
     * for the first time pays a flat keys sum plus a difficulty-scaled XP sum
     * ONCE per story ever. The ledger row (reason=story_clear, subject=story)
     * is the idempotency anchor, so replaying the finish heartbeat or the
     * finish button can never double-pay. A finish-URL skip that banked zero
     * real reading seconds pays nothing; the once-per-story rule and the
     * 3-per-day cap bound how much an instant end-to-end scroll can farm.
     *
     * @return array{keys: int, xp: int, paid: bool, promotion: array<string, mixed>}
     */
    public static function settleStoryClear(User $user, int $storyId, int $timeSpentSeconds, Carbon $now, ?string $difficultyLabel = null): array
    {
        $cfg = config('gamification.story_clear', ['keys' => 3, 'xp' => 5]);

        // Any real reading time qualifies (the old 10s session gate was
        // removed 2026-09-06). A zero-second finish-URL skip — no heartbeat,
        // no scroll evidence — stays unpaid so the finish URL can't farm.
        if ($timeSpentSeconds <= 0) {
            return ['keys' => 0, 'xp' => 0, 'paid' => false];
        }

        return DB::transaction(function () use ($user, $storyId, $cfg, $now, $difficultyLabel) {
            // Lock the user row FIRST so two racing settles for the same (or
            // consecutive) stories serialize: the second caller re-checks the
            // ledger inside the same lock and sees the first payment, so a
            // clear can never be banked twice or over a daily cap.
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($fresh === null) {
                return ['keys' => 0, 'xp' => 0, 'paid' => false];
            }

            $alreadyPaid = KeyTransaction::query()
                ->where('user_id', $user->id)
                ->where('reason', 'story_clear')
                ->where('subject_type', 'story')
                ->where('subject_id', $storyId)
                ->exists();

            if ($alreadyPaid) {
                return ['keys' => 0, 'xp' => 0, 'paid' => false];
            }

            $maxPerDay = (int) ($cfg['max_per_day'] ?? 0);
            if ($maxPerDay > 0) {
                $paidToday = KeyTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('reason', 'story_clear')
                    ->whereDate('created_at', $now->toDateString())
                    ->count();

                if ($paidToday >= $maxPerDay) {
                    return ['keys' => 0, 'xp' => 0, 'paid' => false];
                }
            }

            $keys = (int) $cfg['keys'];
            $xpLabels = (array) ($cfg['xp_by_band'] ?? []);
            $xp = (int) ($xpLabels[$difficultyLabel ?? ''] ?? (int) $cfg['xp']);

            $fresh->increment('points', $xp);
            $fresh->increment('total_keys', $keys);
            $fresh->refresh();

            self::recordTransaction($fresh, $keys, 'story_clear', 'story', $storyId);

            $user->setRawAttributes($fresh->getAttributes());

            $promotion = TitleRewardService::congratulate($user, $now);

            return ['keys' => $keys, 'xp' => $xp, 'paid' => true, 'promotion' => $promotion];
        });
    }

    /**
     * Number of farmable actions already recorded for the user today —
     * the B3 decay input for the next settlement.
     */
    public static function farmableActionsToday(User $user, Carbon $now): int
    {
        $activity = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->whereDate('activity_date', $now->toDateString())
            ->first();

        if ($activity === null) {
            return 0;
        }

        return $activity->quiz_submissions + $activity->drills_completed + $activity->game_rounds;
    }

    /**
     * Points banked for a given cumulative reading-seconds total today.
     *
     * Full minutes count toward points at reading_per_minute each (a fraction,
     * so floor(minutes x rate) keeps whole-point steps), hard capped at
     * reading_daily_cap points a day. It is a pure function of the stored
     * reading_seconds_today, so replays / re-accumulated heartbeats can never
     * double-bill (the delta vs. the previous beat is what pays).
     */
    public static function readingPointsFor(int $totalSecondsToday): int
    {
        $rate = (float) config('gamification.points.reading_per_minute', 1);
        $cap = (int) config('gamification.points.reading_daily_cap', 60);
        $minutes = (int) floor($totalSecondsToday / 60);

        return min($cap, (int) floor($minutes * $rate));
    }

    /**
     * Credit the reading-points delta for a beat, given the total seconds
     * accumulated today up to AND including this beat. The award is always
     * (points-for-new-total) minus (points-for-prior-total), i.e. a pure
     * function of the same stored reading_seconds_today that drives the
     * reading-goal tracker — so points and reading time stay perfectly in
     * step, and the award can never exceed the daily cap.
     */
    public static function awardReadingPoints(User $user, int $newTotalSecondsToday, int $previousTotalSecondsToday): int
    {
        $new = self::readingPointsFor($newTotalSecondsToday);
        $delta = $new - self::readingPointsFor($previousTotalSecondsToday);

        if ($delta <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $delta) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $fresh->increment('points', $delta);
            $user->setRawAttributes($fresh->getAttributes());

            return $delta;
        });
    }

    /**
     * Apply a settled quiz attempt: credits keys/XP and writes the audit
     * ledger for any key movement. Callers persist the TestAttempt itself.
     *
     * @return array{keys: int, xp: int, multiplier: float, reason: string, story_id: int, promotion: array<string, mixed>}
     */
    public static function settleQuizAttempt(User $user, int $storyId, ?string $difficultyLabel, float $scorePct, ?float $previousBestPct, Carbon $now): array
    {
        $earnings = self::quizEarnings($difficultyLabel, $scorePct, $previousBestPct, self::farmableActionsToday($user, $now));

        if ($earnings['keys'] === 0 && $earnings['xp'] === 0) {
            return $earnings + ['story_id' => $storyId];
        }

        return DB::transaction(function () use ($user, $storyId, $earnings, $now) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $changes = ['points' => $fresh->points + $earnings['xp']];

            if ($earnings['keys'] > 0) {
                $changes['total_keys'] = $fresh->total_keys + $earnings['keys'];
            }

            $fresh->fill($changes)->save();

            if ($earnings['keys'] > 0) {
                self::recordTransaction($fresh, $earnings['keys'], $earnings['reason'], 'story', $storyId);
            }

            $user->setRawAttributes($fresh->getAttributes());

            $promotion = TitleRewardService::congratulate($user, $now);

            return $earnings + ['story_id' => $storyId, 'promotion' => $promotion];
        });
    }

    /**
     * Apply a scored gym drill settlement. $firstDailyForDeck is determined
     * by the drill controller from its session records (slice F owns drill
     * event persistence); B4 forbids paying repeat drills.
     *
     * @return array{keys: int, xp: int, multiplier: float, deck_id: int, promotion: array<string, mixed>}
     */
    public static function settleDrill(User $user, int $deckId, bool $firstDailyForDeck, Carbon $now): array
    {
        $earnings = self::drillEarnings($firstDailyForDeck, self::farmableActionsToday($user, $now));

        if ($earnings['keys'] === 0 && $earnings['xp'] === 0) {
            return $earnings + ['deck_id' => $deckId];
        }

        return DB::transaction(function () use ($user, $deckId, $earnings, $now) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $changes = ['points' => $fresh->points + $earnings['xp']];

            if ($earnings['keys'] > 0) {
                $changes['total_keys'] = $fresh->total_keys + $earnings['keys'];
            }

            $fresh->fill($changes)->save();

            if ($earnings['keys'] > 0) {
                self::recordTransaction($fresh, $earnings['keys'], 'drill_first_daily', 'word_gym_deck', $deckId);
            }

            $user->setRawAttributes($fresh->getAttributes());

            $promotion = TitleRewardService::congratulate($user, $now);

            return $earnings + ['deck_id' => $deckId, 'promotion' => $promotion];
        });
    }

    /**
     * Spend keys on a sink. Returns false (and changes nothing) when the
     * balance is insufficient.
     */
    public static function spend(User $user, int $amount, string $reason, ?string $subjectType = null, ?int $subjectId = null, ?array $metadata = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $amount, $reason, $subjectType, $subjectId, $metadata) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($fresh->total_keys < $amount) {
                return false;
            }

            $fresh->decrement('total_keys', $amount);
            $fresh->refresh();

            self::recordTransaction($fresh, -$amount, $reason, $subjectType, $subjectId, $metadata);

            $user->setRawAttributes($fresh->getAttributes());

            return true;
        });
    }

    /**
     * Grant keys with no natural earning path (admin Dev Tools, future
     * giveaways). Ledger-backed exactly like every other key movement, so
     * total_keys and the audit trail stay in lock-step.
     */
    public static function grant(User $user, int $amount, string $reason, ?string $subjectType = null, ?int $subjectId = null, ?array $metadata = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $amount, $reason, $subjectType, $subjectId, $metadata) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            $fresh->increment('total_keys', $amount);
            $fresh->refresh();

            self::recordTransaction($fresh, $amount, $reason, $subjectType, $subjectId, $metadata);

            $user->setRawAttributes($fresh->getAttributes());

            return true;
        });
    }

    /**
     * Buy a Streak Freeze with keys (S6 sink). Honours the same hard cap as
     * the automatic monthly grant (config gamification.freeze.hold_cap) so
     * freezes can never be hoarded past the ceiling; keys are only spent when
     * the purchase is legal.
     *
     * @return array{ok: bool, reason: 'cap'|'keys'|null}
     */
    public static function buyStreakFreeze(User $user): array
    {
        $cost = (int) config('gamification.sinks.streak_freeze');
        $cap = (int) config('gamification.freeze.hold_cap');

        return DB::transaction(function () use ($user, $cost, $cap): array {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($fresh->streak_freezes >= $cap) {
                return ['ok' => false, 'reason' => 'cap'];
            }

            if ($fresh->total_keys < $cost) {
                return ['ok' => false, 'reason' => 'keys'];
            }

            $fresh->decrement('total_keys', $cost);
            $fresh->increment('streak_freezes');
            $fresh->increment('streak_freezes_bought');
            $fresh->refresh();

            self::recordTransaction($fresh, -$cost, 'streak_freeze');

            $user->setRawAttributes($fresh->getAttributes());

            return ['ok' => true, 'reason' => null];
        });
    }

    /**
     * Spend one held Streak Restore inside the 1-day grace window (last
     * stamp exactly two days ago): the missed day rejoins the streak chain,
     * so today's next stamp continues it instead of resetting. Refused
     * outside the window, with no restores held, or when this break was
     * already restored.
     *
     * @return array{ok: bool, message: string, streak: int, holds: int}
     */
    public static function useStreakRestore(User $user, Carbon $now): array
    {
        return DB::transaction(function () use ($user, $now): array {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $today = $now->copy()->startOfDay();
            $last = $fresh->last_stamped_date?->copy()->startOfDay();

            if ($last === null || $fresh->current_streak < 1) {
                return ['ok' => false, 'message' => 'There is no streak to restore.', 'streak' => (int) $fresh->current_streak, 'holds' => (int) $fresh->streak_freezes];
            }

            if (! $last->isSameDay($today->copy()->subDays(2))) {
                return ['ok' => false, 'message' => 'The grace period to restore your streak has passed.', 'streak' => (int) $fresh->current_streak, 'holds' => (int) $fresh->streak_freezes];
            }

            if ($fresh->streak_freezes < 1) {
                return ['ok' => false, 'message' => 'You have 0 Streak Restores left.', 'streak' => (int) $fresh->current_streak, 'holds' => 0];
            }

            $prefs = $fresh->preferences ?? [];
            $answer = $prefs['streak_restore'] ?? null;

            if (is_array($answer) && ($answer['for'] ?? null) === $last->toDateString() && ($answer['answer'] ?? null) === 'accepted') {
                return ['ok' => false, 'message' => 'Your streak was already restored.', 'streak' => (int) $fresh->current_streak, 'holds' => (int) $fresh->streak_freezes];
            }

            $fresh->streak_freezes--;
            $fresh->streak_freezes_used++;
            // Yesterday (the missed day) rejoins the chain; the next stamp is
            // then a normal consecutive day.
            $fresh->last_stamped_date = $today->copy()->subDay()->toDateString();

            $prefs['streak_restore'] = [
                'for' => $last->toDateString(),
                'answer' => 'accepted',
                'at' => $now->toDateTimeString(),
            ];
            $fresh->preferences = $prefs;
            $fresh->save();

            $user->setRawAttributes($fresh->getAttributes());

            return ['ok' => true, 'message' => 'Streak restored.', 'streak' => (int) $fresh->current_streak, 'holds' => (int) $fresh->streak_freezes];
        });
    }

    /**
     * Was the restore prompt for this break left unanswered (or answered
     * "no restores held", which still allows the safety net once a restore
     * is bought)? Only an explicit "No" disables the automatic shield.
     */
    private static function restoreUndecided(User $user, Carbon $lastStamped): bool
    {
        $answer = $user->preferences['streak_restore'] ?? null;

        if (! is_array($answer) || ($answer['for'] ?? null) !== $lastStamped->toDateString()) {
            return true;
        }

        return ($answer['answer'] ?? null) === 'unavailable';
    }

    /**
     * Credit a daily-task claim (S7): atomically adds keys + points and writes
     * one KeyTransaction ledger row for the keys (points are not a spent
     * currency, so they carry no ledger row — matching quiz/drill credits).
     *
     * @return array{keys: int, points: int, promotion: array<string, mixed>}
     */
    public static function creditTaskReward(User $user, int $keys, int $points, string $reason, ?string $subjectType = null, ?int $subjectId = null): array
    {
        if ($keys <= 0 && $points <= 0) {
            return ['keys' => 0, 'points' => 0];
        }

        return DB::transaction(function () use ($user, $keys, $points, $reason, $subjectType, $subjectId): array {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

            $changes = ['points' => $fresh->points + $points];

            if ($keys > 0) {
                $changes['total_keys'] = $fresh->total_keys + $keys;
            }

            $fresh->fill($changes)->save();

            if ($keys > 0) {
                self::recordTransaction($fresh, $keys, $reason, $subjectType, $subjectId);
            }

            $user->setRawAttributes($fresh->getAttributes());

            $promotion = TitleRewardService::congratulate($user, Carbon::now());

            return ['keys' => $keys, 'points' => $points, 'promotion' => $promotion];
        });
    }

    /**
     * Credit the keys for a daily goal the student has claimed (the hub's
     * CLAIM button) — the reading goal or the study-word goal. Payout is gated
     * to once per day by a timestamp on the (user, activity_date) row, so a
     * racing double-tap can never double-pay. Returns the keys credited (0 if
     * the goal was already claimed today).
     */
    public static function creditGoalReward(User $user, string $goal, Carbon $now): int
    {
        if (! in_array($goal, ['reading', 'study'], true)) {
            throw new \InvalidArgumentException("Unknown daily goal [{$goal}].");
        }

        $keys = (int) config("gamification.goal.{$goal}_keys", 5);

        if ($keys <= 0) {
            return 0;
        }

        $column = $goal === 'reading' ? 'reading_goal_claimed_at' : 'study_goal_claimed_at';

        return DB::transaction(function () use ($user, $goal, $keys, $column, $now): int {
            $today = $now->copy()->startOfDay();

            $activity = UserDailyActivity::query()
                ->where('user_id', $user->id)
                ->whereDate('activity_date', $today->toDateString())
                ->lockForUpdate()
                ->first();

            if ($activity === null) {
                $activity = UserDailyActivity::create([
                    'user_id' => $user->id,
                    'activity_date' => $today->toDateString(),
                ]);
            }

            if ($activity->{$column} !== null) {
                return 0;
            }

            $activity->{$column} = $now;
            $activity->save();

            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $fresh->increment('total_keys', $keys);
            self::recordTransaction($fresh, $keys, "{$goal}_goal", 'daily_goal', null);

            $user->setRawAttributes($fresh->getAttributes());

            return $keys;
        });
    }

    /**
     * Record one major action for the day, stamping the Brain Age calendar
     * cell on the day's first major action and advancing/resetting the
     * streak. A single missed day no longer consumes a restore on its own:
     * the Streak Restore prompt asks first, and an explicit "No" resets the
     * streak here. An unanswered prompt (dismissed without choosing) keeps
     * the old automatic shield as a safety net.
     *
     * $action: read | quiz | drill | game
     *
     * @return array{stamped: bool, streak: int, freeze_used: bool, freeze_granted: bool}
     */
    public static function stampDay(User $user, string $action, Carbon $now): array
    {
        $column = match ($action) {
            'read' => 'read_sessions',
            'quiz' => 'quiz_submissions',
            'drill' => 'drills_completed',
            'game' => 'game_rounds',
            default => throw new \InvalidArgumentException("Unknown major action [{$action}]."),
        };

        return DB::transaction(function () use ($user, $column, $now) {
            $today = $now->copy()->startOfDay();

            $activity = UserDailyActivity::query()
                ->where('user_id', $user->id)
                ->whereDate('activity_date', $today->toDateString())
                ->lockForUpdate()
                ->first();

            if ($activity === null) {
                $activity = new UserDailyActivity([
                    'user_id' => $user->id,
                    'activity_date' => $today->toDateString(),
                ]);
            }

            $activity->{$column}++;
            $activity->save();

            $result = ['stamped' => false, 'streak' => $user->current_streak, 'freeze_used' => false, 'freeze_granted' => false];

            if ($activity->stamped_at !== null) {
                return $result;
            }

            // Day's first major action — slam the stamp.
            $activity->stamped_at = $now;
            $activity->save();

            $result['stamped'] = true;

            $lastStamped = $user->last_stamped_date?->copy()->startOfDay();

            if ($lastStamped === null || ! $lastStamped->isSameDay($today)) {
                if ($lastStamped !== null && $lastStamped->isSameDay($today->copy()->subDay())) {
                    $user->current_streak++;
                } elseif (
                    $lastStamped !== null
                    && $lastStamped->isSameDay($today->copy()->subDays(2))
                    && $user->streak_freezes > 0
                    && self::restoreUndecided($user, $lastStamped)
                ) {
                    $user->streak_freezes--;
                    $user->streak_freezes_used++;
                    $user->current_streak++;
                    $result['freeze_used'] = true;
                } else {
                    $user->current_streak = 1;
                }

                $user->longest_streak = max($user->longest_streak, $user->current_streak);
                $user->last_stamped_date = $today->toDateString();
                $user->save();

                $result['streak'] = $user->current_streak;
            } else {
                $result['streak'] = $user->current_streak;
            }

            // Monthly freeze grant: only on the first stamped day of the
            // calendar month, and only while holding fewer than the cap.
            $monthHasStamps = UserDailyActivity::query()
                ->where('user_id', $user->id)
                ->whereNotNull('stamped_at')
                ->whereYear('stamped_at', $now->year)
                ->whereMonth('stamped_at', $now->month)
                ->where('activity_date', '!=', $today->toDateString())
                ->exists();

            if (! $monthHasStamps && $user->streak_freezes < config('gamification.freeze.hold_cap')) {
                $user->streak_freezes = min(
                    config('gamification.freeze.hold_cap'),
                    $user->streak_freezes + config('gamification.freeze.monthly_grant'),
                );
                $user->save();
                $result['freeze_granted'] = true;
            }

            return $result;
        });
    }

    /**
     * Append one auditable ledger row. MUST be called after the user's
     * balance already reflects the change.
     */
    public static function recordTransaction(User $user, int $amount, string $reason, ?string $subjectType = null, ?int $subjectId = null, ?array $metadata = null): KeyTransaction
    {
        return KeyTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'reason' => $reason,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'balance_after' => $user->total_keys,
            'metadata' => $metadata,
        ]);
    }
}
