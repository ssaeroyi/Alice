<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\Story;

/**
 * Workbook A mechanics: letter-fact riddles, multiple choice. Static pool,
 * always fit. Choices are shuffled per generation; grading is by answer
 * string, not index.
 */
class RiddleGenerator implements ActionGenerator
{
    private const ITEMS = 4;

    public function key(): string
    {
        return 'riddle';
    }

    public function feasibility(StoryProfile $profile): int
    {
        return 1;
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        $pool = config('missions.riddles');
        $keys = (array) array_rand($pool, self::ITEMS);
        $items = [];
        foreach ($keys as $k) {
            $choices = $pool[$k]['choices'];
            shuffle($choices);
            $items[] = [
                'q' => $pool[$k]['q'],
                'choices' => $choices,
                'answer' => $pool[$k]['answer'],
            ];
        }

        return [
            'kind' => 'choice',
            'key' => $this->key(),
            'flavor' => 'LETTER RIDDLE',
            'prompt' => 'Solve the letter riddles',
            'payload' => ['items' => $items],
            // Answers stay in payload (server grading only).
            'public_payload' => [
                'items' => array_map(fn (array $item) => ['q' => $item['q'], 'choices' => $item['choices']], $items),
                'title' => 'LETTER RIDDLE',
            ],
        ];
    }
}
