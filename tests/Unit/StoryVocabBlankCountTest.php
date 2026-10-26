<?php

use App\Services\StoryVocabService;

function chooseBlanksFor(array $words): array
{
    $method = new ReflectionMethod(StoryVocabService::class, 'chooseBlanks');

    return $method->invoke(null, $words);
}

test('chooseBlanks never blanks the first or last word', function () {
    $words = array_map(fn ($w) => ['text' => $w['text'], 'pos' => $w['pos']], [
        ['text' => 'The', 'pos' => 'function'],
        ['text' => 'dog', 'pos' => 'noun'],
        ['text' => 'runs', 'pos' => 'verb'],
        ['text' => 'fast', 'pos' => 'adv'],
        ['text' => 'today', 'pos' => 'noun'],
    ]);

    $blanks = chooseBlanksFor($words);

    expect($blanks)->not->toContain(0)
        ->and($blanks)->not->toContain(count($words) - 1);
});

test('chooseBlanks caps at 5 blanks and drops to fewer when the sentence is short', function () {
    $words = array_map(fn ($w) => ['text' => $w['text'], 'pos' => $w['pos']], [
        ['text' => 'A', 'pos' => 'function'],
        ['text' => 'big', 'pos' => 'adj'],
        ['text' => 'dog', 'pos' => 'noun'],
        ['text' => 'ran', 'pos' => 'verb'],
        ['text' => 'away', 'pos' => 'adv'],
        ['text' => 'home', 'pos' => 'noun'],
    ]);

    $blanks = chooseBlanksFor($words);

    expect(count($blanks))->toBeLessThanOrEqual(5)
        ->and(count($blanks))->toBeGreaterThanOrEqual(2);
});

test('chooseBlanks fills a rich interior up to 5 blanks across POS types', function () {
    $words = array_map(fn ($w) => ['text' => $w['text'], 'pos' => $w['pos']], [
        ['text' => 'The', 'pos' => 'function'],
        ['text' => 'happy', 'pos' => 'adj'],
        ['text' => 'children', 'pos' => 'noun'],
        ['text' => 'quietly', 'pos' => 'adv'],
        ['text' => 'read', 'pos' => 'verb'],
        ['text' => 'every', 'pos' => 'function'],
        ['text' => 'night', 'pos' => 'noun'],
    ]);

    $blanks = chooseBlanksFor($words);

    expect(count($blanks))->toBe(5)
        ->and(count(array_unique($blanks)))->toBe(5);
});

test('chooseBlanks never crashes on an all-null interior (empty candidate pool)', function () {
    $words = array_map(fn ($w) => ['text' => $w['text'], 'pos' => $w['pos']], [
        ['text' => 'The', 'pos' => null],
        ['text' => 'zzz', 'pos' => null],
        ['text' => 'abc', 'pos' => null],
        ['text' => 'xyz', 'pos' => null],
        ['text' => 'end', 'pos' => null],
    ]);

    $blanks = chooseBlanksFor($words);

    expect($blanks)->toBe([]);
});

test('chooseBlanks pass 2 fills with repeated POS types up to 5', function () {
    $words = array_map(fn ($w) => ['text' => $w['text'], 'pos' => $w['pos']], [
        ['text' => 'The', 'pos' => 'function'],
        ['text' => 'dog', 'pos' => 'noun'],
        ['text' => 'cat', 'pos' => 'noun'],
        ['text' => 'bird', 'pos' => 'noun'],
        ['text' => 'fish', 'pos' => 'noun'],
        ['text' => 'slept', 'pos' => 'verb'],
        ['text' => 'quietly', 'pos' => 'adv'],
        ['text' => 'outside', 'pos' => 'noun'],
    ]);

    $blanks = chooseBlanksFor($words);

    expect(count($blanks))->toBe(5)
        ->and(count(array_unique($blanks)))->toBe(5)
        ->and($blanks)->not->toContain(0)
        ->and($blanks)->not->toContain(count($words) - 1);
});
