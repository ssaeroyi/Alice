<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\RewardService;
use App\Support\StreakRestore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function restoreStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'nickname' => 'Ace Reader',
        'current_streak' => 3,
        'longest_streak' => 5,
        'streak_freezes' => 1,
        ...$overrides,
    ]);
}

test('the restore prompt shows once while inside the grace window', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-24']);

    $this->actingAs($user)
        ->get(route('activity'))
        ->assertOk()
        ->assertSee('STREAK AT RISK')
        ->assertSee('Would you like to restore it?')
        ->assertSee('You have 1 Streak Restore.');
});

test('accepting the prompt spends one restore and backfills the missed day', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-24']);

    $this->actingAs($user)
        ->from(route('activity'))
        ->post(route('rewards.streak.restore'), ['answer' => 'accept'])
        ->assertRedirect(route('activity'))
        ->assertSessionHas('toast');

    $user->refresh();
    expect($user->streak_freezes)->toBe(0)
        ->and($user->streak_freezes_used)->toBe(1)
        ->and($user->current_streak)->toBe(3)
        ->and($user->last_stamped_date->toDateString())->toBe('2026-08-25')
        ->and($user->preferences['streak_restore']['answer'])->toBe('accepted');

    // Today's stamp now continues the streak instead of resetting it.
    $result = RewardService::stampDay($user, 'read', Carbon::now());
    expect($result['streak'])->toBe(4);
});

test('the prompt never re-fires for a declined break and the next stamp resets', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-24', 'streak_freezes' => 2]);

    $this->actingAs($user)
        ->from(route('activity'))
        ->post(route('rewards.streak.restore'), ['answer' => 'decline'])
        ->assertRedirect(route('activity'))
        ->assertSessionHas('toast');

    $user->refresh();
    expect($user->preferences['streak_restore']['answer'])->toBe('declined');

    // Prompt is gone on the next load.
    $this->actingAs($user)
        ->get(route('activity'))
        ->assertOk()
        ->assertDontSee('Would you like to restore it?');

    // Even with restores held, an explicit "No" resets the streak (and the
    // hold cap blocks the monthly grant from masking the consumption).
    RewardService::stampDay($user->refresh(), 'read', Carbon::now());
    expect($user->refresh()->current_streak)->toBe(1)
        ->and($user->streak_freezes)->toBe(2);
});

test('accepting with zero restores is refused', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-24', 'streak_freezes' => 0]);

    $this->actingAs($user)
        ->from(route('activity'))
        ->post(route('rewards.streak.restore'), ['answer' => 'accept'])
        ->assertRedirect(route('activity'))
        ->assertSessionHas('toast.type', 'error');

    $user->refresh();
    expect($user->streak_freezes_used)->toBe(0)
        ->and($user->last_stamped_date->toDateString())->toBe('2026-08-24');
});

test('accepting is refused outside the grace window', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-23']);

    $this->actingAs($user)
        ->from(route('activity'))
        ->post(route('rewards.streak.restore'), ['answer' => 'accept'])
        ->assertRedirect(route('activity'))
        ->assertSessionHas('toast.type', 'error');

    expect($user->refresh()->streak_freezes_used)->toBe(0);
});

test('the grace-missed notice shows exactly once per break', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-23']);

    $this->actingAs($user)
        ->get(route('activity'))
        ->assertOk()
        ->assertSee('missed the 1 day grace period to restore it');

    $this->actingAs($user)
        ->get(route('activity'))
        ->assertOk()
        ->assertDontSee('missed the 1 day grace period to restore it');
});

test('a live streak never prompts', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent(['last_stamped_date' => '2026-08-25']);

    $this->actingAs($user)
        ->get(route('activity'))
        ->assertOk()
        ->assertDontSee('Would you like to restore it?');
});

test('a pending restore prompt suppresses the login welcome banner', function () {
    Carbon::setTestNow('2026-08-26 09:00:00');
    $user = restoreStudent([
        'last_stamped_date' => '2026-08-24',
        'password' => 'password123',
    ]);

    expect(StreakRestore::state($user))->not->toBeNull();

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('hub'))
        ->assertSessionMissing('welcome');
});
