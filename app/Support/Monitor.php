<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Monitor
{
    /**
     * Traffic stats from the request log (collection is per-request, no cron needed).
     *
     * @return array<string, mixed>
     */
    public static function traffic(int $hours = 24): array
    {
        try {
            $since = now()->subHours($hours);
            $counts = DB::table('request_logs')->where('created_at', '>=', $since)
                ->selectRaw('COUNT(*) AS total, AVG(duration_ms) AS avg_ms')
                ->first();
            $errors = DB::table('request_logs')->where('created_at', '>=', $since)
                ->selectRaw(
                    'SUM(CASE WHEN status BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS four_xx, '
                    .'SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END) AS five_xx'
                )
                ->first();
            $p95 = static::percentile($since, 0.95);

            return [
                'range_hours' => $hours,
                'day_count' => (int) $counts->total,
                'day_avg_ms' => $counts->avg_ms !== null ? (int) round($counts->avg_ms) : null,
                'p95_ms' => $p95,
                'four_xx' => (int) $errors->four_xx,
                'five_xx' => (int) $errors->five_xx,
            ];
        } catch (\Throwable) {
            return [
                'range_hours' => $hours,
                'day_count' => 0,
                'day_avg_ms' => null,
                'p95_ms' => null,
                'four_xx' => 0,
                'five_xx' => 0,
            ];
        }
    }

    /**
     * Requests per day for the last $days days (0 = all history), oldest
     * first. Rolled-up daily_stats rows cover the durable past; the most
     * recent couple of days read live from the request log because the
     * nightly roll-up has not finished them yet.
     *
     * @return array<int, array{date: string, total: int}>
     */
    public static function dailySeries(int $days = 30): array
    {
        try {
            $start = $days > 0 ? now()->subDays($days - 1)->startOfDay() : null;

            $rollup = DB::table('daily_stats')
                ->where('metric', 'traffic.requests')
                ->when($start, fn ($query) => $query->where('day', '>=', $start->toDateString()))
                ->pluck('value', 'day');

            $liveSince = now()->subDays(2)->startOfDay();
            $live = DB::table('request_logs')
                ->where('created_at', '>=', $liveSince)
                ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
                ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $firstRollup = DB::table('daily_stats')->where('metric', 'traffic.requests')->min('day');
            $firstLive = DB::table('request_logs')->min(DB::raw('DATE(created_at)'));
            $cursor = $start?->toDateString() ?? ($firstRollup ?? $firstLive);

            if ($cursor === null) {
                return [];
            }

            $series = [];
            $day = Carbon::parse($cursor)->startOfDay();
            $end = now()->startOfDay();

            while ($day->lte($end)) {
                $key = $day->toDateString();
                $series[] = ['date' => $key, 'total' => (int) ($rollup[$key] ?? $live[$key] ?? 0)];
                $day->addDay();
            }

            return $series;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Mail health from the last mail test in the audit trail.
     *
     * @return array<string, mixed>
     */
    public static function mailHealth(): array
    {
        $last = AuditLog::whereIn('action', ['system.mail_test_sent', 'system.mail_test_failed'])
            ->latest('created_at')
            ->first();

        if (! $last) {
            return ['tested' => false, 'ok' => null, 'at' => null, 'duration_ms' => null];
        }

        return [
            'tested' => true,
            'ok' => $last->action === 'system.mail_test_sent',
            'at' => $last->created_at,
            'duration_ms' => $last->meta['duration_ms'] ?? null,
        ];
    }

    /**
     * Failed-login pressure across the given window (brute-force warning).
     * Now reads from the dedicated login_attempts table (SYSTEM_DESIGN §46.6).
     *
     * @return array{count: int, emails: int, top_ip: ?string, top_ip_count: int}
     */
    public static function loginThreat(int $hours = 24): array
    {
        try {
            $since = now()->subHours($hours);
            $fails = DB::table('login_attempts')
                ->where('created_at', '>=', $since)
                ->where('successful', false);

            $count = (clone $fails)->count();
            $emails = (clone $fails)->distinct('email')->count('email');

            $topIp = DB::table('login_attempts')
                ->where('created_at', '>=', $since)
                ->where('successful', false)
                ->where('ip_address', '!=', '')
                ->selectRaw('ip_address, COUNT(*) AS cnt')
                ->groupBy('ip_address')
                ->orderByDesc('cnt')
                ->first();

            return [
                'count' => $count,
                'emails' => $emails,
                'top_ip' => $topIp?->ip_address,
                'top_ip_count' => (int) ($topIp?->cnt ?? 0),
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'emails' => 0, 'top_ip' => null, 'top_ip_count' => 0];
        }
    }

    /**
     * Top IPs by request volume in the last 24 hours, with error rate.
     *
     * @return array<int, array{ip: string, total: int, errors: int, last_seen: string}>
     */
    public static function topIps(int $hours = 24): array
    {
        try {
            $since = now()->subHours($hours);

            return DB::table('request_logs')
                ->where('created_at', '>=', $since)
                ->whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->selectRaw('ip_address AS ip, COUNT(*) AS total, SUM(CASE WHEN status >= 400 THEN 1 ELSE 0 END) AS errors, MAX(created_at) AS last_seen')
                ->groupBy('ip_address')
                ->orderByDesc('total')
                ->limit(15)
                ->get()
                ->map(fn ($row) => [
                    'ip' => $row->ip,
                    'total' => (int) $row->total,
                    'errors' => (int) $row->errors,
                    'last_seen' => $row->last_seen,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * IPs with suspicious 403 spikes (possible path-scanning / auth-stuffing).
     *
     * @return array<int, array{ip: string, count_403: int, paths: int}>
     */
    public static function probeIps(int $hours = 24, int $min403 = 10): array
    {
        try {
            $since = now()->subHours($hours);

            return DB::table('request_logs')
                ->where('created_at', '>=', $since)
                ->where('status', 403)
                ->whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->selectRaw('ip_address AS ip, COUNT(*) AS count_403, COUNT(DISTINCT path) AS paths')
                ->groupBy('ip_address')
                ->having('count_403', '>=', $min403)
                ->orderByDesc('count_403')
                ->limit(15)
                ->get()
                ->map(fn ($row) => [
                    'ip' => $row->ip,
                    'count_403' => (int) $row->count_403,
                    'paths' => (int) $row->paths,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Last N login attempts (for the admin security panel).
     *
     * @return array<int, array{email: string, ip: string, ok: bool, at: string}>
     */
    public static function loginAttempts(int $limit = 50): array
    {
        try {
            return DB::table('login_attempts')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'email' => $row->email,
                    'ip' => $row->ip_address,
                    'ok' => (bool) $row->successful,
                    'at' => $row->created_at,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Error-level entries in the tail of laravel.log, for the sidebar dot
     * on Status. Same parse the Logs tab uses, cached for a few minutes so
     * every admin page render stays cheap.
     */
    public static function errorLogErrorCount(): int
    {
        if (app()->environment('testing')) {
            return 0;
        }

        return (int) Cache::remember('alice.admin.error_log.count', 300, function (): int {
            $path = storage_path('logs/laravel.log');

            if (! is_file($path)) {
                return 0;
            }

            try {
                $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -200);

                return count(array_filter($lines, fn (string $line) => preg_match(
                    '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] (?:local|production|testing|dev)\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING):\s*.+$/',
                    $line
                )));
            } catch (\Throwable) {
                return 0;
            }
        });
    }

    /**
     * Empty laravel.log and drop the cached error count so the Status
     * sidebar dot goes away on the next page load.
     */
    public static function clearErrorLog(): void
    {
        $path = storage_path('logs/laravel.log');

        if (is_file($path)) {
            @file_put_contents($path, '');
        }

        Cache::forget('alice.admin.error_log.count');
    }

    /**
     * Whether the sidebar Status dot should light up. It shows only when the
     * error log has entries AND the log file was written to after the last
     * time the admin opened Status or Logs. Fresh errors relight it; opening
     * either page marks it seen and the dot goes away until something new
     * lands in the log.
     */
    public static function errorLogDot(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        $seenAt = (int) Cache::get('alice.admin.error_log.seen_at', 0);

        if ($seenAt === 0) {
            return self::errorLogErrorCount() > 0;
        }

        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return false;
        }

        try {
            return @filemtime($path) > $seenAt && self::errorLogErrorCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Record that the admin has looked at Status or Logs. The sidebar dot
     * stays dark until the log file is appended to again.
     */
    public static function markErrorLogSeen(): void
    {
        Cache::put('alice.admin.error_log.seen_at', now()->timestamp, 60 * 60 * 24 * 7);
    }

    private static function percentile(Carbon $since, float $fraction): ?int
    {
        $count = DB::table('request_logs')->where('created_at', '>=', $since)->count();

        if ($count === 0) {
            return null;
        }

        $offset = max(0, (int) ceil($count * $fraction) - 1);

        return (int) DB::table('request_logs')
            ->where('created_at', '>=', $since)
            ->orderBy('duration_ms')
            ->offset($offset)
            ->limit(1)
            ->value('duration_ms');
    }
}
