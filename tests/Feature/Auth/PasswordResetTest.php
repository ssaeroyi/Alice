<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('forgot password page can be rendered', function () {
    $this->get(route('password.request'))->assertOk();
});

test('reset password page can be rendered with token and email', function () {
    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'maria@example.com']))->assertOk();
});

test('submitting a registered email sends a reset link with a generic message', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->followingRedirects()
        ->post(route('password.email'), ['email' => $user->email])
        ->assertSee('If your email is registered, we have sent a reset link. It may take a minute to arrive.');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('submitting an unknown email returns the same generic message and sends nothing', function () {
    Notification::fake();

    $this->followingRedirects()
        ->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSee('If your email is registered, we have sent a reset link. It may take a minute to arrive.');

    Notification::assertNothingSent();
});

test('forgot password validates the email field', function () {
    $this->post(route('password.email'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('a user can reset their password with a valid token', function () {
    $user = User::factory()->create();

    $token = Password::createToken($user);

    $response = $this->followingRedirects()->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Newpassword123!',
        'password_confirmation' => 'Newpassword123!',
    ]);

    $response->assertSee('Your password has been reset. Sign in with your new password.');

    expect(Hash::check('Newpassword123!', $user->fresh()->password))->toBeTrue();
});

test('reset password rejects an invalid token', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'Newpassword123!',
        'password_confirmation' => 'Newpassword123!',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('Newpassword123!', $user->fresh()->password))->toBeFalse();
});

test('reset password validates the new password rules', function () {
    $user = User::factory()->create();

    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('reset password rejects a password containing whitespace', function () {
    $user = User::factory()->create();

    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new password123',
        'password_confirmation' => 'new password123',
    ])->assertSessionHasErrors('password');
});
