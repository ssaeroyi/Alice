<?php

namespace App\Console\Commands;

use App\Services\AccountDeletionService;
use Illuminate\Console\Command;

class PurgeDeletedAccounts extends Command
{
    protected $signature = 'alice:accounts:purge-deleted';

    protected $description = 'Hard-delete student accounts whose deletion grace window has lapsed.';

    public function handle(AccountDeletionService $deletion): int
    {
        $purged = $deletion->purgeExpired();

        $this->info("Purged {$purged} expired account(s).");

        return self::SUCCESS;
    }
}
