<?php

namespace App\Support;

class Readability
{
    /**
     * Flesch Reading Ease: 0 (very hard) to 100 (very easy).
     * 206.835 - 1.015 * (words/sentences) - 84.6 * (syllables/words)
     */
    public static function fleschReadingEase(string $text): ?float
    {
        $stats = self::stats($text);

        if ($stats['words'] === 0) {
            return null;
        }

        return 206.835 - 1.015 * ($stats['words'] / $stats['sentences']) - 84.6 * ($stats['syllables'] / $stats['words']);
    }

    /**
     * Flesch-Kincaid Grade Level: US school grade needed to read the text.
     * 0.39 * (words/sentences) + 11.8 * (syllables/words) - 15.59
     */
    public static function fleschKincaidGrade(string $text): ?float
    {
        $stats = self::stats($text);

        if ($stats['words'] === 0) {
            return null;
        }

        return 0.39 * ($stats['words'] / $stats['sentences']) + 11.8 * ($stats['syllables'] / $stats['words']) - 15.59;
    }

    /**
     * @return array{words: int, sentences: int, syllables: int}
     */
    private static function stats(string $text): array
    {
        $text = trim((string) preg_replace('/\{\{\d+\}\}/', '', $text));

        $words = array_values(array_filter(preg_split('/\s+/', $text) ?: [], fn (string $word) => $word !== ''));

        preg_match_all('/[.!?;:]+/', $text, $matches);
        $sentences = max(count($matches[0]), 1);

        $syllables = 0;

        foreach ($words as $word) {
            $syllables += self::syllables($word);
        }

        return [
            'words' => count($words),
            'sentences' => $sentences,
            'syllables' => $syllables,
        ];
    }

    /**
     * Vowel-group syllable counting: every run of aeiouy is one syllable,
     * minus a trailing silent e (kept for -le endings), at least one per word.
     */
    private static function syllables(string $word): int
    {
        $word = strtolower((string) preg_replace('/[^a-z]/', '', $word));

        if ($word === '') {
            return 0;
        }

        $count = preg_match_all('/[aeiouy]+/', $word) ?: 0;

        if (str_ends_with($word, 'e') && ! str_ends_with($word, 'le')) {
            $count = max($count - 1, 0);
        }

        return max($count, 1);
    }
}
