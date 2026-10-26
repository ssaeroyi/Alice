<?php

namespace App\Support;

use App\Models\IntegrationCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The health registry for every external service Alice talks to. Probes
 * make a tiny real call and record the outcome in integration_checks;
 * the admin status page renders the latest outcome per integration and
 * the recent history.
 */
class Integrations
{
    /**
     * @return array<string, string> integration key => display label
     */
    public static function definitions(): array
    {
        return collect(config('stories.integrations', []))
            ->map(fn (array $definition) => $definition['label'])
            ->all();
    }

    /**
     * Run the probe for one integration and record its outcome.
     *
     * @return array{ok: bool, provider?: string, message: string, error_type?: string|null, latency_ms: int}
     */
    public static function probe(string $integration): array
    {
        $started = microtime(true);

        $result = match ($integration) {
            'harper' => self::probeHarper(),
            'open_library' => self::probeOpenLibrary(),
            'wikipedia' => self::probeWikipedia(),
            default => throw new \InvalidArgumentException("Unknown integration: {$integration}"),
        };

        $result['latency_ms'] = (int) round((microtime(true) - $started) * 1000);

        IntegrationCheck::record(
            $integration,
            $result['ok'],
            $result['latency_ms'],
            $result['message'] ?? null,
            $result['error_type'] ?? null,
            $result['prompt_tokens'] ?? null,
            $result['completion_tokens'] ?? null,
        );

        return $result;
    }

    /**
     * The latest recorded outcome per integration, for the health panel.
     *
     * @return array<string, array{ok: bool, latency_ms: int, message: string, error_type: string|null, prompt_tokens: int|null, completion_tokens: int|null, created_at: string}>
     */
    public static function latestByIntegration(): array
    {
        return IntegrationCheck::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (IntegrationCheck $check) => $check->integration)
            ->map->last()
            ->map(fn (IntegrationCheck $check) => [
                'ok' => $check->ok,
                'latency_ms' => $check->latency_ms,
                'message' => $check->message,
                'error_type' => $check->error_type,
                'prompt_tokens' => $check->prompt_tokens,
                'completion_tokens' => $check->completion_tokens,
                'created_at' => $check->created_at,
            ])
            ->all();
    }

    /**
     * The most recent checks for one integration, oldest first, for the
     * history strip in the panel.
     *
     * @return list<array{ok: bool, latency_ms: int, error_type: string|null, created_at: string}>
     */
    public static function history(string $integration, int $limit = 24): array
    {
        return IntegrationCheck::where('integration', $integration)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (IntegrationCheck $check) => [
                'ok' => $check->ok,
                'latency_ms' => $check->latency_ms,
                'error_type' => $check->error_type,
                'created_at' => $check->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Harper runs on-device, so the "probe" verifies the WebAssembly
     * engine binary is present in public/ and ready to serve.
     *
     * @return array{ok: bool, message: string}
     */
    private static function probeHarper(): array
    {
        $binary = (string) config('stories.grammar.binary');
        $path = public_path(ltrim($binary, '/'));

        return file_exists($path)
            ? ['ok' => true, 'message' => 'Engine ready']
            : ['ok' => false, 'message' => 'Engine binary missing — '.$binary];
    }

    /**
     * Open Library search API — the primary cover source. A one-result
     * query keeps the probe tiny; only the HTTP status matters.
     *
     * @return array{ok: bool, message: string}
     */
    private static function probeOpenLibrary(): array
    {
        try {
            $response = Http::timeout(8)->get(config('stories.cover.open_library.search_endpoint'), [
                'q' => 'alice',
                'fields' => 'title',
                'limit' => 1,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach — '.Str::limit($e->getMessage(), 120)];
        }

        return $response->ok()
            ? ['ok' => true, 'message' => 'Search API answering']
            : ['ok' => false, 'message' => 'Search API answered '.$response->status()];
    }

    /**
     * Wikipedia action API — the cover-search fallback. Same tiny
     * one-hit query; only the HTTP status matters.
     *
     * @return array{ok: bool, message: string}
     */
    private static function probeWikipedia(): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Alice/1.0 (status probe; +https://localhost)'])
                ->get(config('stories.cover.wikipedia.endpoint'), [
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => 'alice',
                    'format' => 'json',
                    'formatversion' => 2,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach — '.Str::limit($e->getMessage(), 120)];
        }

        return $response->ok()
            ? ['ok' => true, 'message' => 'Search API answering']
            : ['ok' => false, 'message' => 'Search API answered '.$response->status()];
    }
}
