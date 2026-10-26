<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login when visiting the admin area', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('students are forbidden from the admin area', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('teachers are forbidden from the admin area', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins can open the admin dashboard', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Administration');
});
