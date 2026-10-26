<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins cannot access any student page', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    foreach (['hub', 'library', 'study', 'mini-game', 'settings'] as $route) {
        $this->actingAs($admin)
            ->get(route($route))
            ->assertForbidden();
    }
});

test('admins cannot access placement steps', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    foreach (['placement.step1', 'placement.step2', 'placement.step3', 'placement.step4', 'placement.step5'] as $route) {
        $this->actingAs($admin)
            ->get(route($route))
            ->assertForbidden();
    }
});

test('admins are redirected to the admin dashboard after login', function () {
    $admin = User::factory()->create([
        'role' => Role::Admin,
        'email' => 'admin@example.com',
        'password' => 'adminpass123',
        'is_setup_complete' => true,
    ]);

    $this->post(route('login.submit'), [
        'email' => 'admin@example.com',
        'password' => 'adminpass123',
    ])->assertRedirect(route('admin.dashboard'));
});

test('suspended students are logged out and sent back to login', function () {
    $student = User::factory()->create([
        'is_setup_complete' => true,
        'suspended_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast');

    $this->assertGuest();
});

test('suspended students cannot log in', function () {
    $student = User::factory()->create([
        'email' => 'student@example.com',
        'password' => 'studentpass123',
        'suspended_at' => now(),
    ]);

    $this->post(route('login.submit'), [
        'email' => 'student@example.com',
        'password' => 'studentpass123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('suspended users cannot reach placement steps', function () {
    $student = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($student)
        ->get(route('placement.step1'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('teachers are kept out of the student zone', function () {
    $teacher = User::factory()->create([
        'role' => Role::Teacher,
        'is_setup_complete' => true,
    ]);

    foreach (['hub', 'library', 'study', 'mini-game', 'settings'] as $route) {
        $this->actingAs($teacher)
            ->get(route($route))
            ->assertForbidden();
    }
});
