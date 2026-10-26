<?php

namespace App\Support;

use Normalizer;

/**
 * Normalizes free-text section labels to a canonical form.
 * Input like "diamOnd ✨", "9-diamond", "ilang-ilang" all produce
 * deterministic, sortable, title-cased labels: "9-Diamond", "Ilang-Ilang".
 */
class Section
{
    /**
     * Normalize a free-text section label to canonical form.
     *
     * Strips emoji, symbols, quotes; trims; collapses whitespace;
     * title-cases with hyphen preservation for Filipino section names.
     */
    public static function normalize(string $input): string
    {
        $s = $input;

        // Unicode normalize (NFC) — collapses smart quotes, full-width chars, etc.
        if (Normalizer::isNormalized($s, Normalizer::FORM_C) === false) {
            $s = Normalizer::normalize($s, Normalizer::FORM_C);
        }

        // Strip emoji, symbols, control chars — keep only letters, digits,
        // hyphens, apostrophes (for legit names), and spaces.
        $s = preg_replace('/[^\p{L}\p{N}\s\'\-]/u', '', $s);

        // Collapse internal whitespace runs to single space, trim.
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s);

        // Title-case with hyphen-aware capitalization.
        // Explode on spaces, then title-case each token.
        // Within each token, capitalize after hyphens.
        $s = static::titleCase($s);

        return $s;
    }

    /**
     * Title-case a string, preserving hyphens and hyphenated word boundaries.
     *
     * "9-diamond" → "9-Diamond"
     * "ilang-ilang" → "Ilang-Ilang"
     * "st. anne" → "St. Anne"
     * "9 A" → "9 A"
     */
    private static function titleCase(string $input): string
    {
        // Split on spaces, process each word.
        $words = explode(' ', $input);

        $title = array_map(function ($word) {
            // Split on hyphens to capitalize each hyphen-segment.
            $parts = explode('-', $word);

            return implode('-', array_map(function ($part, $idx) {
                // Always capitalize first letter of the first hyphen-segment
                // and first letter of subsequent segments.
                if ($part === '') {
                    return '';
                }

                // Strip leading apostrophes (possessives) for detection.
                $trimmed = ltrim($part, '\u2019\u2018\u0027');

                if ($trimmed === '') {
                    return $part;
                }

                return mb_strtoupper(mb_substr($trimmed, 0, 1)).mb_substr($trimmed, 1);
            }, $parts, array_keys($parts)));
        }, $words);

        return implode(' ', $title);
    }
}
