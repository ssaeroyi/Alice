<?php

use App\Enums\Role;
use App\Mail\AccountDeletionRequested;
use App\Mail\AccountRestored;
use App\Models\GameRound;
use App\Models\SavedWord;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function deletionStudent(): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'diver-one',
        'first_name' => 'Rita',
        'last_name' => 'Torres',
        'email' => 'rita.torres@example.com',
        'password' => 'password',
        'is_setup_complete' => true,
    ]);
}

test('a student can request deletion and personal data is scrambled', function () {
    Mail::fake();
    $student = deletionStudent();
    $this->actingAs($student);

    DB::table('sessions')->insert([
        'id' => str_repeat('d', 40),
        'user_id' => $student->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'empty',
        'last_activity' => now()->timestamp,
    ]);

    $this->post(route('settings.delete.request'), [
        'password' => 'password',
        'nickname_confirm' => 'diver-one',
    ])->assertOk()
        ->assertSee('Deletion Scheduled');

    Mail::assertSent(AccountDeletionRequested::class, fn (AccountDeletionRequested $mail) => $mail->hasTo('rita.torres@example.com'));

    $fresh = $student->fresh();

    expect($fresh->deleted_at)->not->toBeNull()
        ->and($fresh->deletion_requested_at)->not->toBeNull()
        ->and($fresh->email)->toBe('deleted-'.$fresh->id.'@invalid')
        ->and($fresh->nickname)->toBeNull()
        ->and($fresh->first_name)->toBeNull()
        ->and($fresh->last_name)->toBeNull();

    expect(DB::table('sessions')->where('user_id', $student->id)->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deletion_requested',
        'target_type' => 'user',
        'target_id' => $fresh->id,
    ]);
});

test('a student can cancel deletion and regain access', function () {
    Mail::fake();
    $student = deletionStudent();
    $this->actingAs($student);

    $this->post(route('settings.delete.request'), [
        'password' => 'password',
        'nickname_confirm' => 'diver-one',
    ])->assertOk();

    $this->post(route('settings.delete.cancel'), [
        'password' => 'password',
        'email' => 'rita.back@example.com',
    ])->assertRedirect();

    Mail::assertSent(AccountRestored::class, fn (AccountRestored $mail) => $mail->hasTo('rita.back@example.com'));

    $fresh = $student->fresh();

    expect($fresh->deleted_at)->toBeNull()
        ->and($fresh->deletion_requested_at)->toBeNull()
        ->and($fresh->email)->toBe('rita.back@example.com');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deletion_cancelled',
        'target_id' => $fresh->id,
    ]);
});

test('the original email is freed for reuse immediately after a deletion request', function () {
    $student = deletionStudent();
    $this->actingAs($student);

    $this->post(route('settings.delete.request'), [
        'password' => 'password',
        'nickname_confirm' => 'diver-one',
    ])->assertOk();

    $reuse = User::factory()->create([
        'role' => Role::Student,
        'email' => 'rita.torres@example.com',
    ]);

    expect($reuse->email)->toBe('rita.torres@example.com');
});

test('cancellation refuses an email in use by another account', function () {
    $student = deletionStudent();
    $this->actingAs($student);

    User::factory()->create(['role' => Role::Student, 'email' => 'taken@example.com']);

    $this->post(route('settings.delete.request'), [
        'password' => 'password',
        'nickname_confirm' => 'diver-one',
    ])->assertOk();

    $this->post(route('settings.delete.cancel'), [
        'password' => 'password',
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');

    expect($student->fresh()->deleted_at)->not->toBeNull();
});

test('a deletion request requires the correct password and nickname', function () {
    $student = deletionStudent();
    $this->actingAs($student);

    $this->post(route('settings.delete.request'), [
        'password' => 'wrong-password',
        'nickname_confirm' => 'diver-one',
    ])->assertSessionHasErrors('password');

    $this->post(route('settings.delete.request'), [
        'password' => 'password',
        'nickname_confirm' => 'not-my-name',
    ])->assertSessionHasErrors('nickname_confirm');

    expect($student->fresh()->deleted_at)->toBeNull();
});

test('a deleted user cannot log in and is routed into the restore funnel', function () {
    $student = deletionStudent();
    $student->forceFill(['deleted_at' => now(), 'deletion_requested_at' => now()])->save();

    $this->post('/login', [
        'email' => 'rita.torres@example.com',
        'password' => 'password',
    ])->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

test('GET on the student delete endpoints redirects instead of a 405', function () {
    $student = deletionStudent();
    $this->actingAs($student);

    $this->get('/settings/delete/request')->assertRedirect(route('settings'));
    $this->get('/settings/delete/cancel')->assertRedirect(route('settings'));
});

test('GET on the teacher delete endpoints redirects instead of a 405', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $this->actingAs($teacher);

    $this->get('/teacher/settings/delete/request')->assertRedirect(route('teacher.settings'));
    $this->get('/teacher/settings/delete/cancel')->assertRedirect(route('teacher.settings'));
});

test('an expired deletion is hard-purged and the account row is removed', function () {
    $student = deletionStudent();
    $student->forceFill(['deleted_at' => now()->subDays(8), 'deletion_requested_at' => now()->subDays(8)])->save();
    $id = $student->id;

    GameRound::create(['user_id' => $id, 'game_type' => 'wordsearch', 'score' => 100, 'round_id' => (string) Str::uuid()]);
    SavedWord::create(['user_id' => $id, 'word' => 'serendipity']);

    $service = app(AccountDeletionService::class);

    $purged = $service->purgeExpired();

    expect($purged)->toBe(1)
        ->and(User::find($id))->toBeNull()
        ->and(GameRound::where('user_id', $id)->count())->toBe(0)
        ->and(SavedWord::where('user_id', $id)->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deleted',
        'target_id' => $id,
    ]);
});

test('teachers and admins are never hard-deleted by the purge', function () {
    $teacher = User::factory()->create([
        'role' => Role::Teacher,
        'deleted_at' => now()->subDays(20),
        'deletion_requested_at' => now()->subDays(20),
    ]);
    $admin = User::factory()->create([
        'role' => Role::Admin,
        'deleted_at' => now()->subDays(20),
        'deletion_requested_at' => now()->subDays(20),
    ]);

    $service = app(AccountDeletionService::class);

    $purged = $service->purgeExpired();

    expect($purged)->toBe(0)
        ->and(User::find($teacher->id))->not->toBeNull()
        ->and(User::find($admin->id))->not->toBeNull();
});

test('an admin can restore a pending-deletion account', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = deletionStudent();
    $student->forceFill(['deleted_at' => now(), 'deletion_requested_at' => now()])->save();

    $this->actingAs($admin)
        ->post(route('admin.accounts.restore', $student), [
            'email' => 'rita.back@example.com',
        ])->assertRedirect();

    Mail::assertSent(AccountRestored::class, fn (AccountRestored $mail) => $mail->hasTo('rita.back@example.com'));

    $fresh = $student->fresh();

    expect($fresh->deleted_at)->toBeNull()
        ->and($fresh->email)->toBe('rita.back@example.com');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.restored',
        'target_id' => $fresh->id,
    ]);
});

test('admin restore refuses an email already in use', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = deletionStudent();
    $student->forceFill(['deleted_at' => now(), 'deletion_requested_at' => now()])->save();

    User::factory()->create(['role' => Role::Student, 'email' => 'taken@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.accounts.restore', $student), [
            'email' => 'taken@example.com',
        ])->assertSessionHasErrors('email');

    expect($student->fresh()->deleted_at)->not->toBeNull();
});

test('the accounts index shows deletion-pending accounts via the filter', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $pending = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'pending-delete',
        'deleted_at' => now(),
        'deletion_requested_at' => now(),
    ]);
    $active = User::factory()->create(['role' => Role::Student, 'nickname' => 'still-here']);

    $this->actingAs($admin)
        ->get(route('admin.accounts.index', ['status' => 'deletion-pending']))
        ->assertOk()
        ->assertSee('pending-delete')
        ->assertSee('Deletion pending')
        ->assertDontSee('still-here');
});

test('a teacher can request deletion', function () {
    Mail::fake();
    $teacher = User::factory()->create([
        'role' => Role::Teacher,
        'nickname' => 'teacher-maria',
        'email' => 'teacher.maria@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($teacher);

    $this->post(route('teacher.settings.delete.request'), [
        'password' => 'password',
        'nickname_confirm' => 'teacher-maria',
    ])->assertOk();

    Mail::assertSent(AccountDeletionRequested::class, fn (AccountDeletionRequested $mail) => $mail->hasTo('teacher.maria@example.com'));

    $fresh = $teacher->fresh();

    expect($fresh->deleted_at)->not->toBeNull()
        ->and($fresh->email)->toBe('deleted-'.$fresh->id.'@invalid');
});

test('a teacher cannot be hard-deleted but can be restored by an admin', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $teacher = User::factory()->create([
        'role' => Role::Teacher,
        'nickname' => 'teacher-rosa',
        'deleted_at' => now()->subDays(15),
        'deletion_requested_at' => now()->subDays(15),
    ]);
    $teacher->forceFill(['email' => 'deleted-'.$teacher->id.'@invalid'])->save();
    $id = $teacher->id;

    $service = app(AccountDeletionService::class);

    expect($service->purgeExpired())->toBe(0);
    expect(User::find($id))->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.accounts.restore', $teacher), [
            'email' => 'rosa.new@example.com',
        ])->assertRedirect();

    expect($teacher->fresh()->deleted_at)->toBeNull()
        ->and($teacher->fresh()->email)->toBe('rosa.new@example.com');
});
