<?php

use App\Enums\Rarity;
use App\Models\Badge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['is_setup_complete' => true]);
});

test('game banner renders a badge from the plain-array summary flash', function () {
    $this->actingAs($this->student)
        ->withSession([
            'badges_awarded' => [
                ['name' => 'Word Collector', 'rarity' => 'common', 'asset_path' => 'ui/book'],
            ],
        ])
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Badge unlocked!')
        ->assertSee('Word Collector');
});

test('game banner tolerates a legacy Collection flash of badge models', function () {
    $badge = Badge::create([
        'key' => 'test_unlock',
        'name' => 'Legacy Unlock',
        'description' => 'Test',
        'asset_path' => 'ui/key',
        'rarity' => Rarity::Common,
        'active' => true,
    ]);

    $this->actingAs($this->student)
        ->withSession([
            'badges_awarded' => collect([$badge]),
        ])
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Badge unlocked!')
        ->assertSee('Legacy Unlock');
});

test('game banner shows the +N more suffix for multiple badges', function () {
    $this->actingAs($this->student)
        ->withSession([
            'badges_awarded' => [
                ['name' => 'Streak 3', 'rarity' => 'common', 'asset_path' => 'ui/fire'],
                ['name' => 'Early Bird', 'rarity' => 'rare', 'asset_path' => 'ui/sun'],
            ],
        ])
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Streak 3 +1 more');
});

test('game banner renders nothing when no badge flash is present', function () {
    $this->actingAs($this->student)
        ->get(route('mini-game'))
        ->assertOk()
        ->assertDontSee('Badge unlocked!');
});

test('game banner keeps reward->badge->greeting precedence', function () {
    $this->actingAs($this->student)
        ->withSession([
            'task_claim' => ['keys' => 5, 'points' => 10],
            'badges_awarded' => [
                ['name' => 'Word Collector', 'rarity' => 'common', 'asset_path' => 'ui/book'],
            ],
            'welcome' => ['eyebrow' => 'Welcome', 'title' => 'Welcome back!', 'sub' => ''],
        ])
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Task complete!')
        ->assertDontSee('Word Collector');
});

test('game banner renders the assignment-complete flash with its sky tone', function () {
    $this->actingAs($this->student)
        ->withSession([
            'assignment_complete' => ['title' => 'Read The Garden', 'story_id' => 1],
        ])
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Assignment complete!')
        ->assertSee('Read The Garden')
        ->assertSee('You completed it.');
});
