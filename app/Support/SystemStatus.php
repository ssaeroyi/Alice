<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\PlacementAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemStatus
{
    /**
     * Read-only snapshot of platform health. Every source is defensive —
     * a failing probe degrades to a null value instead of breaking the page.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        return [
            'app' => [
                'name' => config('app.name'),
                'framework' => 'Laravel '.app()->version(),
                'php' => PHP_VERSION,
                'env' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
            ],
            'database' => [
                'driver' => config('database.default'),
                'host' => config('database.connections.'.config('database.default').'.host'),
                'name' => config('database.connections.'.config('database.default').'.database'),
                'size_mb' => static::databaseSizeMb(),
                'migrations' => DB::table('migrations')->count(),
                'last_migration' => DB::table('migrations')->orderByDesc('batch')->orderByDesc('id')->value('migration'),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'entries' => static::cacheEntries(),
            ],
            'session' => [
                'driver' => config('session.driver'),
            ],
            'queue' => [
                'connection' => config('queue.default'),
            ],
            'stt_debug' => (bool) session('alice.stt_debug'),
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ],
            'storage' => [
                'bytes' => static::storageBytes(),
            ],
            'counts' => [
                'users' => static::nonScratch()->count(),
                'students' => static::nonScratch()->where('role', Role::Student)->count(),
                'teachers' => static::nonScratch()->where('role', Role::Teacher)->count(),
                'admins' => User::where('role', Role::Admin)->count(),
                'suspended' => static::nonScratch()->whereNotNull('suspended_at')->count(),
                'setup_done' => static::nonScratch()->where('is_setup_complete', true)->count(),
                'setup_done_students' => static::nonScratch()->where('role', Role::Student)->where('is_setup_complete', true)->count(),
                'placement_attempts' => PlacementAttempt::count(),
                'audit_today' => AuditLog::whereDate('created_at', today())->count(),
            ],
        ];
    }

    /** Base user query excluding the disposable admin-preview scratch accounts. */
    private static function nonScratch()
    {
        return User::query()->whereNot('email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN);
    }

    private static function databaseSizeMb(): ?float
    {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            return null;
        }

        try {
            $size = DB::selectOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.TABLES
                 WHERE table_schema = DATABASE()'
            );

            return $size?->size_mb !== null ? (float) $size->size_mb : 0.0;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Real (already-allocated) PHP memory use in MB, for the dashboard vitals
     * strip. Cheap enough to call on every page load.
     */
    public static function memoryMb(): float
    {
        return round(memory_get_usage(true) / 1048576, 1);
    }

    /**
     * Seconds since the server last booted, for the dashboard vitals strip.
     * The boot timestamp is probed at most once an hour because the Windows
     * probe shells out to PowerShell; only the probe is cached, so the
     * running count stays exact between refreshes. Null when unknown.
     */
    public static function uptimeSeconds(): ?int
    {
        try {
            $bootedAt = (int) Cache::remember('system.booted_at', 3600, function (): int {
                return static::bootTimestamp() ?? 0;
            });
        } catch (\Throwable) {
            return null;
        }

        return $bootedAt > 0 ? max(0, time() - $bootedAt) : null;
    }

    /**
     * Unix timestamp the machine booted, or null when no source works:
     * /proc/uptime on Linux, one PowerShell call on Windows.
     */
    private static function bootTimestamp(): ?int
    {
        if (app()->environment('testing')) {
            return null;
        }

        if (is_readable('/proc/uptime')) {
            $raw = @file_get_contents('/proc/uptime');

            if (is_string($raw) && preg_match('/^(\d+)/', trim($raw), $matches)) {
                return time() - (int) $matches[1];
            }
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $lines = [];
        $exitCode = 1;

        @exec('powershell -NoProfile -Command "[int]([DateTimeOffset](Get-CimInstance Win32_OperatingSystem).LastBootUpTime).ToUnixTimeSeconds()"', $lines, $exitCode);

        $output = trim($lines[0] ?? '');

        return $exitCode === 0 && ctype_digit($output) ? (int) $output : null;
    }

    public static function cacheEntries(): ?int
    {
        if (config('cache.default') !== 'file') {
            return null;
        }

        try {
            $directory = storage_path('framework/cache/data');

            if (! is_dir($directory)) {
                return 0;
            }

            $count = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function storageBytes(): int
    {
        try {
            $total = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(storage_path(), \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total += $file->getSize();
                }
            }

            return $total;
        } catch (\Throwable) {
            return 0;
        }
    }
}
