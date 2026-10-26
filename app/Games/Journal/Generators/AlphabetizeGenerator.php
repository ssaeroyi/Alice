<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\Story;

/**
 * Alphabetizing (workbook mechanics): each item offers three story words
 * with different first letters; the student picks the one that comes first
 * in ABC order. Uses the choice panel, so no new UI. Story-derived and
 * feasible whenever the text has words under three different first letters.
 */
class AlphabetizeGenerator implements ActionGenerator
{
    private const ITEMS = 3;

    private const CHOICES = 3;

    public function key(): string
    {
        return 'alphabetize';
    }

    public function feasibility(StoryProfile $profile): int
    {
        $byLetter = $this->byFirstLetter($profile);

        return count($byLetter) >= self::CHOICES ? array_sum(array_map('count', $byLetter)) : 0;
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        $byLetter = $this->byFirstLetter($profile);
        $letters = array_keys($byLetter);
        $items = [];

        for ($i = 0; $i < self::ITEMS; $i++) {
            // Distinct first letters make the correct answer unambiguous.
            $picked = array_rand(array_flip($letters), self::CHOICES);
            $words = [];
            foreach ((array) $picked as $letter) {
                $words[] = $byLetter[$letter][array_rand($byLetter[$letter])];
            }

            $answer = $this->firstInAbc($words);
            shuffle($words);

            $items[] = [
                'q' => 'Which word comes first in ABC order?',
                'choices' => $words,
                'answer' => $answer,
            ];
        }

        return [
            'kind' => 'choice',
            'key' => $this->key(),
            'flavor' => 'ABC ORDER',
            'prompt' => 'Pick the word that comes first in ABC order',
            'payload' => ['items' => $items],
            // Answers stay in payload (server grading only).
            'public_payload' => [
                'items' => array_map(fn (array $item) => ['q' => $item['q'], 'choices' => $item['choices']], $items),
                'title' => 'ABC ORDER',
            ],
        ];
    }

    /** @return array<string, list<string>> first letter => distinct words */
    private function byFirstLetter(StoryProfile $profile): array
    {
        $out = [];
        foreach ($profile->distinct() as $word) {
            if (mb_strlen($word) < 4 || ! preg_match('/^\p{L}+$/u', $word)) {
                continue;
            }
            $out[mb_substr($word, 0, 1)][] = $word;
        }

        // Only letters with words to spare keep the picker varied.
        return array_filter($out, fn (array $words) => $words !== []);
    }

    /** @param list<string> $words */
    private function firstInAbc(array $words): string
    {
        sort($words, SORT_STRING);

        return $words[0];
    }
}
