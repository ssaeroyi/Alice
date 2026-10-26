<?php

namespace App\Support;

use App\Models\Story;
use Illuminate\Support\Facades\Storage;

/**
 * Narration resolution for the reader page (TTS_AND_DICTIONARY.md §3.4/§3.6).
 *
 * Audio is a static asset produced offline by tools/narration/generate_narration.py.
 * There is no database table: the per-story manifest
 * (storage/app/narration/manifests/{slug}.json) records the SHA-256 of each
 * section's source text, and a page gets its player ONLY when the manifest
 * entry's hash still matches the text currently being served AND the MP3
 * exists on the public disk. A teacher edit therefore silently hides the
 * play button until regeneration - students never see broken audio.
 */
class Narration
{
    private function __construct() {}

    /**
     * Resolve audio player payloads for ALL narration segments of a story.
     * Each segment is validated against the manifest hash and the MP3 file
     * existence — stale or missing segments are silently skipped.
     *
     * @return array<int, array{page: int, url: string, seconds: float, sentences: array<int, array{int, int}>}>
     */
    public static function forStory(Story $story, array $pages): array
    {
        $manifest = Storage::disk('local')->json("narration/manifests/{$story->slug}.json");

        if (! is_array($manifest)) {
            return [];
        }

        $segments = [];

        foreach ($manifest['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $n = (int) ($section['n'] ?? 0);

            if ($n < 1 || $n > count($pages)) {
                continue;
            }

            if (! isset($section['hash'], $section['mp3'])) {
                continue;
            }

            if (! is_string($section['hash']) || ! hash_equals($section['hash'], hash('sha256', $pages[$n - 1]))) {
                continue;
            }

            $path = "audio/stories/{$story->slug}/{$section['mp3']}";

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            $sentences = $section['sentences'] ?? [];

            $segments[] = [
                'page' => $n,
                'url' => Storage::disk('public')->url($path),
                'seconds' => (float) ($section['seconds'] ?? 0),
                'sentences' => $sentences,
                'text' => (string) $pages[$n - 1],
            ];
        }

        return $segments;
    }

    /**
     * Resolve the audio player payload for one reader page, or null when
     * there is no usable (fresh + present) narration for it.
     *
     * @return array{url: string, seconds: int}|null
     */
    public static function forPage(Story $story, int $page, string $pageText): ?array
    {
        $manifest = Storage::disk('local')->json("narration/manifests/{$story->slug}.json");

        if (! is_array($manifest)) {
            return null;
        }

        $entry = collect($manifest['sections'] ?? [])
            ->first(fn ($section) => is_array($section) && (int) ($section['n'] ?? 0) === $page);

        if ($entry === null || ! isset($entry['hash'], $entry['mp3'])) {
            return null;
        }

        // Stale contract: any drift between authored text and generated
        // audio kills the player before it can ever mislead a student.
        if (! is_string($entry['hash']) || ! hash_equals($entry['hash'], hash('sha256', $pageText))) {
            return null;
        }

        $path = "audio/stories/{$story->slug}/{$entry['mp3']}";

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return [
            'url' => Storage::disk('public')->url($path),
            'seconds' => (int) ($entry['seconds'] ?? 0),
        ];
    }

    /** Coverage memoized per story slug within one request (index rows + pills). */
    private static array $coverage = [];

    private static function stallSeconds(): int
    {
        return (int) config('stories.narration.stall_seconds', 600);
    }

    /**
     * Shared coverage math, used by the sync audit AND the teacher status
     * pill so the two can never disagree (TTS_AND_DICTIONARY.md §3.4/§3.6):
     *
     *  covered  - pages whose manifest hash matches the text AND whose MP3 exists
     *  stale    - manifest sections that no longer match their page text (or
     *             sit outside the current page count)
     *  missing  - pages with no matching, present entry
     *
     * Referenced MP3 names accompany the counts so `sync --prune` can free
     * orphans from exactly the same fresh set the reader would play.
     *
     * Coverage is memoized per slug for a single render pass (the shelf
     * renders dozens of rows). Status polls, sync audits and purge-targeted
     * callers pass $fresh = true: the generator writes manifests/MP3s as it
     * runs, and a stale memo would freeze the pill mid-flight.
     *
     * @return array{manifest: bool, covered: int, stale: int, missing: int, total: int, referenced: list<string>}
     */
    public static function coverage(Story $story, bool $fresh = false): array
    {
        $slug = $story->slug;

        if (! $fresh && isset(self::$coverage[$slug])) {
            return self::$coverage[$slug];
        }

        $local = Storage::disk('local');
        $public = Storage::disk('public');
        $pages = StoryPagination::pages($story->content)['pages'];
        $manifest = $local->json("narration/manifests/{$slug}.json");

        $entries = is_array($manifest)
            ? collect($manifest['sections'] ?? [])
                ->filter(fn ($s) => is_array($s) && isset($s['n']))
                ->keyBy(fn ($s) => (int) $s['n'])
            : collect();

        [$covered, $stale, $missing] = [0, 0, 0];
        $referenced = [];

        foreach ($pages as $index => $pageText) {
            $n = $index + 1;
            $entry = $entries->get($n);

            if ($entry === null || ! isset($entry['hash'], $entry['mp3']) || ! is_string($entry['hash'])
                || ! hash_equals($entry['hash'], hash('sha256', $pageText))) {
                $missing++;

                continue;
            }

            if (! $public->exists("audio/stories/{$slug}/{$entry['mp3']}")) {
                $missing++;

                continue;
            }

            $referenced[] = basename((string) $entry['mp3']);
            $covered++;
        }

        foreach ($entries->keys() as $n) {
            $n = (int) $n;
            $entry = $entries->get($n);

            if ($n < 1 || $n > count($pages)
                || ! isset($entry['hash']) || ! is_string($entry['hash'])
                || ! hash_equals($entry['hash'], hash('sha256', $pages[$n - 1]))) {
                $stale++;
            }
        }

        $result = [
            'manifest' => is_array($manifest),
            'covered' => $covered,
            'stale' => $stale,
            'missing' => $missing,
            'total' => count($pages),
            'referenced' => $referenced,
        ];

        return $fresh ? $result : self::$coverage[$slug] = $result;
    }

    /**
     * Pure coverage state (ignores queued/generation activity). One of
     * narrated | partial | stale | missing.
     *
     * @return array{state: string, covered: int, stale: int, missing: int, total: int}
     */
    public static function statusFor(Story $story, bool $fresh = false): array
    {
        $c = self::coverage($story, $fresh);

        if ($c['total'] === 0) {
            return ['state' => 'missing', 'covered' => 0, 'stale' => 0, 'missing' => 0, 'total' => 0];
        }

        if ($c['covered'] === $c['total']) {
            return ['state' => 'narrated', 'covered' => $c['covered'], 'stale' => $c['stale'], 'missing' => $c['missing'], 'total' => $c['total']];
        }

        if ($c['covered'] > 0) {
            return ['state' => 'partial', 'covered' => $c['covered'], 'stale' => $c['stale'], 'missing' => $c['missing'], 'total' => $c['total']];
        }

        if ($c['stale'] > 0) {
            return ['state' => 'stale', 'covered' => 0, 'stale' => $c['stale'], 'missing' => $c['missing'], 'total' => $c['total']];
        }

        return ['state' => 'missing', 'covered' => 0, 'stale' => 0, 'missing' => $c['missing'], 'total' => $c['total']];
    }

    private static function wantedPath(): string
    {
        return 'narration/wanted.json';
    }

    /** @return list<array{slug: string, requested_at: string, pages: list<string>}> */
    private static function readWanted(): array
    {
        $raw = Storage::disk('local')->json(self::wantedPath());

        return is_array($raw) ? $raw : [];
    }

    private static function writeWanted(array $entries): void
    {
        Storage::disk('local')->put(
            self::wantedPath(),
            json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    /** @return list<array{slug: string, requested_at: string, pages: list<string>}> */
    public static function wantedList(): array
    {
        return array_values(array_filter(
            self::readWanted(),
            fn ($entry) => is_array($entry) && isset($entry['slug'], $entry['requested_at']),
        ));
    }

    public static function wantedFor(string $slug): ?array
    {
        foreach (self::wantedList() as $entry) {
            if ($entry['slug'] === $slug) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Record a narration request for a story (idempotent upsert). A pending
     * request surfaces as "queued" until the generator picks it up; the page
     * hashes are snapshotted so the UI can warn when the story text drifts.
     *
     * @return array{slug: string, requested_at: string, pages: list<string>}
     */
    public static function request(Story $story): array
    {
        $pages = StoryPagination::pages($story->content)['pages'];
        $entry = [
            'slug' => $story->slug,
            'requested_at' => now()->toIso8601String(),
            'pages' => array_map(fn (string $text) => hash('sha256', $text), $pages),
        ];

        $entries = self::wantedList();
        $upserted = false;

        foreach ($entries as $i => $existing) {
            if ($existing['slug'] === $story->slug) {
                $entries[$i] = $entry;
                $upserted = true;

                break;
            }
        }

        if (! $upserted) {
            $entries[] = $entry;
        }

        self::writeWanted($entries);

        // A fresh request re-arms any leftover "done" progress from the
        // previous run so the pill returns to "queued" immediately.
        Storage::disk('local')->delete("narration/progress/{$story->slug}.json");

        return $entry;
    }

    public static function removeWanted(string $slug): void
    {
        $kept = array_values(array_filter(
            self::wantedList(),
            fn ($entry) => $entry['slug'] !== $slug,
        ));

        self::writeWanted($kept);
    }

    /** Drop wanted entries whose story is no longer published. Returns removals. */
    public static function pruneWantedAgainst(array $liveSlugs): int
    {
        $entries = self::wantedList();
        $kept = array_values(array_filter(
            $entries,
            fn ($entry) => in_array($entry['slug'], $liveSlugs, true),
        ));

        $removed = count($entries) - count($kept);

        if ($removed > 0) {
            self::writeWanted($kept);
        }

        return $removed;
    }

    public static function progress(string $slug): ?array
    {
        $raw = Storage::disk('local')->json("narration/progress/{$slug}.json");

        return is_array($raw) ? $raw : null;
    }

    /** Whether the generator is actively working on a story right now. */
    public static function hasActiveRun(string $slug): bool
    {
        $progress = self::progress($slug);

        if (($progress['state'] ?? null) !== 'running') {
            return false;
        }

        $updated = strtotime((string) ($progress['updated_at'] ?? ''));

        return is_int($updated) && (time() - $updated) < self::stallSeconds();
    }

    /**
     * The merged status shown to teachers: coverage states enriched with
     * queued / generating / awaiting-deploy / stalled activity from the
     * wanted list and the generator's progress heartbeat.
     *
     * @return array{
     *   state: string,
     *   covered: int, stale: int, missing: int, total: int,
     *   progress: ?array,
     *   requested_at: ?string,
     *   text_changed_since_request: bool,
     * }
     */
    public static function runtime(Story $story, bool $fresh = false): array
    {
        $slug = $story->slug;
        $status = self::statusFor($story, $fresh);
        $coverage = self::coverage($story, $fresh);
        $progress = self::progress($slug);
        $wanted = self::wantedFor($slug);

        $state = $status['state'];
        $progressOut = null;

        if ($progress !== null) {
            $running = ($progress['state'] ?? null) === 'running';
            $updated = strtotime((string) ($progress['updated_at'] ?? ''));
            $fresh = is_int($updated) && (time() - $updated) < self::stallSeconds();

            if ($running && $fresh) {
                $state = 'generating';
            } elseif ($running && ! $fresh) {
                $state = 'stalled';
            } elseif (($progress['state'] ?? null) === 'done') {
                $state = $coverage['covered'] === $coverage['total'] && $coverage['total'] > 0
                    ? 'narrated'
                    : 'awaiting-deploy';
            }

            $progressOut = [
                'state' => $progress['state'] ?? null,
                'done' => (int) ($progress['done'] ?? 0),
                'total' => (int) ($progress['total'] ?? $coverage['total']),
                'current_page' => (int) ($progress['current_page'] ?? 0),
                'started_at' => $progress['started_at'] ?? null,
                'updated_at' => $progress['updated_at'] ?? null,
                'error' => $progress['error'] ?? null,
            ];
        }

        if (in_array($state, ['missing', 'partial', 'stale'], true) && $wanted !== null && $progress === null) {
            $state = 'queued';
        }

        $textChanged = false;

        if ($wanted !== null && $coverage['total'] > 0) {
            $current = array_map(
                fn (string $text) => hash('sha256', $text),
                StoryPagination::pages($story->content)['pages'],
            );

            $textChanged = $current !== array_values($wanted['pages'] ?? []);
        }

        // Bookkeeping: once everything is actually narrated, a finished
        // request and any leftover "done" heartbeat have nothing left to say.
        if ($state === 'narrated') {
            if ($progress !== null && ($progress['state'] ?? null) === 'done') {
                Storage::disk('local')->delete("narration/progress/{$slug}.json");
            }

            if ($wanted !== null && ! self::hasActiveRun($slug)) {
                self::removeWanted($slug);
            }
        }

        return [
            'state' => $state,
            'covered' => $coverage['covered'],
            'stale' => $coverage['stale'],
            'missing' => $coverage['missing'],
            'total' => $coverage['total'],
            'progress' => $progressOut,
            'requested_at' => $wanted['requested_at'] ?? null,
            'text_changed_since_request' => $textChanged,
        ];
    }

    /** Human label for a runtime state (shelf pill + form summary). */
    public static function label(array $runtime): string
    {
        return match ($runtime['state']) {
            'narrated' => 'Narrated',
            'partial' => $runtime['covered'].' of '.$runtime['total'].' narrated',
            'stale' => 'Audio stale',
            'missing' => 'No narration',
            'queued' => 'Queued',
            'generating' => ($runtime['progress']['done'] ?? 0).' of '.($runtime['progress']['total'] ?? $runtime['total']).' pages',
            'awaiting-deploy' => 'Awaiting deploy',
            'stalled' => 'Generator stalled',
            default => ucfirst((string) $runtime['state']),
        };
    }

    /** Pill tone classes for each runtime state (matches x-admin.pill palette). */
    public static function classes(string $state): string
    {
        return [
            'narrated' => 'bg-emerald-100 text-emerald-800',
            'partial' => 'bg-amber-100 text-amber-800',
            'stale' => 'bg-amber-100 text-amber-800',
            'missing' => 'bg-[#F3ECE0] text-[#8C7B70]',
            'queued' => 'bg-[#F3ECE0] text-[#8C7B70]',
            'generating' => 'bg-sky-100 text-sky-700',
            'awaiting-deploy' => 'bg-sky-100 text-sky-700',
            'stalled' => 'bg-rose-100 text-rose-700',
        ][$state] ?? 'bg-[#F3ECE0] text-[#8C7B70]';
    }

    /**
     * Bookkeeping-only cleanup when a story is permanently deleted. Narration
     * files are an on-disk archive (SYSTEM_DESIGN revision notes): the
     * manifest and MP3s stay, so deleting and re-importing the same source
     * reproduces the same slug and the pill reads Narrated straight away.
     * Only the queue/heartbeat bookkeeping is dropped.
     */
    public static function archiveOut(string $slug): void
    {
        $local = Storage::disk('local');

        $local->delete("narration/progress/{$slug}.json");
        self::removeWanted($slug);

        unset(self::$coverage[$slug]);
    }

    /**
     * Drop every narration artefact for a story (manifest, audio, progress,
     * wanted entry) — the hard erase behind `alice:narration:sync
     * --purge-audio`, for when a dead story's audio is really meant to go.
     */
    public static function purge(string $slug): void
    {
        $local = Storage::disk('local');
        $public = Storage::disk('public');

        $local->delete("narration/manifests/{$slug}.json");
        $local->delete("narration/progress/{$slug}.json");
        self::removeWanted($slug);

        if ($public->exists("audio/stories/{$slug}")) {
            $public->deleteDirectory("audio/stories/{$slug}");
        }

        unset(self::$coverage[$slug]);
    }
}
