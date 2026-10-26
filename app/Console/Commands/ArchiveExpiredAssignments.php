<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use Illuminate\Console\Command;

class ArchiveExpiredAssignments extends Command
{
    protected $signature = 'alice:archive-expired-assignments';

    protected $description = 'Move active assignments whose due date has passed into the Finished bucket.';

    public function handle(): int
    {
        $expired = Assignment::query()
            ->where('status', 'active')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        $archived = 0;
        foreach ($expired as $assignment) {
            $assignment->archive('auto_expired');
            $archived++;
        }

        $this->info("Archived {$archived} expired assignment(s).");

        return self::SUCCESS;
    }
}
