<?php

namespace App\Support;

use App\Models\Story;

/**
 * Student-facing library presentation helpers: genre tile styling, reading
 * time math and progress-chip estimates. Read-only; all currency/progress
 * mutations stay in RewardService (SYSTEM_DESIGN §11.3).
 */
class LibraryPresentation
{
    /**
     * Per-genre tile styling for the Explore row and genre placeholders.
     * Keys are lowercase genre names from GenreSeeder; anything unknown
     * falls back to a neutral ink palette.
     *
     * @return array{bg: string, dots: string, icon: string}
     */
    /**
     * Genre tile palette + pictorial glyph. `glyph` names a vendored
     * pictorial SVG under public/image/game/icons/genre/{slug}.svg
     * (game-icons.net CC BY 3.0 tiles — see ATTRIBUTION.md). Inline
     * `icon` components remain the fallback only for genres outside
     * the canonical ten.
     */
    public static function genreStyle(string $name): array
    {
        return match (mb_strtolower($name)) {
            'fantasy' => ['bg' => 'bg-violet-500', 'dots' => '#FAF6EE', 'icon' => 'wand', 'glyph' => 'fantasy'],
            'science fiction' => ['bg' => 'bg-sky-600', 'dots' => '#FAF6EE', 'icon' => 'telescope', 'glyph' => 'science-fiction'],
            'mystery' => ['bg' => 'bg-[#2D231E]', 'dots' => '#FAF6EE', 'icon' => 'magnifier', 'glyph' => 'mystery'],
            'adventure' => ['bg' => 'bg-[#F97316]', 'dots' => '#2D231E', 'icon' => 'compass', 'glyph' => 'adventure'],
            'horror' => ['bg' => 'bg-rose-900', 'dots' => '#FAF6EE', 'icon' => 'moon', 'glyph' => 'horror'],
            'romance' => ['bg' => 'bg-rose-400', 'dots' => '#2D231E', 'icon' => 'heart', 'glyph' => 'romance'],
            'historical fiction' => ['bg' => 'bg-amber-700', 'dots' => '#FAF6EE', 'icon' => 'landmark', 'glyph' => 'historical-fiction'],
            'realistic fiction' => ['bg' => 'bg-emerald-500', 'dots' => '#2D231E', 'icon' => 'home', 'glyph' => 'realistic-fiction'],
            'biography' => ['bg' => 'bg-teal-600', 'dots' => '#FAF6EE', 'icon' => 'user', 'glyph' => 'biography'],
            'science/nature' => ['bg' => 'bg-lime-600', 'dots' => '#FAF6EE', 'icon' => 'leaf', 'glyph' => 'science-nature'],
            default => ['bg' => 'bg-[#4A4038]', 'dots' => '#FAF6EE', 'icon' => 'book-open', 'glyph' => null],
        };
    }

    /** URL slug for a genre name ("Science/Nature" -> "science-nature"). */
    public static function genreSlug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
    }

    /**
     * Raw inline SVG for a genre glyph, tinted through currentColor.
     * The genre slate is game-icons.net tiles (CC BY 3.0, restored
     * 2026-10-05) in the legacy tile format — an opaque full-canvas
     * background rect under #fff art — so the background-rect strip and
     * #fff rewrite below are what make them tintable.
     */
    public static function genreGlyphSvg(string $name): ?string
    {
        $glyph = self::genreStyle($name)['glyph'] ?? null;

        if ($glyph === null || ! is_file(public_path("image/game/icons/genre/{$glyph}.svg"))) {
            return null;
        }

        $svg = (string) file_get_contents(public_path("image/game/icons/genre/{$glyph}.svg"));
        $svg = (string) preg_replace('/<path d="M0 0h512v512H0z"\s*\/>/', '', $svg);

        return str_replace('fill="#fff"', 'fill="currentColor"', $svg);
    }

    /**
     * Raw inline SVG for an arbitrary vendored pictorial glyph under
     * public/image/game/icons/{group}/{name}.svg, tinted via
     * currentColor. Mirrors genreGlyphSvg — the background-rect strip
     * and #fff art rewrite process the restored game-icons tile format
     * (37 of 41 files); the 4 Nieobie CC0 files ship currentColor
     * directly and pass through unchanged.
     */
    public static function iconGlyph(string $group, string $name): ?string
    {
        $path = public_path("image/game/icons/{$group}/{$name}.svg");

        if (! is_file($path)) {
            return null;
        }

        $svg = (string) file_get_contents($path);
        $svg = (string) preg_replace('/<path d="M0 0h512v512H0z"\s*\/>/', '', $svg);

        return str_replace('fill="#fff"', 'fill="currentColor"', $svg);
    }

    /** Reading-time estimate, same 200 wpm constant as AliceLibrary. */
    public static function minutes(int $wordCount): int
    {
        return max(1, (int) ceil($wordCount / 200));
    }

    /** "Quick Reads" membership: flash or quick duration tiers (<5 min). */
    public const QUICK_TIERS = ['flash', 'quick'];

    /**
     * Estimated completion percent for an in-progress shelf row. The
     * reader (slice D) will persist authoritative page counts; until
     * then pages are estimated at ~250 words per page and clamped to a
     * believable band so chips never claim a finished book.
     *
     * Accepts the aliased attributes added by
     * LibraryController::inProgressQuery (current_page,
     * progress_completed_at) alongside word_count.
     */
    public static function percentFromRow(Story $row): ?int
    {
        if (! array_key_exists('current_page', $row->getAttributes())) {
            return null;
        }

        if (($row->getAttribute('progress_completed_at')) !== null) {
            return 100;
        }

        // current_page now stores scroll percentage (0–100) directly.
        // A row that exists only because of a favourite tap has no reading
        // progress to show; the card falls back to its heart chip instead
        // of claiming a fabricated "5% DONE".
        $pct = (int) $row->current_page;
        if ($pct < 1) {
            return null;
        }

        return min(95, max(5, $pct));
    }
}
