<?php

use App\Models\User;
use App\Support\PointsRank;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rankUser(int $points): User
{
    return User::factory()->create(['points' => $points]);
}

test('a brand-new reader starts at the first tier', function () {
    $rank = PointsRank::for(rankUser(0));

    expect($rank['title'])->toBe('New Reader')
        ->and($rank['tier'])->toBe(0)
        ->and($rank['next_title'])->toBe('Bookworm')
        ->and($rank['points_to_next'])->toBe(50)
        ->and($rank['points'])->toBe(0);
});

test('tier boundaries resolve to the highest reached step', function () {
    $expect = [
        49 => 'New Reader',
        50 => 'Bookworm',
        149 => 'Bookworm',
        150 => 'Page Turner',
        299 => 'Page Turner',
        300 => 'Story Explorer',
        599 => 'Story Explorer',
        600 => 'Chapter Master',
        1199 => 'Chapter Master',
        1200 => 'Bibliophile',
        2199 => 'Bibliophile',
        2200 => 'Word Sage',
        3599 => 'Word Sage',
        3600 => 'Literary Ace',
        5499 => 'Literary Ace',
        5500 => 'Tale Weaver',
        7999 => 'Tale Weaver',
        8000 => 'Benevolent',
    ];

    foreach ($expect as $points => $title) {
        expect(PointsRank::forPoints($points)['title'])->toBe($title);
    }
});

test('progress reports the fraction travelled toward the next tier', function () {
    // New Reader floor 0, next floor 50.
    $halfway = PointsRank::forPoints(25);
    expect($halfway['progress'])->toBe(0.5);

    // Bookworm floor 50, next floor 150: 100 points of travel.
    $quarter = PointsRank::forPoints(75);
    expect($quarter['progress'])->toBe(0.25);
});

test('the top tier reports no next title and full progress', function () {
    $top = PointsRank::forPoints(8000);

    expect($top['title'])->toBe('Benevolent')
        ->and($top['next_title'])->toBeNull()
        ->and($top['points_to_next'])->toBeNull()
        ->and($top['progress'])->toBe(1.0);
});
