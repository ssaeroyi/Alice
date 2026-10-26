<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RollupDailyStats extends Command
{
    protected $signature = 'alice:stats:daily';

    protected $description = 'Roll the request-log traffic for every missed day into durable daily_stats rows (run nightly)';

    public function handle(): int
    {
        $firstLog = DB::table('request_logs')->min('created_at');

        if ($firstLog === null) {
            $this->info('No request logs to roll up yet.');

            return self::SUCCESS;
        }

        $lastRolled = DB::table('daily_stats')->where('metric', 'traffic.requests')->max('day');

        $cursor = $lastRolled !== null
            ? Carbon::parse($lastRolled)->addDay()->startOfDay()
            : Carbon::parse($firstLog)->startOfDay();

        $yesterdayEnd = now()->startOfDay();

        if ($cursor->gte($yesterdayEnd)) {
            $this->info('Daily stats are up to date.');

            return self::SUCCESS;
        }

        $rows = DB::table('request_logs')
            ->where('created_at', '>=', $cursor)
            ->where('created_at', '<', $yesterdayEnd)
            ->selectRaw(
                'DATE(created_at) AS day, '
                .'COUNT(*) AS requests, '
                .'SUM(CASE WHEN status BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS four_xx, '
                .'SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END) AS five_xx, '
                .'AVG(duration_ms) AS avg_ms'
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $written = 0;

        foreach ($rows as $row) {
            $metrics = [
                'traffic.requests' => (int) $row->requests,
                'traffic.four_xx' => (int) $row->four_xx,
                'traffic.five_xx' => (int) $row->five_xx,
                'traffic.avg_ms' => $row->avg_ms !== null ? (int) round((float) $row->avg_ms) : 0,
            ];

            foreach ($metrics as $metric => $value) {
                DB::table('daily_stats')->updateOrInsert(
                    ['day' => $row->day, 'metric' => $metric],
                    ['value' => $value, 'updated_at' => now()],
                );

                $written++;
            }
        }

        $range = $rows->count() > 0
            ? $rows->first()->day.' → '.$rows->last()->day.' ('.count($rows).' days)'
            : 'no new days';

        $this->info("Rolled up {$written} stat rows for {$range}.");

        return self::SUCCESS;
    }
}
