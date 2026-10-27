<?php

namespace App\Services;

use App\Mail\NudgeInactive;
use App\Mail\NudgeSetupPending;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a single friendly "come back" email to one student and records it.
 *
 * Rules shared by the teacher's manual button and the scheduled auto nudge:
 * never twice within the cooldown window, never to suspended accounts,
 * never to accounts that real production should not touch. The nudge kind
 * is picked by the student's own state (setup vs inactive), not by who
 * asked for it.
 */
class NudgeService
{
    /** The cooldown between any two nudges to the same student. */
    public const COOLDOWN_HOURS = 24;

    /**
     * Try to nudge. Returns 'sent', 'cooldown', 'recently_active' or 'blocked'.
     */
    public static function send(User $student, string $source): string
    {
        if ($student->suspended_at !== null || $student->deleted_at !== null) {
            return 'blocked';
        }

        $kind = $student->nudgeKind();

        if ($kind === null) {
            return 'recently_active';
        }

        if ($student->last_nudged_at !== null && $student->last_nudged_at->gt(now()->subHours(self::COOLDOWN_HOURS))) {
            if ($source === 'teacher') {
                Audit::log('nudge.skipped_cooldown', $student, ['kind' => $kind]);
            }

            return 'cooldown';
        }

        // Force rerender of the fresh state before stamping below.
        $kind === 'setup'
            ? Mail::to($student->email)->send(new NudgeSetupPending($student))
            : Mail::to($student->email)->send(new NudgeInactive($student));

        $student->forceFill([
            'last_nudged_at' => now(),
            'last_nudge_kind' => $kind,
        ])->save();

        $source === 'teacher'
            ? Audit::log('nudge.sent_teacher', $student, ['kind' => $kind])
            : Audit::log('nudge.sent_auto', $student, ['kind' => $kind]);

        return 'sent';
    }
}
