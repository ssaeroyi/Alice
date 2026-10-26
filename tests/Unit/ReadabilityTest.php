<?php

use App\Support\Readability;

test('a simple sentence scores as easy reading', function () {
    $flesch = Readability::fleschReadingEase('The quick brown fox jumps over the lazy dog.');
    $grade = Readability::fleschKincaidGrade('The quick brown fox jumps over the lazy dog.');

    expect($flesch)->toBeGreaterThanOrEqual(88)->toBeLessThanOrEqual(98);
    expect($grade)->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4.5);
});

test('a dense classic sentence scores as hard reading', function () {
    $text = 'It was a dark and stormy night; the rain fell in torrents, except at occasional intervals, when it was checked by a violent gust of wind which swept up the streets, rattling along the housetops, and fiercely agitating the scanty flame of the lamps that struggled against the darkness.';

    $flesch = Readability::fleschReadingEase($text);
    $grade = Readability::fleschKincaidGrade($text);

    expect($flesch)->toBeGreaterThanOrEqual(40)->toBeLessThanOrEqual(60);
    expect($grade)->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(16);
});

test('easy text always outscores hard text on both metrics', function () {
    $easy = 'The quick brown fox jumps over the lazy dog.';
    $hard = 'It was a dark and stormy night; the rain fell in torrents, except at occasional intervals, when it was checked by a violent gust of wind which swept up the streets, rattling along the housetops, and fiercely agitating the scanty flame of the lamps that struggled against the darkness.';

    expect(Readability::fleschReadingEase($easy))->toBeGreaterThan(Readability::fleschReadingEase($hard));
    expect(Readability::fleschKincaidGrade($easy))->toBeLessThan(Readability::fleschKincaidGrade($hard));
});

test('cloze placeholders are ignored when measuring', function () {
    expect(Readability::fleschKincaidGrade('The {{1}} fox jumps over the {{2}} dog.'))
        ->toBe(Readability::fleschKincaidGrade('The fox jumps over the dog.'));
});

test('empty or whitespace-only text returns null', function () {
    expect(Readability::fleschReadingEase(''))->toBeNull();
    expect(Readability::fleschKincaidGrade('   '))->toBeNull();
});

test('every seeded passage returns a measurable score', function () {
    $sets = require __DIR__.'/../../database/seeders/data/placement_test_sets.php';

    foreach ($sets['sets'] as $set) {
        foreach ([$set['cloze']['text'], $set['comprehension']['passage']] as $passage) {
            $grade = Readability::fleschKincaidGrade($passage);

            expect($grade)->not->toBeNull()
                ->and(Readability::fleschReadingEase($passage))->not->toBeNull()
                ->and($grade)->toBeGreaterThan(0);
        }
    }
});
