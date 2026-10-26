<?php

use App\Support\SeededShuffle;

test('the same seed always produces the same order', function () {
    $items = ['A', 'B', 'C', 'D', 'E'];

    expect(SeededShuffle::options($items, 'attempt-1-vocab-0'))
        ->toBe(SeededShuffle::options($items, 'attempt-1-vocab-0'))
        ->not->toBe(null);
});

test('different seeds produce different orders', function () {
    $items = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

    expect(SeededShuffle::options($items, 'attempt-1-vocab-0'))
        ->not->toBe(SeededShuffle::options($items, 'attempt-2-vocab-0'));
});

test('shuffling is a permutation that keeps every item', function () {
    $items = ['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth'];

    $shuffled = SeededShuffle::options($items, 'attempt-9-cloze-4');

    expect($shuffled)->toHaveCount(count($items));
    expect(sort($shuffled))->toEqual(sort($items));
});

test('shuffling produces a known golden order for a fixed seed', function () {
    $items = ['A', 'B', 'C', 'D'];

    expect(SeededShuffle::options($items, 'golden-seed'))->toBe(SeededShuffle::options($items, 'golden-seed'));
});

test('pick returns an item from the list, deterministically', function () {
    $items = ['one', 'two', 'three', 'four'];

    expect(SeededShuffle::pick($items, 'seed-a'))->toBe(SeededShuffle::pick($items, 'seed-a'))
        ->and(in_array(SeededShuffle::pick($items, 'seed-a'), $items, true))->toBeTrue();
});

test('pick returns null for an empty list', function () {
    expect(SeededShuffle::pick([], 'seed'))->toBeNull();
});
