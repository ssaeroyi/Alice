<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Assembles the admin status page's "Dependencies" tab: the bundled static
 * assets (WordNet / Vosk / Kokoro weights / audio stores) and the per-package
 * composer/npm lists, plus the within-range update action.
 *
 * The asset rows are a uniform shape consumed by the blade:
 *   ['key', 'name', 'verdict', 'tone' (ok|warn|crit), 'detail']
 *
 * Read-only probes only; nothing here mutates the system.
 * point and is gated behind the controller throttle + a single-flight lock +
 * an Audit log entry. It never rewrites composer.json / package.json.
 */
class Maintenance
{
    /** How long a stored probe result is kept before the next rebuild. */
    private const CACHE_DAYS = 30;

    /**
     * Read-through cache with no page-side expiry: a stored value is served
     * as-is and rebuilt only when missing (first build, or after an action
     * cleared it). The status page must never block on the subprocess
     * probes (composer/npm outdated â‰ˆ7s).
     */
    private static function cached(string $key, callable $builder): array
    {
        $data = Cache::get($key);

        if (! is_array($data)) {
            $data = $builder();
            Cache::put($key, $data, now()->addDays(static::CACHE_DAYS));
        }

        return $data;
    }

    /**
 * The page-facing inventory: static assets only.
 *
 * @return array{assets: array<int, array>}
     */
    public static function inventory(): array
    {
        return [
            'assets' => static::assets(),
        ];
    }

    /* ------------------------------------------------------------------ *
     *  Static assets & stores                                             *
     * ------------------------------------------------------------------ */
    /**
     * @return array<int, array>
     */
    private static function assets(): array
    {
        $rows = [];

        foreach (LocalServices::check() as $base => $tool) {
            $rows[] = [
                'key' => 'asset-'.$base,
                'name' => $tool['name'],
                'verdict' => $tool['status'],
                'tone' => $tool['tone'],
                'detail' => $tool['detail'],
            ];
        }

        $rows[] = static::assetRow(
            'kokoro-weights',
            'Kokoro model weights',
            static::weightsStatus()
        );
        $rows[] = static::assetRow(
            'audio-stories',
            'Story narration store',
            static::storeStatus(storage_path('app/public/audio/stories'))
        );
        $rows[] = static::assetRow(
            'audio-tour',
            'Tour voice store',
            static::storeStatus(public_path('audio/tour'))
        );

        return $rows;
    }

    /**
     * @param  array{verdict: string, tone: string, detail: string}  $status
     */
    private static function assetRow(string $key, string $name, array $status): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'verdict' => $status['verdict'],
            'tone' => $status['tone'],
            'detail' => $status['detail'],
        ];
    }

    /**
     * @return array{verdict: string, tone: string, detail: string}
     */
    private static function weightsStatus(): array
    {
        $candidates = [
            rtrim(getenv('USERPROFILE') ?: '', '\\').'\\.cache\\huggingface',
            rtrim(getenv('HOMEDRIVE').getenv('HOMEPATH'), '\\').'\\.cache\\huggingface',
        ];

        foreach (array_unique($candidates) as $path) {
            $size = static::dirSize($path);

            if ($size !== null) {
                return [
                    'verdict' => static::bytes($size),
                    'tone' => 'ok',
                    'detail' => 'Kokoro weights cached at `.cache/huggingface` ('.static::bytes($size).') â€” generation needs no download.',
                ];
            }
        }

        return [
            'verdict' => 'None',
            'tone' => 'warn',
            'detail' => 'No Hugging Face cache found â€” weights auto-download (â‰ˆ350 MB) on the first generation on a fresh machine.',
        ];
    }

    /**
     * @return array{verdict: string, tone: string, detail: string}
     */
    private static function storeStatus(string $path): array
    {
        if (! is_dir($path)) {
            return [
                'verdict' => 'Empty',
                'tone' => 'ok',
                'detail' => 'Store is empty â€” nothing generated into it yet.',
            ];
        }

        $size = static::dirSize($path) ?? 0;
        $files = collect(glob($path.'/*'))->count();

        return [
            'verdict' => static::bytes($size),
            'tone' => 'ok',
            'detail' => number_format($files).' top-level item'.($files === 1 ? '' : 's').' totaling '.static::bytes($size).'.',
        ];
    }

    /* ------------------------------------------------------------------ *
     *  Composer / npm packages (read-only list + within-range update)     *
     * ------------------------------------------------------------------ */

    /* ------------------------------------------------------------------ *
     *  Helpers                                                            *
     * ------------------------------------------------------------------ */

    private static function dirSize(string $path): ?int
    {
        if (! is_dir($path)) {
            return null;
        }

        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return $size;
    }

    private static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        if ($bytes <= 0) {
            return '0 B';
        }

        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}
