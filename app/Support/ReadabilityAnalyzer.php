<?php

namespace App\Support;

/**
 * Readability analysis for story text.
 *
 * Flesch-Kincaid grade level, implemented in pure PHP (no external
 * package): sentence count by punctuation runs, word count by
 * str_word_count, and a vowel-group syllable approximation. The formula
 * itself is deterministic and well documented, so the implementation is
 * small, testable and fully explainable — the tier bands (beginner /
 * intermediate / advanced at 5 and 9) are the tuning used by Alice 1
 * and keep the teacher-facing labels consistent with the old system.
 *
 * The grade level is grade-equivalent (a US school grade year), so the
 * story's numeric reading level is simply round(score) clamped to the
 * shared 1-20 scale that students receive from placement.
 */
class ReadabilityAnalyzer
{
    /** Words required before a reading level is considered reliable. */
    public const MIN_WORDS = 50;

    /**
     * Full analysis of a text, or null when there is nothing to analyze.
     *
     * @return array<string, mixed>|null
     */
    public static function analyze(?string $text): ?array
    {
        if (! filled($text) || trim($text) === '') {
            return null;
        }

        $words = self::wordCount($text);
        $sentences = self::sentenceCount($text);

        if ($words === 0 || $sentences === 0) {
            return null;
        }

        $syllables = self::syllableCount($text);
        $score = self::fleschKincaidGradeLevel($words, $sentences, $syllables);

        return [
            'score' => round($score, 2),
            'reading_ease' => round(206.835 - (1.015 * ($words / $sentences)) - (84.6 * ($syllables / $words)), 2),
            'tier' => self::tierFor($score),
            'grade_level' => self::gradeLevelText($score),
            'age_range' => self::ageRange($score),
            'reading_level' => self::levelFor($score),
            'word_count' => $words,
            'sentence_count' => $sentences,
            'syllable_count' => $syllables,
            'letter_count' => self::letterCount($text),
        ];
    }

    /**
     * The numeric reading level (1-20) a story text resolves to, or null
     * when the text is too short to be analyzed reliably.
     */
    public static function readingLevelFor(?string $text): ?int
    {
        if (! filled($text) || self::wordCount($text) < self::MIN_WORDS) {
            return null;
        }

        $score = self::fleschKincaidGradeLevel(
            self::wordCount($text),
            self::sentenceCount($text),
            self::syllableCount($text),
        );

        return self::levelFor($score);
    }

    /**
     * Flesch-Kincaid grade level: 0.39 x (words / sentences) + 11.8 x
     * (syllables / words) - 15.59. Grade-equivalent output.
     */
    public static function fleschKincaidGradeLevel(int $words, int $sentences, int $syllables): float
    {
        if ($words === 0 || $sentences === 0) {
            return 0.0;
        }

        return (0.39 * ($words / $sentences)) + (11.8 * ($syllables / $words)) - 15.59;
    }

    /**
     * The reading tier a score falls into — Alice 1's tuned bands.
     */
    public static function tierFor(float $score): string
    {
        if ($score < 5) {
            return 'beginner';
        }

        if ($score < 9) {
            return 'intermediate';
        }

        return 'advanced';
    }

    /**
     * Teacher-facing grade band for a score.
     */
    public static function gradeLevelText(float $score): string
    {
        if ($score < 5) {
            return 'Elementary (Grades 1-4)';
        }

        if ($score < 9) {
            return 'Middle School (Grades 5-8)';
        }

        return 'High School+ (Grades 9-12)';
    }

    /**
     * Teacher-facing age range for a score.
     */
    public static function ageRange(float $score): string
    {
        if ($score < 5) {
            return '6-9 years';
        }

        if ($score < 9) {
            return '10-13 years';
        }

        return '14+ years';
    }

    /**
     * Numeric level (1-20): the score rounded to the nearest grade year,
     * clamped to the shared scale students receive from placement.
     */
    public static function levelFor(float $score): int
    {
        return max(1, min(20, (int) round($score)));
    }

    public static function wordCount(?string $text): int
    {
        return filled($text) ? str_word_count($text) : 0;
    }

    /**
     * A short opening line as the synopsis — two sentences at most,
     * trimmed to a readable length. Empty texts never reach here.
     */
    public static function synopsisFor(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        preg_match('/^[^.!?]*[.!?](\s+[^.!?]*[.!?])?/', $text, $match);
        $synopsis = trim($match[0] ?? '');

        return mb_strlen($synopsis) > 240 ? mb_substr($synopsis, 0, 237).'...' : $synopsis;
    }

    public static function sentenceCount(?string $text): int
    {
        if (! filled($text)) {
            return 0;
        }

        // A run of terminators (. ! ?) followed by whitespace or the end
        // counts as one sentence end. "Hello!!" is one sentence; the
        // classic abbreviation caveat ("Mr. Smith") applies, as it does
        // to any punctuation-based sentence counter.
        return preg_match_all('/[.!?]+(?=\s|$)/', $text) ?: 0;
    }

    public static function syllableCount(?string $text): int
    {
        if (! filled($text)) {
            return 0;
        }

        $total = 0;

        foreach (preg_split('/\s+/', strtolower($text)) ?: [] as $word) {
            $total += self::syllableCountWord($word);
        }

        return $total;
    }

    public static function letterCount(?string $text): int
    {
        return filled($text) ? (preg_match_all('/[A-Za-z]/', $text) ?: 0) : 0;
    }

    /**
     * Syllables of one word: a vowel-group approximation. Silent trailing
     * "e" endings (and words ending in "ed"/"es" after a consonant) are
     * stripped first, a leading "y" is dropped, then runs of vowels count
     * as one syllable. Short words count as one syllable.
     */
    public static function syllableCountWord(string $word): int
    {
        $word = strtolower(trim($word));

        if ($word === '') {
            return 0;
        }

        if (strlen($word) <= 3) {
            return 1;
        }

        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);

        $syllables = preg_match_all('/[aeiouy]{1,2}/', $word);

        return max(1, $syllables);
    }
}
