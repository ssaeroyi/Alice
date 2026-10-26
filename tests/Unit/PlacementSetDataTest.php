<?php

$sets = require __DIR__.'/../../database/seeders/data/placement_test_sets.php';

function itemCounts(array $set): array
{
    return [
        'vocab' => count($set['vocab']),
        'cloze' => count($set['cloze']['blanks']),
        'comprehension' => count($set['comprehension']['questions']),
    ];
}

function allItems(array $set): array
{
    return [
        ...$set['vocab'],
        ...$set['cloze']['blanks'],
        ...$set['comprehension']['questions'],
    ];
}

test('every set has 10 vocab, 10 cloze blanks, and 5 comprehension questions', function () use ($sets) {
    foreach ($sets['sets'] as $set) {
        expect(itemCounts($set))->toBe(['vocab' => 10, 'cloze' => 10, 'comprehension' => 5])
            ->and($set['slug'])->not->toBeEmpty();
    }
});

test('every item has at least 3 unique options and its answer is one of them', function () use ($sets) {
    foreach ($sets['sets'] as $set) {
        foreach (allItems($set) as $item) {
            $options = array_values($item['options']);

            expect(count($options))->toBeGreaterThanOrEqual(3)
                ->and(count(array_unique($options)))->toBe(count($options))
                ->and(in_array($item['answer'], $options, true))->toBeTrue();
        }
    }
});

test('cloze blanks are keyed 1 through 10 in order and the passage is non-empty', function () use ($sets) {
    foreach ($sets['sets'] as $set) {
        $keys = array_map(fn (array $blank) => $blank['key'], $set['cloze']['blanks']);

        expect($keys)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
            ->and($set['cloze']['text'])->not->toBeEmpty()
            ->and($set['comprehension']['passage'])->not->toBeEmpty();
    }
});

test('every set carries its attribution and slug is unique', function () use ($sets) {
    $slugs = array_column($sets['sets'], 'slug');

    expect(count($slugs))->toBe(count(array_unique($slugs)));

    foreach ($sets['sets'] as $set) {
        expect($set['title'])->not->toBeEmpty()
            ->and($set['author'])->not->toBeEmpty()
            ->and($set['source_url'])->toStartWith('https://')
            ->and(strtolower($set['license']))->toBe('public domain');
    }
});
