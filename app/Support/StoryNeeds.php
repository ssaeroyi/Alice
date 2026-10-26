<?php

namespace App\Support;

use App\Enums\StoryStatus;
use App\Models\Story;

/**
 * The "Needs attention" chips shown on the story shelf (batch 11, part A).
 *
 * Each chip tells a teacher in one glance what a story still needs to be
 * publishable. Red chips block publishing (missing core fields, empty or
 * over-limit text); amber chips are recommended polish (quiz, cover,
 * synopsis, narration coverage for published stories); a green "Ready"
 * stories); a green "Ready" chip appears only when nothing is missing. The
 * chips also drive the default needs-first sort so the stories that need
 * work bubble to the top.
 */
class StoryNeeds
{
    /**
     * Build the ordered chip list for a story row.
     *
     * @return array<int, array{key: string, tone: string, label: string, detail: string}>
     */
    public static function chips(Story $story, int $questionCount): array
    {
        $chips = [];

        $content = (string) $story->content;
        $isImported = $story->gutenberg_id !== null;

        if (trim($content) === '') {
            $chips[] = self::chip('no_text', 'red', 'No text', 'Publishing needs the story text — add it in the Story tab.');
        }

        if (! $isImported && trim($content) !== '') {
            $overWords = str_word_count($content) > (int) config('stories.content.max_words');
            $overChars = strlen($content) > (int) config('stories.content.max_chars');

            if ($overWords || $overChars) {
                $chips[] = self::chip('over_limit', 'red', 'Over limit', 'The story text is longer than the grammar-check window — shorten it in the Story tab.');
            }
        }

        if (blank($story->author)) {
            $chips[] = self::chip('author', 'red', 'Missing: Author', 'Set an author in the Story tab — it gates publishing.');
        }

        if (blank($story->license)) {
            $chips[] = self::chip('license', 'red', 'Missing: License', 'Pick a license in the Story tab — it gates publishing.');
        }

        if ($questionCount === 0) {
            $chips[] = self::chip('quiz', 'amber', 'No quiz yet', 'Add comprehension questions in the Quiz tab so students can test their understanding.');
        }

        if (blank($story->cover_image)) {
            $chips[] = self::chip('cover', 'amber', 'No cover', 'Add a cover in the Story tab — Cover so the story stands out in the library.');
        }

        if (blank($story->synopsis)) {
            $chips[] = self::chip('synopsis', 'amber', 'No synopsis', 'Write a short synopsis in the Story tab — students see it before opening the story.');
        }

        if ($story->status === StoryStatus::Published) {
            $narration = Narration::statusFor($story);

            if ($narration['state'] === 'missing') {
                $chips[] = self::chip('narration', 'amber', 'No narration', 'Students listen along in the reader — generate narration from the story\'s Publish tab.');
            } elseif ($narration['state'] === 'stale') {
                $chips[] = self::chip('narration', 'amber', 'Audio stale', 'The story text changed since it was read aloud — regenerate the narration when the edit is final.');
            } elseif ($narration['state'] === 'partial') {
                $chips[] = self::chip('narration', 'amber', $narration['covered'].' of '.$narration['total'].' narrated', 'Only part of this story has listen-along audio — regenerate for full coverage.');
            }
        }

        if ($chips === []) {
            $chips[] = self::chip('ready', 'green', 'Ready', 'Nothing needs attention — this story is good to go.');
        }

        return $chips;
    }

    /**
     * How many non-ready chips a row carries (used for the "+N more"
     * overflow in the shelf UI).
     */
    public static function attentionCount(array $chips): int
    {
        return count(array_filter($chips, fn (array $chip) => $chip['tone'] !== 'green'));
    }

    /**
     * @return array{key: string, tone: string, label: string, detail: string}
     */
    private static function chip(string $key, string $tone, string $label, string $detail): array
    {
        return compact('key', 'tone', 'label', 'detail');
    }
}
