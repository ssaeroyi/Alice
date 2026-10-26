<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests see footer pages in the guest layout', function () {
    $this->get(route('about'))->assertOk()->assertSee('SIGN IN')->assertSee('Arañez John Ray');
    $this->get(route('contact'))->assertOk()->assertSee('SIGN IN');
    $this->get(route('terms'))->assertOk()->assertSee('SIGN IN');
});

test('logged-in students see footer pages in the authenticated layout', function () {
    $student = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);

    $this->actingAs($student)
        ->get(route('about'))
        ->assertOk()
        ->assertDontSee('SIGN IN')
        ->assertSee('ABOUT ALICE')
        ->assertSee('Arañez John Ray');
});

test('logged-in teachers and admins still get the guest layout on footer pages', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($teacher)->get(route('about'))->assertOk()->assertSee('SIGN IN');
    $this->actingAs($admin)->get(route('terms'))->assertOk()->assertSee('SIGN IN');
});
