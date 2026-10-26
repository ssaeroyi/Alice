<?php

namespace App\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

class SeededShuffle
{
    /**
     * Deterministically shuffle a list using a string seed. The same seed
     * always yields the same order, so a placement attempt keeps stable
     * option order across page reloads while different attempts differ.
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int, mixed>
     */
    public static function options(array $items, string $seed): array
    {
        return (new Randomizer(new Mt19937(crc32($seed))))->shuffleArray(array_values($items));
    }

    /**
     * Deterministically pick one item from a list using a string seed.
     *
     * @param  array<int, mixed>  $items
     */
    public static function pick(array $items, string $seed): mixed
    {
        if ($items === []) {
            return null;
        }

        return $items[crc32($seed) % count($items)];
    }
}
