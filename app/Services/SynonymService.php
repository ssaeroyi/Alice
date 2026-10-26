<?php

namespace App\Services;

/**
 * Synonym pair source for the Matching mini-game.
 *
 * Pairs come from the hand-curated whitelist in config/game_synonyms.php —
 * grade-appropriate, genuinely interchangeable word pairs with no loose
 * hypernyms, inflections or archaic terms. Drawing from a curated constant
 * (instead of a WordNet-derived table) keeps the game clean and teachable.
 */
class SynonymService
{
    /**
     * Draw random synonym pairs for a Matching round.
     *
     * @param  array<int, string>  $exclude  words to avoid (recently used)
     * @return array<int, array{a: string, b: string}>
     */
    public static function pickPairs(int $count, array $exclude = []): array
    {
        $exclude = array_flip($exclude);

        $candidates = array_filter(
            config('game_synonyms', []),
            fn (array $pair) => ! isset($exclude[$pair['a']]) && ! isset($exclude[$pair['b']]),
        );

        $candidates = array_values($candidates);
        shuffle($candidates);

        return array_slice($candidates, 0, $count);
    }

    public static function count(): int
    {
        return count(config('game_synonyms', []));
    }
}
