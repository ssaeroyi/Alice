<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function setupCompleteUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'is_setup_complete' => true,
    ], $attributes));
}

test('settings page can be rendered with the real email', function () {
    $user = setupCompleteUser(['email' => 'maya@example.com']);

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('maya@example.com');
});

test('settings page is blocked for users who have not finished placement', function () {
    $user = User::factory()->create(['is_setup_complete' => false]);

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertRedirect(route('placement.step1'));
});

test('a user can change their email with the correct current password', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('settings.email'), [
            'email' => 'newemail@example.com',
            'current_password' => 'oldpassword',
        ])
        ->assertSee('Email updated.');

    expect($user->fresh()->email)->toBe('newemail@example.com');
});

test('changing email rejects a wrong current password', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->post(route('settings.email'), [
            'email' => 'newemail@example.com',
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->not->toBe('newemail@example.com');
});

test('changing email rejects a duplicate email', function () {
    setupCompleteUser(['email' => 'taken@example.com']);
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->post(route('settings.email'), [
            'email' => 'taken@example.com',
            'current_password' => 'oldpassword',
        ])
        ->assertSessionHasErrors('email');
});

test('a user can change their password with the correct current password', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('settings.password'), [
            'current_password' => 'oldpassword',
            'password' => 'Newpassword123!',
            'password_confirmation' => 'Newpassword123!',
        ])
        ->assertSee('Password updated.');

    $user->refresh();

    expect(Hash::check('Newpassword123!', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull();
});

test('changing password rejects a wrong current password', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->post(route('settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeFalse();
});

test('changing password rejects a short password', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->post(route('settings.password'), [
            'current_password' => 'oldpassword',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');
});

test('changing password rejects a password containing whitespace', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->post(route('settings.password'), [
            'current_password' => 'oldpassword',
            'password' => 'new password123',
            'password_confirmation' => 'new password123',
        ])
        ->assertSessionHasErrors('password');
});

test('changing password rejects mismatched confirmation', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);

    $this->actingAs($user)
        ->post(route('settings.password'), [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'different123',
        ])
        ->assertSessionHasErrors('password');
});

test('settings page shows the display name', function () {
    $user = setupCompleteUser(['nickname' => 'Maya']);

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('Maya');
});

test('a user can update their display name without a password', function () {
    $user = setupCompleteUser(['nickname' => 'Maya']);

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('settings.name'), ['name' => 'Maya Rose'])
        ->assertSee('Display name updated.');

    expect($user->fresh()->nickname)->toBe('Maya Rose');
});

test('name update rejects a duplicate nickname', function () {
    setupCompleteUser(['nickname' => 'TakenName']);
    $user = setupCompleteUser(['nickname' => 'Maya']);

    $this->actingAs($user)
        ->post(route('settings.name'), ['name' => 'TakenName'])
        ->assertSessionHasErrors('name');
});

test('name update rejects invalid characters', function () {
    $user = setupCompleteUser(['nickname' => 'Maya']);

    $this->actingAs($user)
        ->post(route('settings.name'), ['name' => 'Maya!!!'])
        ->assertSessionHasErrors('name');
});

test('name update rejects a banned name', function () {
    $user = setupCompleteUser(['nickname' => 'Maya']);

    $this->actingAs($user)
        ->post(route('settings.name'), ['name' => 'admin'])
        ->assertSessionHasErrors('name');
});

test('settings page does not show a last-changed label for a new account', function () {
    $user = setupCompleteUser();

    expect($user->password_changed_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertOk()
        ->assertDontSee('Last changed');
});

test('settings page shows the last-changed label only after a real password change', function () {
    $user = setupCompleteUser(['password' => Hash::make('oldpassword')]);
    $user->password_changed_at = now();
    $user->save();

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('Last changed');
});

test('settings page no longer shows the daily goals section', function () {
    $user = setupCompleteUser();

    $this->actingAs($user)
        ->get(route('settings'))
        ->assertOk()
        ->assertDontSee('DAILY GOALS')
        ->assertDontSee('Reading Goal')
        ->assertDontSee('Study Goal');
});
