<?php

use App\Support\SchoolYear;
use Carbon\Carbon;

test('school year starts in June of the same year', function () {
    Carbon::setTestNow('2026-06-01');
    expect(SchoolYear::startYear())->toBe(2026);
});

test('school year starts in August of the same year', function () {
    Carbon::setTestNow('2026-08-15');
    expect(SchoolYear::startYear())->toBe(2026);
});

test('school year belongs to the previous year before June', function () {
    Carbon::setTestNow('2026-05-31');
    expect(SchoolYear::startYear())->toBe(2025);
});

test('school year belongs to the previous year in January', function () {
    Carbon::setTestNow('2026-01-10');
    expect(SchoolYear::startYear())->toBe(2025);
});
