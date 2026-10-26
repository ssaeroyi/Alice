<?php

use App\Enums\Role;
use App\Mail\AccountRestored;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function pendingDeletionStudent(): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'diver-one',
        'email' => 'deleted-99@invalid',
        'password' => 'password',
        'is_setup_complete' => true,
        'deleted_at' => now(),
        'deletion_requested_at' => now(),
    ]);
}

test('a login with the password of a scheduled-deletion account offers restore instead of a generic error', function () {
    $student = pendingDeletionStudent();

    $this->post('/login', [
        'email' => 'rita.torres@example.com',
        'password' => 'password',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('deletion_pending');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('class="flex fixed inset-0', false);

    expect(auth()->check())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.deletion_restore_offered',
        'target_id' => $student->id,
    ]);
});

test('a scheduled-deletion account can be restored through the guest funnel', function () {
    Mail::fake();
    $student = pendingDeletionStudent();

    $this->post(route('login.restore'), [
        'email' => 'rita.back@example.com',
        'password' => 'password',
    ])->assertRedirect(route('hub'));

    $this->assertAuthenticatedAs($student);

    $fresh = $student->fresh();

    expect($fresh->deleted_at)->toBeNull()
        ->and($fresh->deletion_requested_at)->toBeNull()
        ->and($fresh->email)->toBe('rita.back@example.com')
        ->and($fresh->last_login_at)->not->toBeNull();

    Mail::assertSent(AccountRestored::class, fn (AccountRestored $mail) => $mail->hasTo('rita.back@example.com'));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deletion_cancelled',
        'target_id' => $fresh->id,
    ]);
});

test('restore refuses an email already in use by another account', function () {
    $student = pendingDeletionStudent();
    User::factory()->create(['role' => Role::Student, 'email' => 'taken@example.com']);

    $this->post(route('login.restore'), [
        'email' => 'taken@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse()
        ->and($student->fresh()->deleted_at)->not->toBeNull();
});

test('restore requires the correct password', function () {
    $student = pendingDeletionStudent();

    $this->post(route('login.restore'), [
        'email' => 'rita.back@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse()
        ->and($student->fresh()->deleted_at)->not->toBeNull();
});

test('an unknown account still gets the generic login error and no funnel', function () {
    $this->post('/login', [
        'email' => 'never.registered@example.com',
        'password' => 'whatever',
    ])->assertSessionHasErrors('email')
        ->assertSessionMissing('deletion_pending');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('class="hidden fixed inset-0', false);
});

test('GET on the guest restore endpoint redirects instead of a 405', function () {
    $this->get('/login/restore')->assertRedirect(route('login'));
});

test('a guest GET on the delete endpoints is sent to login, not a 405', function () {
    $this->get('/settings/delete/request')->assertRedirect(route('login'));
    $this->get('/settings/delete/cancel')->assertRedirect(route('login'));
});
