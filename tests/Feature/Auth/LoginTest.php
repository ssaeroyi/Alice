<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('users can log in with correct credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('placement.step1'));

    $this->assertAuthenticatedAs($user);
});

test('successful login flashes a welcome greeting', function () {
    $user = User::factory()->create([
        'password' => 'password123',
        'is_setup_complete' => true,
        'nickname' => 'Ziggy',
    ]);

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('hub'))
        ->assertSessionHas('welcome.title', 'Welcome back, Ziggy!');
});

test('users who completed placement are sent to the hub on login', function () {
    $user = User::factory()->create([
        'password' => 'password123',
        'is_setup_complete' => true,
    ]);

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('hub'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot log in with a wrong password', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users cannot log in with an unknown email', function () {
    $this->post(route('login.submit'), [
        'email' => 'ghost@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('failed login uses the generic error message', function () {
    User::factory()->create(['password' => 'password123']);

    $this->from(route('login'))->post(route('login.submit'), [
        'email' => 'someone@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('remember me issues a remember token', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => '1',
    ])->assertRedirect(route('placement.step1'));

    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('login attempts are rate limited after five failures', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.submit'), [
            'email' => 'attacker@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login.submit'), [
        'email' => 'attacker@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
