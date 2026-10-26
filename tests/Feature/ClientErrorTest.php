<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\ClientError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the client error endpoint stores sanitized batches', function () {
    $this->post(route('client-error.store'), [
        'errors' => [
            ['message' => 'Cannot read properties of undefined (reading "map")', 'url' => 'https://alice.test/library', 'stack' => 'TypeError at Library.vue:12'],
            ['message' => 'Failed to fetch /api/story', 'url' => 'https://alice.test/word-gym'],
        ],
    ])->assertNoContent();

    expect(ClientError::count())->toBe(2);
    expect(ClientError::first()->message)->toBe('Cannot read properties of undefined (reading "map")');
    expect(ClientError::first()->user_id)->toBeNull();
    expect(ClientError::latest('id')->first()->stack)->toBeNull();
});

test('the client error endpoint records the signed-in user', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)->post(route('client-error.store'), [
        'errors' => [['message' => 'boom', 'url' => '/hub']],
    ])->assertNoContent();

    expect(ClientError::first()->user_id)->toBe($student->id);
});

test('the client error endpoint rejects invalid payloads', function () {
    $this->post(route('client-error.store'), ['errors' => [['url' => '/x']]])->assertSessionHasErrors();
    $this->post(route('client-error.store'), ['errors' => [['message' => str_repeat('x', 2001)]]])->assertSessionHasErrors();

    expect(ClientError::count())->toBe(0);
});

test('client errors appear on the status page with a hint and account link', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student, 'nickname' => 'pearl-diver']);

    ClientError::create([
        'user_id' => $student->id,
        'message' => 'Cannot read properties of undefined',
        'url' => 'https://alice.test/hub',
        'user_agent' => 'Mozilla/5.0 (iPhone)',
        'created_at' => now(),
    ]);
    ClientError::create([
        'user_id' => null,
        'message' => 'Failed to load resource: https://cdn.example.com/app.js',
        'url' => 'https://alice.test/library',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Client errors')
        ->assertSee('Cannot read properties of undefined')
        ->assertSee('pearl-diver')
        ->assertSee('Review this account')
        ->assertSee('Failed to load resource')
        ->assertSee('A network or resource problem')
        ->assertSee('2 reported errors in the last 24 hours');
});

test('the status client error panel is admin-only', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->post(route('admin.status.client-errors.clear'))
        ->assertForbidden();
});

test('clearing client errors removes them and is audited', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    ClientError::create(['message' => 'one', 'url' => '/hub', 'created_at' => now()]);
    ClientError::create(['message' => 'two', 'url' => '/library', 'created_at' => now()]);

    $this->actingAs($admin)
        ->post(route('admin.status.client-errors.clear'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'Removed 2 reported errors'));

    expect(ClientError::count())->toBe(0);
    expect(AuditLog::where('action', 'system.client_errors_cleared')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('friendly error pages render for students', function () {
    $this->get('/this-page-does-not-exist')->assertNotFound()->assertSee('Page not found')->assertSee('BACK TO HOME');

    expect(view('errors.419')->render())->toContain('Page expired')->toContain('SIGN IN AGAIN');
    expect(view('errors.500')->render())->toContain('Something went wrong')->toContain('GO BACK');
});
