<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see the landing page with both CTAs', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('START LEARNING')
        ->assertSee('SIGN IN')
        ->assertSee(route('register'))
        ->assertSee(route('login'));
});

test('signed-in admins skip the landing and go to the admin dashboard', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertRedirect(route('admin.dashboard'));
});

test('signed-in teachers skip the landing and go to the teacher dashboard', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('home'))
        ->assertRedirect(route('teacher.dashboard'));
});

test('signed-in students with a completed quick check go to the hub', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
    ]);

    $this->actingAs($student)
        ->get(route('home'))
        ->assertRedirect(route('hub'));
});

test('signed-in students without a completed quick check go to placement step 1', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => false,
    ]);

    $this->actingAs($student)
        ->get(route('home'))
        ->assertRedirect(route('placement.step1'));
});
