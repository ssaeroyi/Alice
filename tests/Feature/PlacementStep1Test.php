<?php

use App\Models\User;
use App\Support\SchoolYear;
use Database\Seeders\PlacementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlacementSeeder::class);
});

test('the grade is anchored to the current school year', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ])->assertRedirect(route('placement.intro'));

    expect($user->fresh()->grade_anchor_year)
        ->toBe(SchoolYear::startYear());
});

test('reading goals inside the slider range are accepted', function () {
    $user = User::factory()->create();

    foreach ([21, 25, 30, 45, 60] as $minutes) {
        $this->actingAs($user)->post(route('placement.step1.submit'), [
            'grade_level' => '8',
            'daily_reading_goal' => $minutes,
        ])->assertRedirect(route('placement.intro'));

        expect($user->fresh()->daily_reading_goal)->toBe($minutes);
    }
});

test('reading goals outside the slider range are rejected', function () {
    $user = User::factory()->create();

    foreach ([2, 61] as $minutes) {
        $this->actingAs($user)->post(route('placement.step1.submit'), [
            'grade_level' => '8',
            'daily_reading_goal' => $minutes,
        ])->assertSessionHasErrors('daily_reading_goal');
    }
});
