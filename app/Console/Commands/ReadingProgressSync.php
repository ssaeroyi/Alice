<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\ReadingProgressService;
use Illuminate\Console\Command;

class ReadingProgressSync extends Command
{
    protected $signature = 'alice:reading-progress';

    protected $description = 'Refresh every student\'s live reading-progress estimate (run nightly via the scheduler)';

    public function handle(): int
    {
        $students = User::query()
            ->realAccounts()
            ->where('role', Role::Student)
            ->where('is_setup_complete', true)
            ->with('readingProgress')
            ->get();

        $count = 0;

        foreach ($students as $student) {
            ReadingProgressService::recompute($student);
            $count++;
        }

        $this->info("Refreshed reading progress for {$count} student(s).");

        return self::SUCCESS;
    }
}
