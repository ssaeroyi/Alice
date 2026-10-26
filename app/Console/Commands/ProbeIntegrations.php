<?php

namespace App\Console\Commands;

use App\Support\Integrations;
use Illuminate\Console\Command;

class ProbeIntegrations extends Command
{
    protected $signature = 'alice:probe-integrations';

    protected $description = 'Probe every enabled external service and record the outcome (run hourly via the scheduler)';

    public function handle(): int
    {
        $working = 0;
        $total = 0;

        foreach (array_keys(Integrations::definitions()) as $integration) {
            $result = Integrations::probe($integration);
            $total++;
            $working += (int) $result['ok'];

            $this->line(sprintf(
                '[%s] %s (%d ms) — %s',
                $result['ok'] ? 'ok' : 'fail',
                $integration,
                $result['latency_ms'],
                $result['message'],
            ));
        }

        if ($total === 0) {
            $this->warn('No enabled integrations to probe.');

            return self::SUCCESS;
        }

        $this->info("Probed {$total} integration(s): {$working} working.");

        return self::SUCCESS;
    }
}
