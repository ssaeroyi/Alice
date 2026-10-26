<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('logout flashes a signed out toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('logout'))
        ->assertSee('You have been signed out.');
});

test('logout is post-only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('logout'))
        ->assertStatus(405);
});

test('guests are redirected to login when visiting student pages', function () {
    $protectedRoutes = [
        'hub',
        'library',
        'study',
        'mini-game',
        'settings',
        'placement.step1',
        'placement.step2',
    ];

    foreach ($protectedRoutes as $routeName) {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }
});

test('authenticated users are redirected to hub when visiting guest pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('hub'));

    $this->actingAs($user)
        ->get(route('register'))
        ->assertRedirect(route('hub'));
});

test('public pages remain accessible to guests', function () {
    $publicRoutes = ['home', 'about', 'contact', 'terms', 'password.request'];

    foreach ($publicRoutes as $routeName) {
        $this->get(route($routeName))->assertOk();
    }
});
