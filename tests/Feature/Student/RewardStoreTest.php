<?php

use App\Models\KeyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function storeStudent(array $overrides = []): User
{
    return User::factory()->create([
        'is_setup_complete' => true,
        ...$overrides,
    ]);
}

test('guests cannot buy a streak freeze', function () {
    $this->post(route('rewards.freeze.buy'))->assertRedirect(route('login'));
});

test('a student can buy a streak freeze with keys', function () {
    $cost = (int) config('gamification.sinks.streak_freeze');
    $user = storeStudent(['total_keys' => 20, 'streak_freezes' => 0]);

    $this->actingAs($user)
        ->from(route('hub'))
        ->post(route('rewards.freeze.buy'))
        ->assertRedirect(route('hub'))
        ->assertSessionHas('toast');

    $user->refresh();
    expect($user->total_keys)->toBe(20 - $cost)
        ->and($user->streak_freezes)->toBe(1);

    $row = KeyTransaction::query()->sole();
    expect($row->reason)->toBe('streak_freeze')
        ->and($row->amount)->toBe(-$cost);
});

test('buying is refused at the freeze hold cap', function () {
    $user = storeStudent([
        'total_keys' => 50,
        'streak_freezes' => (int) config('gamification.freeze.hold_cap'),
    ]);

    $this->actingAs($user)
        ->from(route('hub'))
        ->post(route('rewards.freeze.buy'))
        ->assertRedirect(route('hub'))
        ->assertSessionHas('toast.type', 'error');

    $user->refresh();
    expect($user->total_keys)->toBe(50)
        ->and($user->streak_freezes)->toBe((int) config('gamification.freeze.hold_cap'))
        ->and(KeyTransaction::count())->toBe(0);
});

test('buying is refused without enough keys', function () {
    $cost = (int) config('gamification.sinks.streak_freeze');
    $user = storeStudent(['total_keys' => $cost - 1, 'streak_freezes' => 0]);

    $this->actingAs($user)
        ->from(route('hub'))
        ->post(route('rewards.freeze.buy'))
        ->assertRedirect(route('hub'))
        ->assertSessionHas('toast.type', 'error');

    $user->refresh();
    expect($user->total_keys)->toBe($cost - 1)
        ->and($user->streak_freezes)->toBe(0)
        ->and(KeyTransaction::count())->toBe(0);
});
