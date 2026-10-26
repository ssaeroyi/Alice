<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The platform-health metrics behind the admin dashboard's metric explorer.
 *
 * One whitelist maps a metric key to its label, hint, unit and the builder
 * that produces a daily {date, count} series for a DashboardRange. The tiles
 * are links (JS-off still selects a metric) and the JSON endpoint reads the
 * same map, so the tile list, the chart copy and the queries can never drift
 * apart.
 *
 * Every builder is defensive: a source that fails or has no rows yet
 * degrades to an empty series instead of breaking the page, matching
 * SystemStatus::snapshot(). Account metrics exclude the disposable admin
 * preview accounts.
 */
class OpsMetrics
{
    /**
     * Metric builders. `kind` decides the tile headline and the bucketing:
     * sum  — counts that add up across the window (requests, errors, sign-ups)
     * avg  — a daily average where empty days carry no data (response time)
     *
     * @var array<string, callable>
     */
    private const BUILDERS = [
        'traffic.requests' => 'traffic',
        'traffic.avg_ms' => 'traffic',
        'traffic.four_xx' => 'traffic',
        'traffic.five_xx' => 'traffic',
        'client.errors' => 'clientErrors',
        'auth.failed_logins' => 'failedLogins',
        'accounts.new' => 'newAccounts',
    ];

    /**
     * Ordered tile definitions, in the order the explorer shows them.
     * `detail_target`/`detail_label` drive the actionable link under the
     * chart: traffic lands on the Status traffic tab, accounts on
     * the accounts list.
     *
     * @return array<string, array{label: string, unit: string, hint: string, icon: string, kind: string, precision: int, detail_target: string, detail_label: string}>
     */
    public static function definitions(): array
    {
        return [
            'traffic.requests' => [
                'label' => 'Page loads',
                'unit' => 'requests',
                'hint' => 'Counts every page load Alice serves, rolled up nightly with today added live. A sudden drop usually means an outage or a maintenance window.',
                'icon' => 'chart-bar',
                'kind' => 'sum',
                'precision' => 0,
                'detail_target' => 'traffic',
                'detail_label' => 'See request and IP details on Status',
            ],
            'traffic.avg_ms' => [
                'label' => 'Avg response',
                'unit' => 'ms',
                'hint' => 'Average server time to answer one request. Sustained values above about 1,500 ms mean pages start to feel sluggish.',
                'icon' => 'monitor',
                'kind' => 'avg',
                'precision' => 0,
                'detail_target' => 'traffic',
                'detail_label' => 'See request and IP details on Status',
            ],
            'traffic.four_xx' => [
                'label' => 'Client-side errors',
                'unit' => 'errors',
                'hint' => 'Client-side failures such as mistyped URLs and rejected sign-ins. A few are normal, while a spike points at a broken link or someone probing.',
                'icon' => 'magnifier',
                'kind' => 'sum',
                'precision' => 0,
                'detail_target' => 'server_errors',
                'detail_label' => 'Inspect the failing requests in Logs',
            ],
            'traffic.five_xx' => [
                'label' => 'Server-side errors',
                'unit' => 'errors',
                'hint' => 'Requests the app itself failed to serve. Zero is the healthy state, and any rising trend needs the Logs page.',
                'icon' => 'terminal',
                'kind' => 'sum',
                'precision' => 0,
                'detail_target' => 'server_errors',
                'detail_label' => 'Inspect the server failures in Logs',
            ],
            'client.errors' => [
                'label' => 'Browser errors',
                'unit' => 'errors',
                'hint' => 'Problems the browser reported itself, such as a script crash or a failed fetch, with the page and account attached. Server faults show in the traffic tiles.',
                'icon' => 'chart-line',
                'kind' => 'sum',
                'precision' => 0,
                'detail_target' => 'browser_errors',
                'detail_label' => 'Review the reported errors in Logs',
            ],
            'auth.failed_logins' => [
                'label' => 'Failed logins',
                'unit' => 'attempts',
                'hint' => 'Unsuccessful sign-in attempts across the whole site. A spike can mean someone guessing passwords, though most are simply mistyped.',
                'icon' => 'ban',
                'kind' => 'sum',
                'precision' => 0,
                'detail_target' => 'failed_logins',
                'detail_label' => 'See who tried, and from where, in Logs',
            ],
            'accounts.new' => [
                'label' => 'New accounts',
                'unit' => 'accounts',
                'hint' => 'Accounts of any role created that day, preview accounts excluded. A flat stretch means registrations have stalled and is worth checking.',
                'icon' => 'users',
                'kind' => 'sum',
                'precision' => 0,
                'detail_target' => 'accounts',
                'detail_label' => 'See the newest accounts',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /**
     * @return array{label: string, unit: string, hint: string, icon: string, kind: string, precision: int, detail_target: string, detail_label: string}
     */
    public static function meta(string $key): array
    {
        if (! self::has($key)) {
            throw new \InvalidArgumentException("Unknown metric: {$key}");
        }

        return self::definitions()[$key];
    }

    /**
     * Where the tile's actionable link points: the Status page tab that
     * lists the real records behind the metric, or the accounts list.
     */
    public static function detailUrl(string $key): ?string
    {
        $meta = self::definitions()[$key] ?? null;

        if ($meta === null || ($meta['detail_target'] ?? null) === null) {
            return null;
        }

        return match ($meta['detail_target']) {
            'traffic' => route('admin.status').'#'.$meta['detail_target'],
            'accounts' => route('admin.accounts.index', ['sort' => 'newest']),
            'server_errors' => route('admin.logs.index', [
                'tab' => 'server',
                'status' => $key === 'traffic.four_xx' ? '4xx' : '5xx',
                'preset' => 'last_7_days',
            ]),
            'browser_errors' => route('admin.logs.index', [
                'tab' => 'browser',
                'preset' => 'last_7_days',
            ]),
            'failed_logins' => route('admin.logs.index', [
                'tab' => 'audit',
                'action' => 'auth.failed_login',
                'preset' => 'last_7_days',
            ]),
            default => null,
        };
    }

    /**
     * The daily series for one metric across the shared window, oldest first.
     *
     * @return array<int, array{date: string, count: int|float}>
     */
    public static function series(string $key, DashboardRange $range): array
    {
        if (! self::has($key)) {
            return [];
        }

        try {
            $method = self::BUILDERS[$key];

            return match ($method) {
                'traffic' => self::traffic($key, $range),
                'clientErrors' => self::dailyCounts($range, fn () => DB::table('client_errors')
                    ->whereBetween('created_at', [$range->start(), $range->end()])
                    ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                    ->groupBy('day')),
                'failedLogins' => self::dailyCounts($range, fn () => DB::table('login_attempts')
                    ->where('successful', false)
                    ->whereBetween('created_at', [$range->start(), $range->end()])
                    ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                    ->groupBy('day')),
                'newAccounts' => self::dailyCounts($range, fn () => User::query()
                    ->whereNot('email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
                    ->whereBetween('created_at', [$range->start(), $range->end()])
                    ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                    ->groupBy('day')),
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The big number on the tile: a total for the window, a daily average,
     * or the most recent reading, already formatted for display.
     */
    public static function headline(string $key, array $series): string
    {
        if ($series === []) {
            return '—';
        }

        $meta = self::meta($key);

        return match ($meta['kind']) {
            'avg' => self::average($series, $meta['precision']).' '.$meta['unit'],
            'latest' => number_format((float) end($series)['count'], $meta['precision']).' '.$meta['unit'],
            default => number_format((float) collect($series)->sum('count')),
        };
    }

    /**
     * Chart subtitle that states the real window, never a hardcoded day count.
     */
    public static function subtitle(string $key, DashboardRange $range): string
    {
        $meta = self::meta($key);

        return match ($meta['kind']) {
            'avg' => $meta['label'].' per '.$range->bucketNoun().' ('.$meta['unit'].'), '.$range->humanWindow().'.',
            'latest' => $meta['label'].', '.$range->humanWindow().'.',
            default => $meta['label'].' per '.$range->bucketNoun().', '.$range->humanWindow().'.',
        };
    }

    /**
     * The traffic family: durable nightly roll-ups for the finished days plus
     * a live tail from the request log, which only holds the newest ~10k rows
     * so it is trusted for the last couple of days only.
     *
     * @return array<int, array{date: string, count: int|float}>
     */
    private static function traffic(string $key, DashboardRange $range): array
    {
        $rollup = DB::table('daily_stats')
            ->where('metric', $key)
            ->whereBetween('day', [$range->from, $range->to])
            ->pluck('value', 'day');

        $live = self::liveTraffic($key, $range);

        if ($range->granularity() !== 'day') {
            $daily = [];
            foreach ($range->dates() as $day) {
                $daily[$day] = (float) ($rollup[$day] ?? $live[$day] ?? 0);
            }

            return $key === 'traffic.avg_ms'
                ? self::bucketAverage($range, $daily)
                : $range->bucket(collect($daily)->map(fn ($v, $d) => ['date' => $d, 'count' => $v])->values()->all());
        }

        $points = [];
        foreach ($range->dates() as $day) {
            $raw = $rollup[$day] ?? $live[$day] ?? null;
            $points[] = [
                'date' => $day,
                'count' => $key === 'traffic.avg_ms'
                    ? (int) round((float) $raw)
                    : (int) round((float) ($raw ?? 0)),
            ];
        }

        return $points;
    }

    /**
     * Live per-day aggregates straight from the request log for the last
     * couple of days, clamped to the window. Older days fall back to 0 when
     * no roll-up row exists, which means "no traffic recorded".
     *
     * @return array<string, float>
     */
    private static function liveTraffic(string $key, DashboardRange $range): array
    {
        $from = max($range->from, now()->subDays(2)->toDateString());
        $to = min($range->to, now()->toDateString());

        if ($from > $to) {
            return [];
        }

        $expression = match ($key) {
            'traffic.avg_ms' => 'AVG(duration_ms)',
            'traffic.four_xx' => 'SUM(CASE WHEN status BETWEEN 400 AND 499 THEN 1 ELSE 0 END)',
            'traffic.five_xx' => 'SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END)',
            default => 'COUNT(*)',
        };

        return DB::table('request_logs')
            ->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->selectRaw("DATE(created_at) AS day, {$expression} AS total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * A simple per-day count map zero-filled across the whole window, then
     * bucketed to the window's granularity.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private static function dailyCounts(DashboardRange $range, callable $query): array
    {
        $rows = $query()->pluck('total', 'day');

        $daily = [];
        foreach ($range->dates() as $day) {
            $daily[] = ['date' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }

        return $range->bucket($daily);
    }

    /**
     * Bucket a daily average by averaging the days that carry data, since
     * summing milliseconds across a week would be meaningless.
     *
     * @param  array<string, float>  $daily
     * @return array<int, array{date: string, count: int}>
     */
    private static function bucketAverage(DashboardRange $range, array $daily): array
    {
        $granularity = $range->granularity();
        $buckets = [];

        foreach ($range->dates() as $day) {
            $value = $daily[$day] ?? 0;

            if ($value <= 0) {
                continue;
            }

            if ($granularity === 'day') {
                $buckets[$day] = ['date' => $day, 'count' => (int) round($value)];

                continue;
            }

            $key = $granularity === 'week'
                ? Carbon::parse($day)->startOfWeek()->toDateString()
                : substr($day, 0, 7).'-01';

            $buckets[$key]['sum'] = ($buckets[$key]['sum'] ?? 0) + $value;
            $buckets[$key]['days'] = ($buckets[$key]['days'] ?? 0) + 1;
        }

        $points = [];

        foreach ($buckets as $key => $bucket) {
            $points[] = isset($bucket['count'])
                ? $bucket
                : ['date' => $key, 'count' => (int) round($bucket['sum'] / $bucket['days'])];
        }

        return $points;
    }

    /**
     * @param  array<int, array{date: string, count: int|float}>  $series
     */
    private static function average(array $series, int $precision): float
    {
        $values = collect($series)->pluck('count')->filter(fn ($value) => $value > 0);

        if ($values->isEmpty()) {
            return 0.0;
        }

        return round($values->avg(), $precision);
    }
}
