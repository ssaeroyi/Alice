<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\Story;

/**
 * Workbook B mechanics: add a letter, guided by the meaning hint
 * (mat + sail support -> mast). Static pool, always fit.
 */
class LadderGenerator implements ActionGenerator
{
    private const ITEMS = 4;

    public function key(): string
    {
        return 'ladder';
    }

    public function feasibility(StoryProfile $profile): int
    {
        return 1;
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        $pool = config('missions.ladders');
        $keys = (array) array_rand($pool, self::ITEMS);
        $items = [];
        foreach ($keys as $k) {
            $items[] = [
                'shown' => $pool[$k]['base'],
                'hint' => $pool[$k]['hint'],
                'answer' => $pool[$k]['answer'],
            ];
        }

        return [
            'kind' => 'fill',
            'key' => $this->key(),
            'flavor' => 'WORD LADDER',
            'prompt' => 'Add the hidden letter and climb the ladder',
            'payload' => ['items' => $items],
            // Answers stay in payload (server grading only).
            'public_payload' => [
                'items' => array_map(fn (array $item) => ['shown' => $item['shown'], 'hint' => $item['hint']], $items),
                'title' => 'WORD LADDER',
            ],
        ];
    }
}
