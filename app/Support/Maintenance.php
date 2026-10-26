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
 * The package lists are per-ecosystem:
 *   ['key', 'label', 'present', 'packages[]', 'outdated_count', 'current_count', ...]
 *
 * Read-only probes are cached; updatePackage() is the only mutating entry
 * point and is gated behind the controller throttle + a single-flight lock +
 * an Audit log entry. It never rewrites composer.json / package.json.
 */
class Maintenance
{
    private const MANAGERS_CACHE_KEY = 'alice.maintenance.managers.v3';

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
     * The page-facing inventory: static assets and package managers.
     *
     * @return array{assets: array<int, array>, managers: array<int, array>}
     */
    public static function inventory(): array
    {
        return [
            'assets' => static::assets(),
            'managers' => static::managers(),
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

    /**
     * The Packages panel payload: one entry per ecosystem, each carrying the
     * full list of DIRECT dependencies with the installed version and, when a
     * newer release inside the manifest constraint exists, the latest one.
     *
     * @return array<int, array>
     */
    private static function managers(): array
    {
        return static::cached(static::MANAGERS_CACHE_KEY, function () {
            return array_values(array_filter([
                static::composerEcosystem(),
                static::npmEcosystem(),
            ]));
        });
    }

    /**
     * Eagerly rebuild the package cache so the redirect back to the status
     * page finds a warm list instead of paying for composer/npm inline.
     */
    public static function refreshManagers(): void
    {
        try {
            Cache::forget(static::MANAGERS_CACHE_KEY);
            static::managers();
        } catch (\Throwable) {
            // Leave the cache cold â€” the status page rebuilds it once.
        }
    }

    /**
     * Apply a within-range update to ONE direct dependency. The package
     * name must already be present in the freshly-read direct list (never
     * free-form), and the update is delegated to the package manager's own
     * everyday command â€” composer.json / package.json are never rewritten, so
     * a major-version jump is impossible. The cache is rebuilt warm afterwards
     * so the redirect back to the page reflects the new state immediately.
     *
     * @return array{ok: bool, detail: string, rebuilt: bool}
     */
    public static function updatePackage(string $ecosystem, string $package): array
    {
        $runner = app(ProcessRunner::class);

        // Re-read the list fresh (bypasses the 6h cache) so the package we
        // are about to touch is one we actually know about.
        $fresh = $ecosystem === 'composer' ? static::composerEcosystem() : static::npmEcosystem();
        $known = collect($fresh['packages'] ?? [])->firstWhere('name', $package);

        if (! $fresh['present'] || $known === null) {
            return ['ok' => false, 'detail' => $package.' is not a known direct '.($ecosystem === 'composer' ? 'composer' : 'npm').' dependency.', 'rebuilt' => false];
        }

        $result = $ecosystem === 'composer'
            ? $runner->composerUpdate($package)
            : $runner->npmUpdate($package);

        $rebuilt = false;

        if ($ecosystem === 'npm' && $result['ok']) {
            // The browser serves public/build, not node_modules â€” a lockfile
            // change is invisible until the bundle is rebuilt.
            $build = $runner->npmBuild();
            $rebuilt = $build['ok'];
            $result['ok'] = $build['ok'];
            $result['error'] .= $build['ok'] ? '' : ' '.$build['error'];
        }

        $detail = $result['ok']
            ? 'Updated '.$package.' inside its existing constraint.'
            : Str::limit(trim($result['error'] ?: $result['output']), 200);

        Audit::log('system.packages.'.$ecosystem.'-updated', null, [
            'package' => $package,
            'ok' => $result['ok'],
            'rebuilt' => $rebuilt,
            'detail' => $detail,
        ]);

        static::refreshManagers();

        return ['ok' => $result['ok'], 'detail' => $detail, 'rebuilt' => $rebuilt];
    }

    /**
     * @return array{key: string, label: string, present: bool, packages: array<int, array>, outdated_count: int, current_count: int, clean_detail: string, absent_detail: string}|null
     */
    private static function composerEcosystem(): array
    {
        $runner = app(ProcessRunner::class);
        $probe = $runner->capture(['composer', '--version'], 15);

        if (! $probe['ok']) {
            return [
                'key' => 'composer',
                'label' => 'Composer (PHP dependencies)',
                'present' => false,
                'packages' => [],
                'outdated_count' => 0,
                'current_count' => 0,
                'clean_detail' => '',
                'absent_detail' => 'composer is not on this machine â€” run `composer update` on the developer terminal. composer.lock pins current versions here.',
            ];
        }

        $show = json_decode($runner->capture(['composer', 'show', '--direct', '--format=json'], 30)['output'] ?? '', true);
        $installed = $show['installed'] ?? [];
        $devNames = array_flip(is_array($show['dev-package-names'] ?? null) ? $show['dev-package-names'] : []);
        $outdated = json_decode($runner->capture(['composer', 'outdated', '--direct', '--format=json'], 30)['output'] ?? '', true)['installed'] ?? [];

        $packages = [];

        foreach (is_array($installed) ? $installed : [] as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $latest = is_array($outdated) && isset($outdated[$name])
                ? (string) ($outdated[$name]['latest']['version'] ?? '')
                : null;

            $packages[] = [
                'name' => $name,
                'version' => (string) ($entry['version'] ?? '?'),
                'latest' => ($latest === '' ? null : $latest),
                'dev' => isset($devNames[$name]),
                'outdated' => $latest !== null && $latest !== '',
            ];
        }

        return static::buildEcosystem(
            key: 'composer',
            label: 'Composer (PHP dependencies)',
            packages: $packages,
            clean_detail: 'No outdated direct dependencies â€” composer.lock is current.',
            absent_detail: 'composer is not on this machine â€” run `composer update` on the developer terminal. composer.lock pins current versions here.',
        );
    }

    /**
     * @return array{key: string, label: string, present: bool, packages: array<int, array>, outdated_count: int, current_count: int, clean_detail: string, absent_detail: string}|null
     */
    private static function npmEcosystem(): array
    {
        $runner = app(ProcessRunner::class);
        $probe = $runner->capture(['npm', '--version'], 15);

        if (! $probe['ok']) {
            return [
                'key' => 'npm',
                'label' => 'npm (frontend dependencies)',
                'present' => false,
                'packages' => [],
                'outdated_count' => 0,
                'current_count' => 0,
                'clean_detail' => '',
                'absent_detail' => 'npm is not on this machine â€” run `npm update` on the developer terminal. package-lock.json pins current versions here.',
            ];
        }

        // `npm ls --json` exits non-zero on peer warnings but still prints
        // usable JSON, so the output is decoded regardless of the exit flag.
        $tree = json_decode($runner->capture(['npm', 'ls', '--depth=0', '--json'], 30)['output'] ?? '', true);
        $outdated = json_decode($runner->capture(['npm', 'outdated', '--json'], 30)['output'] ?? '', true);

        $packages = [];

        foreach (is_array($tree['dependencies'] ?? null) ? $tree['dependencies'] : [] as $name => $entry) {
            $latest = is_array($outdated) && isset($outdated[$name])
                ? (string) ($outdated[$name]['latest'] ?? '')
                : null;

            $packages[] = [
                'name' => (string) $name,
                'version' => (string) ($entry['version'] ?? '?'),
                'latest' => ($latest === '' ? null : $latest),
                'dev' => (bool) ($entry['dev'] ?? false),
                'outdated' => $latest !== null && $latest !== '',
            ];
        }

        return static::buildEcosystem(
            key: 'npm',
            label: 'npm (frontend dependencies)',
            packages: $packages,
            clean_detail: 'No outdated packages. package-lock.json pins current versions.',
            absent_detail: 'npm is not on this machine â€” run `npm update` on the developer terminal. package-lock.json pins current versions here.',
        );
    }

    /**
     * @param  array<int, array>  $packages
     * @return array{key: string, label: string, present: bool, packages: array<int, array>, outdated_count: int, current_count: int, clean_detail: string, absent_detail: string}
     */
    private static function buildEcosystem(string $key, string $label, array $packages, string $clean_detail, string $absent_detail): array
    {
        usort($packages, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $outdated = count(array_filter($packages, fn (array $package): bool => $package['outdated']));

        return [
            'key' => $key,
            'label' => $label,
            'present' => true,
            'packages' => $packages,
            'outdated_count' => $outdated,
            'current_count' => count($packages) - $outdated,
            'clean_detail' => $clean_detail,
            'absent_detail' => $absent_detail,
        ];
    }

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
