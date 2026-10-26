<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Support\NotificationService;
use Illuminate\Console\Command;

class NotifyAssignmentDue extends Command
{
    protected $signature = 'alice:assignment-reminders';

    protected $description = 'Send due-soon and overdue assignment reminders to students.';

    public function handle(): int
    {
        $now = now();

        // Due-soon: active, story-bearing, within the next 24 hours.
        $dueSoon = Assignment::query()
            ->where('status', 'active')
            ->whereNotNull('story_id')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$now, $now->copy()->addHours(24)])
            ->get();
        $dueSoonTotal = 0;
        foreach ($dueSoon as $assignment) {
            $dueSoonTotal += NotificationService::reminder($assignment, 'assignment.due_soon');
        }

        // Overdue: active, story-bearing, past due.
        $overdue = Assignment::query()
            ->where('status', 'active')
            ->whereNotNull('story_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->get();
        $overdueTotal = 0;
        foreach ($overdue as $assignment) {
            $overdueTotal += NotificationService::reminder($assignment, 'assignment.overdue');
        }

        $this->info("Due-soon: {$dueSoonTotal} · Overdue: {$overdueTotal} notifications dispatched.");

        return self::SUCCESS;
    }
}
