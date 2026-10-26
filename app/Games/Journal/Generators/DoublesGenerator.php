<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\Story;

/**
 * Workbook K mechanics, two sources:
 *  - 'story': blank the double letters in real story words (feasibility-gated)
 *  - 'classic': the workbook's static list (always fit, filler slot)
 */
class DoublesGenerator implements ActionGenerator
{
    private const ITEMS = 4;

    public function __construct(private string $mode = 'classic')
    {
    }

    public function key(): string
    {
        return 'doubles-' . $this->mode;
    }

    public function feasibility(StoryProfile $profile): int
    {
        if ($this->mode === 'classic') {
            return 1;
        }
        return count($this->storyWords($profile));
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        $items = [];

        if ($this->mode === 'story') {
            $words = (array) array_rand(array_flip($this->storyWords($profile)), min(self::ITEMS, count($this->storyWords($profile))));
            foreach ($words as $w) {
                $blanked = $this->blankDouble($w);
                if ($blanked) {
                    $items[] = $blanked;
                }
            }
        } else {
            $pool = config('missions.doubles_classic');
            $keys = (array) array_rand($pool, self::ITEMS);
            foreach ($keys as $k) {
                $items[] = [
                    'shown' => $pool[$k]['shown'],
                    'answer' => $pool[$k]['answer'],
                ];
            }
        }

        $flavor = $this->mode === 'story' ? 'DOUBLE TROUBLE' : 'DOUBLE DRILL';
        $prompt = $this->mode === 'story'
            ? 'Complete the double letters in these story words'
            : 'Complete the double letters';

        return [
            'kind' => 'fill',
            'key' => $this->key(),
            'flavor' => $flavor,
            'prompt' => $prompt,
            'payload' => ['items' => $items],
            // Answers stay in payload (server grading only).
            'public_payload' => [
                'items' => array_map(fn (array $item) => ['shown' => $item['shown']], $items),
                'title' => $flavor,
            ],
        ];
    }

    /** @return string[] */
    private function storyWords(StoryProfile $profile): array
    {
        return array_values(array_filter($profile->doubleWords(), fn (string $w) => mb_strlen($w) >= 4));
    }

    /** Blank the first double-letter run: 'still' -> 'sti__' (answer 'll'). */
    private function blankDouble(string $word): ?array
    {
        if (!preg_match('/(.)\1+/u', $word, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        [$run] = $m[0];
        $at = $m[0][1];
        return [
            'shown' => mb_substr($word, 0, $at) . '__' . mb_substr($word, $at + mb_strlen($run)),
            'answer' => $run,
        ];
    }
}
