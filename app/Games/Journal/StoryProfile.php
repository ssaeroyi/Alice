<?php

namespace App\Games\Journal;

/**
 * Normalized view of a story's text used by every action generator
 * (guidelines/JOURNAL_BATTLES.md). Tokens are lowercase, typographic
 * apostrophes normalized, and every token equals one reader tap span
 * (same pattern as reader.blade.php): hyphenated compounds split into
 * their halves.
 */
class StoryProfile
{
    /** @var string[] lowercase tokens */
    public array $tokens;

    public function __construct(string $content)
    {
        // One token per word, identically to the reader's tap spans:
        // letter/digit runs with internal apostrophes only, so hyphens and
        // dashes split ("half-breed" -> "half", "bred") and every token is
        // what normalize() produces from one tap.
        preg_match_all("/[\p{L}\p{N}]+(?:['\x{2019}\x{2018}][\p{L}\p{N}]+)*/u", $content, $m);
        $this->tokens = array_values(array_map(
            fn (string $w) => mb_strtolower(str_replace(["\u{2019}", "\u{2018}"], "'", $w)),
            $m[0]
        ));
    }

    /** Distinct tokens, most frequent first. */
    public function distinct(): array
    {
        $counts = array_count_values($this->tokens);
        arsort($counts);
        return array_keys($counts);
    }

    /** @return string[] tokens ending with the suffix, distinct */
    public function suffixWords(string $suffix): array
    {
        $suffix = mb_strtolower($suffix);
        $out = [];
        foreach ($this->distinct() as $w) {
            if ($w !== $suffix && str_ends_with($w, $suffix) && mb_strlen($w) > mb_strlen($suffix)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @return string[] distinct tokens containing a double letter, len >= 3 */
    public function doubleWords(): array
    {
        $out = [];
        foreach ($this->distinct() as $w) {
            if (mb_strlen($w) >= 3 && preg_match('/(.)\1/u', $w)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @return string[] distinct tokens of at most $max letters */
    public function shortWords(int $max = 3): array
    {
        $out = [];
        foreach ($this->distinct() as $w) {
            if (mb_strlen($w) <= $max && preg_match('/\p{L}/u', $w)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @return string[] distinct tokens containing ei or ie */
    public function digraphWords(string $pair): array
    {
        $out = [];
        foreach ($this->distinct() as $w) {
            if (mb_strlen($w) >= 4 && str_contains($w, mb_strtolower($pair))) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @return string[] distinct tokens of exactly $len letters */
    public function lengthWords(int $len): array
    {
        $out = [];
        foreach ($this->distinct() as $w) {
            if (mb_strlen($w) === $len && preg_match('/^\p{L}+$/u', $w)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @return string[] distinct letter-only tokens starting with $letter */
    public function initialWords(string $letter): array
    {
        $letter = mb_strtolower($letter);
        if ($letter === '') {
            return [];
        }
        $out = [];
        foreach ($this->distinct() as $w) {
            if (mb_strlen($w) >= 2 && str_starts_with($w, $letter) && preg_match('/^\p{L}+$/u', $w)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /**
     * Start pattern => word count, letter-only tokens of length >= 2.
     * Single letters first (a-z), then the configured two-letter patterns
     * that have at least one word. Each count is the same whole-prefix count
     * that initialWords() returns, so the grid and the writer always agree.
     */
    public function initialCounts(): array
    {
        $counts = [];
        foreach ($this->distinct() as $w) {
            if (mb_strlen($w) < 2 || ! preg_match('/^\p{L}+$/u', $w)) {
                continue;
            }
            $counts[mb_substr($w, 0, 1)] = ($counts[mb_substr($w, 0, 1)] ?? 0) + 1;
        }
        ksort($counts);

        foreach ((array) config('missions.initial_patterns', []) as $pattern) {
            $n = count($this->initialWords($pattern));
            if ($n > 0) {
                $counts[$pattern] = $n;
            }
        }

        return $counts;
    }
}
