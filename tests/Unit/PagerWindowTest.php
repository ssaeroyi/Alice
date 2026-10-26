<?php

use App\Support\PagerWindow;

test('window near the start pins page 1 and the last page with one trailing ellipsis', function () {
    expect(PagerWindow::items(1, 26))
        ->toBe([1, 2, 3, 4, '…', 26])
        ->and(PagerWindow::items(3, 26))
        ->toBe([1, 2, 3, 4, '…', 26])
        ->and(PagerWindow::items(5, 26))
        ->toBe([1, '…', 4, 5, 6, 7, '…', 26]);
});

test('window in the middle pins both ends with a four-chip run', function () {
    expect(PagerWindow::items(13, 26))
        ->toBe([1, '…', 12, 13, 14, 15, '…', 26]);
});

test('window near the end slides to the last four pages', function () {
    expect(PagerWindow::items(25, 26))
        ->toBe([1, '…', 23, 24, 25, 26])
        ->and(PagerWindow::items(26, 26))
        ->toBe([1, '…', 23, 24, 25, 26]);
});

test('a small library shows every page without an ellipsis', function () {
    expect(PagerWindow::items(1, 4))
        ->toBe([1, 2, 3, 4])
        ->and(PagerWindow::items(3, 5))
        ->toBe([1, 2, 3, 4, 5])
        ->and(PagerWindow::items(5, 5))
        ->toBe([1, 2, 3, 4, 5]);
});

test('window is always a four-chip run with both ends pinned and no repeats', function () {
    foreach (range(1, 200) as $current) {
        $items = PagerWindow::items($current, 200);
        $chips = array_values(array_filter($items, 'is_int'));
        $window = array_values(array_slice($chips, $chips[0] === 1 ? 0 : 1, 4));

        expect($window)->toHaveCount(4)
            ->and($chips)->toBe(array_unique($chips))
            ->and(count($chips))->toBeGreaterThanOrEqual(5) // 1 + 4-run (+ last if outside)
            ->and(count($chips))->toBeLessThanOrEqual(6)
            ->and($items)->toContain(1)
            ->and($items)->toContain(200)
            ->and($items)->toContain($current);
    }
});
