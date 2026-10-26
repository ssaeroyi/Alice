<?php

use App\Enums\Role;
use App\Models\ConsentLog;
use App\Models\User;
use Database\Seeders\PlacementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration page can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('new users register with email, preferred username, and password', function () {
    $response = $this->post(route('register.submit'), [
        'email' => 'maria@example.com',
        'nickname' => 'Mari_2026',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('placement.step1'));
    $this->assertAuthenticated();

    $user = User::where('email', 'maria@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->nickname)->toBe('Mari_2026')
        ->and($user->first_name)->toBeNull()
        ->and($user->last_name)->toBeNull()
        ->and($user->role)->toBe(Role::Student)
        ->and($user->avatar)->not->toBeNull()
        ->and($user->avatar)->toBeIn(array_column(config('avatars'), 'file'))
        ->and($user->is_setup_complete)->toBeFalse();

    expect(ConsentLog::where('user_id', $user->id)->exists())->toBeTrue();
});

test('registration requires a preferred username', function () {
    $this->post(route('register.submit'), [
        'email' => 'maria@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertSessionHasErrors('nickname');
});

test('registration rejects a duplicate preferred username', function () {
    User::factory()->create(['nickname' => 'Mari']);

    $this->post(route('register.submit'), [
        'email' => 'maria@example.com',
        'nickname' => 'Mari',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertSessionHasErrors('nickname');
});

test('registration rejects a banned preferred username (case-insensitive)', function () {
    $this->post(route('register.submit'), [
        'email' => 'maria@example.com',
        'nickname' => 'Admin',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertSessionHasErrors('nickname');
});

test('registration rejects special characters outside the allowed set', function () {
    $this->post(route('register.submit'), [
        'email' => 'maria@example.com',
        'nickname' => 'Mari!!!',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertSessionHasErrors('nickname');
});

test('registration flashes a setup prompt toast', function () {
    $this->seed(PlacementSeeder::class);

    $this->followingRedirects()
        ->post(route('register.submit'), [
            'email' => 'maria@example.com',
            'nickname' => 'Mari',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ])
        ->assertSee('Account ready');
});

test('registration validates required fields', function () {
    $this->post(route('register.submit'), [])
        ->assertSessionHasErrors(['email', 'nickname', 'password', 'terms']);
});

test('registration rejects an already registered email', function () {
    $user = User::factory()->create();

    $this->post(route('register.submit'), [
        'email' => $user->email,
        'nickname' => 'Mari',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertSessionHasErrors('email');
});

test('registration rejects a short password', function () {
    $this->post(route('register.submit'), [
        'email' => 'maria3@example.com',
        'nickname' => 'Mari',
        'password' => 'short',
        'password_confirmation' => 'short',
        'terms' => '1',
    ])->assertSessionHasErrors('password');
});

test('registration rejects a password containing whitespace', function () {
    $this->post(route('register.submit'), [
        'email' => 'maria4@example.com',
        'nickname' => 'Mari',
        'password' => 'password 123',
        'password_confirmation' => 'password 123',
        'terms' => '1',
    ])->assertSessionHasErrors('password');
});

test('registration trims whitespace from the email', function () {
    $this->post(route('register.submit'), [
        'email' => '  maria5@example.com  ',
        'nickname' => 'Mari',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertRedirect(route('placement.step1'));

    expect(User::where('email', 'maria5@example.com')->exists())->toBeTrue();
});
