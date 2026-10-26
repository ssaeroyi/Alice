<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Notification;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\TitleRewardService;
use App\Support\NotificationService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function achievementUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => Role::Student,
        'is_setup_complete' => true,
    ], $overrides));
}

function achievementStory(User $user): Story
{
    $story = Story::create([
        'title' => 'Achievement Story '.uniqid(),
        'author' => 'Test Author',
        'license' => 'Public domain',
        'content' => 'The waves crashed against the rocks all afternoon long.',
        'status' => StoryStatus::Published,
    ]);

    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => Carbon::now(),
    ]);

    return $story;
}

test('rankPromoted dispatches one deduped inbox row per tier', function () {
    $user = achievementUser();

    expect(NotificationService::rankPromoted($user, 1, 'Bookworm', 5))->toBe(1);
    expect(NotificationService::rankPromoted($user, 1, 'Bookworm', 5))->toBe(0);

    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => 'achievement.title',
        'title' => 'New title reached',
        'body' => 'This is a rank-up alert. You reached Bookworm and earned 5 keys.',
        'link' => 'achievement:title:1',
    ]);
});

test('rankPromoted singularizes the key tally', function () {
    $user = achievementUser();

    NotificationService::rankPromoted($user, 4, 'Chapter Master', 1);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'body' => 'This is a rank-up alert. You reached Chapter Master and earned 1 key.',
    ]);
});

test('badgeUnlocked dispatches one deduped inbox row per badge', function () {
    $user = achievementUser();

    expect(NotificationService::badgeUnlocked($user, 7, 'First Story'))->toBe(1);
    expect(NotificationService::badgeUnlocked($user, 7, 'First Story'))->toBe(0);

    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => 'achievement.badge',
        'title' => 'Badge unlocked',
        'body' => 'This is a badge alert. You unlocked the First Story badge. See it on your Badges page.',
        'link' => 'achievement:badge:7',
    ]);
});

test('a title promotion dispatches one achievement.title row per crossed tier, exactly once', function () {
    $user = achievementUser(['points' => 150]);
    $now = Carbon::parse('2026-08-24 10:00:00');

    $promotion = TitleRewardService::congratulate($user, $now);
    expect($promotion['promoted'])->toBeTrue();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => 'achievement.title',
        'link' => 'achievement:title:1',
        'body' => 'This is a rank-up alert. You reached Bookworm and earned 5 keys.',
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => 'achievement.title',
        'link' => 'achievement:title:2',
        'body' => 'This is a rank-up alert. You reached Page Turner and earned 7 keys.',
    ]);

    TitleRewardService::congratulate($user->fresh(), $now);

    $this->assertDatabaseCount('notifications', 2);
});

test('a promotion with nothing crossed dispatches nothing', function () {
    $user = achievementUser(['points' => 30]);

    expect(TitleRewardService::congratulate($user, Carbon::now()))->toBe([]);
    $this->assertDatabaseCount('notifications', 0);
});

test('badge award notifies only newly-unlocked non-rank badges and never re-notifies', function () {
    $this->seed(BadgeSeeder::class);
    $user = achievementUser();
    achievementStory($user);
    $now = Carbon::now();

    $awarded = BadgeService::award($user, ['story'], $now);
    expect($awarded->pluck('key'))->toContain('first_story');

    $badgeId = $awarded->first()->id;
    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => 'achievement.badge',
        'link' => 'achievement:badge:'.$badgeId,
    ]);

    expect(BadgeService::award($user, ['story'], $now))->toHaveCount(0);
    $this->assertDatabaseCount('notifications', 1);
});

test('rank badges never notify from badge award; the title row covers them', function () {
    $this->seed(BadgeSeeder::class);
    $user = achievementUser(['points' => 50]);

    $awarded = BadgeService::award($user, ['rank'], Carbon::now());
    expect($awarded->pluck('key'))->toContain('title_bookworm');
    $this->assertDatabaseCount('notifications', 0);
});

test('opening an achievement notification marks it read and lands on the hub badge shelf', function () {
    $user = achievementUser();
    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'achievement.title',
        'title' => 'New title reached!',
        'body' => 'You reached Bookworm and earned 5 keys.',
        'link' => 'achievement:title:1',
    ]);

    $this->actingAs($user)
        ->post(route('inbox.read', $notification))
        ->assertRedirect(route('hub').'#badges');

    $this->assertNotNull($notification->fresh()->read_at);
});

test('opening a badge notification behaves the same', function () {
    $user = achievementUser();
    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'achievement.badge',
        'title' => 'Badge unlocked!',
        'body' => 'You unlocked the First Story badge.',
        'link' => 'achievement:badge:7',
    ]);

    $this->actingAs($user)
        ->post(route('inbox.read', $notification))
        ->assertRedirect(route('hub').'#badges');
});

test('read resolves the remaining synthetic links instead of 404ing', function () {
    $user = achievementUser();

    $cases = [
        ['link' => 'class-invite:5', 'expected' => route('inbox')],
        ['link' => 'reward:welcome-kit', 'expected' => route('hub')],
        ['link' => 'garbage:', 'expected' => route('hub')],
        ['link' => '', 'expected' => route('hub')],
    ];

    foreach ($cases as $case) {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'reward.welcome_kit',
            'title' => 'Welcome to ALICE!',
            'body' => 'Starter reward.',
            'link' => $case['link'],
        ]);

        $this->actingAs($user)
            ->post(route('inbox.read', $notification))
            ->assertRedirect($case['expected']);

        $this->assertNotNull($notification->fresh()->read_at);
    }
});

test('inbox renders achievement rows with their pills and the hub inbox shows them', function () {
    $user = achievementUser();
    NotificationService::rankPromoted($user, 1, 'Bookworm', 5);
    NotificationService::badgeUnlocked($user, 7, 'First Story');

    $this->actingAs($user)
        ->get(route('inbox'))
        ->assertOk()
        ->assertSee('New title reached')
        ->assertSee('Badge unlocked')
        ->assertSee('>Title<', false)
        ->assertSee('>Badge<', false);

    $this->actingAs($user)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('New title reached')
        ->assertSee('Badge unlocked');
});
