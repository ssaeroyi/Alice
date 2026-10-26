<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Streak Restore prompt state (replaces the silent auto-freeze).
 *
 * When a student's last stamp is exactly two days old they are inside the
 * 1-day grace window after a single missed day: the next page load prompts
 * them to spend a held Streak Restore. Missing the window (3+ days) shows
 * the one-time "grace period missed" notice instead. Answers are recorded
 * in the user's `preferences` JSON (no migration) so the prompt fires once
 * per broken streak and `RewardService::stampDay()` can honour an explicit
 * "No" instead of silently consuming a restore.
 */
class StreakRestore
{
    /**
     * What (if anything) the student should be prompted with on this load.
     *
     * @return array{mode: 'restore'|'unavailable'|'missed', holds: int}|null
     */
    public static function state(User $user): ?array
    {
        if ($user->role !== Role::Student || ViewAs::isActive()) {
            return null;
        }

        $last = $user->last_stamped_date?->copy()->startOfDay();

        if ($last === null || $user->current_streak < 1) {
            return null;
        }

        $today = Carbon::now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $graceDay = $today->copy()->subDays(2);

        // Gap of 0 or 1 day: the streak is still alive (or the student has
        // not stamped yet today) — nothing to restore.
        if (! $last->lt($yesterday)) {
            return null;
        }

        $anchor = $last->toDateString();
        $prefs = $user->preferences ?? [];

        if ($last->isSameDay($graceDay)) {
            // Grace window: prompt only while this break is unanswered.
            $answer = $prefs['streak_restore'] ?? null;

            if (is_array($answer) && ($answer['for'] ?? null) === $anchor) {
                return null;
            }

            return [
                'mode' => $user->streak_freezes > 0 ? 'restore' : 'unavailable',
                'holds' => (int) $user->streak_freezes,
            ];
        }

        // 3+ days: the grace period is gone. Show the sad notice once per
        // broken streak (marked here so an Escape does not re-nag forever).
        $seen = $prefs['streak_restore_missed'] ?? null;

        if (is_array($seen) && ($seen['for'] ?? null) === $anchor) {
            return null;
        }

        return ['mode' => 'missed', 'holds' => (int) $user->streak_freezes];
    }

    /**
     * Record the student's answer for the current break so the prompt never
     * fires again for it and stampDay() can act on the choice.
     */
    public static function recordAnswer(User $user, string $answer): void
    {
        $prefs = $user->preferences ?? [];
        $prefs['streak_restore'] = [
            'for' => $user->last_stamped_date?->copy()->startOfDay()->toDateString(),
            'answer' => $answer,
            'at' => Carbon::now()->toDateTimeString(),
        ];

        $user->preferences = $prefs;
        $user->save();
    }

    /**
     * Mark the "grace period missed" notice as shown for the current break.
     */
    public static function markMissedSeen(User $user): void
    {
        $prefs = $user->preferences ?? [];
        $prefs['streak_restore_missed'] = [
            'for' => $user->last_stamped_date?->copy()->startOfDay()->toDateString(),
            'at' => Carbon::now()->toDateTimeString(),
        ];

        $user->preferences = $prefs;
        $user->save();
    }
}
