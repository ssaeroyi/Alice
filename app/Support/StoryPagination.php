<?php

namespace App\Support;

/**
 * Word-budget pagination for the student reader (STUDENT_PAGES_PLAN §4.3):
 * ~300-word pages closed at sentence boundaries, paragraphs kept whole
 * whenever they fit. Pure string math - no database, no I/O - so page N of
 * a story is byte-identical on every request and refreshes resume exactly.
 *
 * Slice D3 narration chunks reuse these same pages so audio and text stay
 * aligned.
 */
class StoryPagination
{
    public const WORDS_PER_PAGE = 300;

    private function __construct() {}

    /**
     * @return array{pages: list<string>, total_words: int, continues: list<bool>}
     */
    public static function pages(?string $content, int $budget = self::WORDS_PER_PAGE): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string) $content));

        if ($text === '') {
            return ['pages' => [], 'total_words' => 0];
        }

        $pages = [];
        $continues = [];
        $parts = [];
        $words = 0;
        $totalWords = 0;
        // Set when a page boundary falls INSIDE an oversized paragraph: the
        // next page continues the same paragraph and must not be given a
        // paragraph gap by the reader (see reader.blade is-continuation).
        $pendingContinue = false;

        $flush = function () use (&$pages, &$continues, &$parts, &$words, &$pendingContinue) {
            if ($parts === []) {
                return;
            }

            $out = '';

            foreach ($parts as $i => [$text, $glue]) {
                $out .= ($i === 0 ? '' : $glue).$text;
            }

            $pages[] = $out;
            $continues[] = $pendingContinue;
            $pendingContinue = false;
            $parts = [];
            $words = 0;
        };

        foreach (preg_split('/\n{2,}/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $count = self::countWords($paragraph);
            $totalWords += $count;

            if ($count <= $budget) {
                // Whole paragraph fits on a page: keep it intact.
                if ($words > 0 && $words + $count > $budget) {
                    $flush();
                }

                $parts[] = [$paragraph, "\n\n"];
                $words += $count;

                continue;
            }

            // Oversized paragraph: split at sentences, sentences of the
            // same paragraph stay visually joined inside their page.
            $first = true;

            foreach (self::sentences($paragraph) as $sentence) {
                $sentenceWords = self::countWords($sentence);

                if ($words > 0 && $words + $sentenceWords > $budget) {
                    $flush();
                    $pendingContinue = true;
                }

                $parts[] = [$sentence, $first ? "\n\n" : ' '];
                $words += $sentenceWords;
                $first = false;
            }
        }

        $flush();

        return ['pages' => array_values($pages), 'total_words' => $totalWords, 'continues' => $continues];
    }

    public static function pageCount(?string $content): int
    {
        return max(count(self::pages($content)['pages']), 1);
    }

    /** Split a paragraph into sentences, keeping terminal punctuation attached. */
    private static function sentences(string $paragraph): array
    {
        $parts = preg_split('/(?<=[.!?…])\s+(?=\p{Lu}["“\'(\[]?)/u', $paragraph) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out !== [] ? $out : [$paragraph];
    }

    public static function countWords(string $text): int
    {
        return preg_match_all("/[\p{L}\p{N}'’-]+/u", $text) ?: 0;
    }
}
