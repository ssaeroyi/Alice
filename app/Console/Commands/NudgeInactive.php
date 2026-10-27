<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NudgeService;
use App\Enums\Role;
use Illuminate\Console\Command;

/**
 * Daily auto nudge: a friendly reminder email to setup-complete students
 * who have gone at least three days without opening the app. Shares the
 * same cooldown rules as the teacher's manual button, so a student who
 * was nudged yesterday by their teacher stays undisturbed, and a still
 * inactive student hears from Alice at most once a week.
 */
class NudgeInactive extends Command
{
    protected $signature = 'alice:nudge-inactive';

    protected $description = 'Email a friendly reminder to students inactive for 3+ days (weekly repeat, shared cooldown)';

    public function handle(): int
    {
        // Auto nudges repeat weekly: skip anyone nudged inside 7 days.
        // The 48h manual guard lives in NudgeService and covers teachers.
        $candidates = User::where('role', Role::Student)
            ->realAccounts()
            ->whereNull('deleted_at')
            ->whereNull('suspended_at')
            ->where('is_setup_complete', true)
            ->where(function ($query) {
                $query->whereNull('last_seen_at')->whereNull('last_login_at')
                    ->orWhereRaw("GREATEST(COALESCE(last_seen_at, '1970-01-01'), COALESCE(last_login_at, '1970-01-01')) < ?", [now()->subDays(3)]);
            })
            ->where(function ($query) {
                $query->whereNull('last_nudged_at')
                    ->orWhere('last_nudged_at', '<', now()->subDays(7));
            })
            ->get();

        $sent = 0;

        foreach ($candidates as $student) {
            if ($student->nudgeKind() === null) {
                continue;
            }

            if (NudgeService::send($student, 'auto') === 'sent') {
                $sent++;
            }
        }

        $this->info("Auto nudges sent: {$sent} of {$candidates->count()} candidates.");

        return self::SUCCESS;
    }
}
