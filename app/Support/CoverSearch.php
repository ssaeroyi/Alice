<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class CoverSearch
{
    public const SOURCE_OPEN_LIBRARY = 'Open Library';

    public const SOURCE_WIKIMEDIA = 'Wikimedia';

    /**
     * "Get cover image from web" — the story's own title (and author,
     * when present) is the query. Open Library book covers come first
     * (ideal for classics); Wikipedia article thumbnails are the
     * fallback. Every returned URL is rebuilt or re-checked against the
     * allowlist in config('stories.cover.allowed_hosts') — nothing from
     * a third party is ever echoed back unfiltered (no SSRF).
     *
     * @return list<array{url: string, source: string, alt: string}>
     */
    public static function search(string $title, string $author = ''): array
    {
        $query = trim($title.' '.$author);

        if ($query === '') {
            return [];
        }

        $max = (int) config('stories.cover.max_results');

        try {
            $candidates = array_merge(static::openLibrary($query), static::wikimedia($query));
        } catch (ConnectionException|RequestException $exception) {
            throw new \RuntimeException('Cover search is temporarily unavailable — please try again in a moment.');
        }

        $seen = [];
        $results = [];

        foreach ($candidates as $candidate) {
            if (! isset($seen[$candidate['url']])) {
                $seen[$candidate['url']] = true;
                $results[] = $candidate;
            }

            if (count($results) >= $max) {
                break;
            }
        }

        return $results;
    }

    /**
     * Open Library search API: docs carry a cover_i when a scan is
     * known, which maps onto the covers CDN (covers.openlibrary.org).
     * The thumbnail URL is rebuilt locally from the numeric id — never
     * taken from the response — so it can only ever point at the
     * allowlisted covers host.
     *
     * @return list<array{url: string, source: string, alt: string}>
     */
    private static function openLibrary(string $query): array
    {
        $response = Http::timeout((int) config('stories.cover.timeout'))
            ->get(config('stories.cover.open_library.search_endpoint'), [
                'q' => $query,
                'fields' => 'title,author_name,cover_i',
                'limit' => 6,
            ]);

        if (! $response->ok()) {
            return [];
        }

        $template = config('stories.cover.open_library.cover_url');
        $results = [];

        foreach ($response->json('docs', []) as $doc) {
            $coverId = $doc['cover_i'] ?? null;

            if ($coverId === null) {
                continue;
            }

            $url = str_replace('{id}', (string) $coverId, $template);

            if (! static::isAllowed($url)) {
                continue;
            }

            $results[] = [
                'url' => $url,
                'source' => static::SOURCE_OPEN_LIBRARY,
                'alt' => trim((string) ($doc['title'] ?? '')),
            ];
        }

        return $results;
    }

    /**
     * Wikipedia search API with page-images in one call — every hit
     * that has a thumbnail yields an upload.wikimedia.org URL, which
     * must survive the allowlist before it is returned.
     *
     * @return list<array{url: string, source: string, alt: string}>
     */
    private static function wikimedia(string $query): array
    {
        $response = Http::timeout((int) config('stories.cover.timeout'))
            ->withHeaders(['User-Agent' => 'Alice/1.0 (cover search; +https://localhost)'])
            ->get(config('stories.cover.wikipedia.endpoint'), [
                'action' => 'query',
                'generator' => 'search',
                'gsrsearch' => $query,
                'gsrlimit' => 6,
                'prop' => 'pageimages',
                'piprop' => 'thumbnail',
                'pithumbsize' => 400,
                'format' => 'json',
            ]);

        if (! $response->ok()) {
            return [];
        }

        $results = [];

        foreach ($response->json('query.pages', []) as $page) {
            $url = $page['thumbnail']['source'] ?? null;

            if (! is_string($url) || ! static::isAllowed($url)) {
                continue;
            }

            $results[] = [
                'url' => $url,
                'source' => static::SOURCE_WIKIMEDIA,
                'alt' => trim((string) ($page['title'] ?? '')),
            ];
        }

        return $results;
    }

    /**
     * The SSRF guard: an http(s) URL whose host is on the allowlist.
     * Everything else is dropped before it can reach the teacher.
     */
    private static function isAllowed(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        return in_array($host, config('stories.cover.allowed_hosts'), true);
    }
}
