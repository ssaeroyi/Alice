<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin, 'password' => 'Secret123!']);
});

test('the settings page is admin-only and renders its sections', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Profile')
        ->assertSee('Password & security')
        ->assertSee(route('admin.settings.profile'), false)
        ->assertSee(route('admin.settings.password'), false);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('admin.settings'))
        ->assertForbidden();

    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

test('the admin profile updates the display name and real name', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.profile'), [
            'nickname' => 'Head Admin',
        ])
        ->assertRedirect(route('admin.settings'));

    $this->assertSame('Head Admin', $this->admin->fresh()->nickname);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.profile'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ])
        ->assertRedirect(route('admin.settings'));

    $admin = $this->admin->fresh();
    $this->assertSame('Ada', $admin->first_name);
    $this->assertSame('Lovelace', $admin->last_name);
});

test('an email change requires the current password', function () {
    $original = $this->admin->email;

    $this->actingAs($this->admin)
        ->from(route('admin.settings'))
        ->post(route('admin.settings.email'), [
            'email' => 'new.admin@alice.local',
            'current_password' => 'wrong-password',
        ])
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasErrors('current_password');

    $this->assertSame($original, $this->admin->fresh()->email);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.email'), [
            'email' => 'new.admin@alice.local',
            'current_password' => 'Secret123!',
        ])
        ->assertRedirect(route('admin.settings'));

    $this->assertSame('new.admin@alice.local', $this->admin->fresh()->email);
});

test('a password change requires the current password and works afterwards', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'EvenBetter456!',
            'password_confirmation' => 'EvenBetter456!',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($this->admin)
        ->post(route('admin.settings.password'), [
            'current_password' => 'Secret123!',
            'password' => 'EvenBetter456!',
            'password_confirmation' => 'EvenBetter456!',
        ])
        ->assertRedirect(route('admin.settings'));

    $this->assertTrue(Hash::check('EvenBetter456!', $this->admin->fresh()->password));
});

test('the admin dropdown links to settings', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.settings'), false);
});
