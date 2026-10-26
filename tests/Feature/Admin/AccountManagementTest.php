<?php

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Mail\AccountDeletionRequested;
use App\Mail\TeacherInvitation;
use App\Models\AuditLog;
use App\Models\ConsentLog;
use App\Models\User;
use App\Notifications\ResetPassword;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function actingAdmin(): User
{
    $admin = User::factory()->create(['role' => Role::Admin]);

    test()->actingAs($admin);

    return $admin;
}

test('the accounts index lists accounts and respects role filters', function () {
    actingAdmin();

    User::factory()->create(['nickname' => 'alice-sky', 'role' => Role::Student]);
    User::factory()->create(['nickname' => 'teacher-maria', 'role' => Role::Teacher]);
    $admin = User::factory()->create(['nickname' => 'root-admin', 'role' => Role::Admin]);

    $this->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSee('alice-sky')
        ->assertSee('teacher-maria')
        ->assertSee('root-admin');

    $this->get(route('admin.accounts.index', ['role' => 'teacher']))
        ->assertOk()
        ->assertSee('teacher-maria')
        ->assertDontSee('alice-sky')
        ->assertDontSee('root-admin');
});

test('the accounts index sorts by nickname and recency through the sort control', function () {
    actingAdmin();

    User::factory()->create(['nickname' => 'zeta-user', 'role' => Role::Student]);
    User::factory()->create(['nickname' => 'alpha-user', 'role' => Role::Student]);

    $this->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSee('name="sort"', false)
        ->assertSee('Nickname A–Z');

    $html = $this->get(route('admin.accounts.index', ['sort' => 'name_asc']))
        ->getContent();

    expect(strpos($html, 'alpha-user'))->toBeLessThan(strpos($html, 'zeta-user'));

    $html = $this->get(route('admin.accounts.index', ['sort' => 'name_desc']))
        ->getContent();

    expect(strpos($html, 'zeta-user'))->toBeLessThan(strpos($html, 'alpha-user'));

    $this->get(route('admin.accounts.index', ['sort' => 'bogus']))
        ->assertOk()
        ->assertSee('Newest first');
});

test('the accounts index searches by nickname and email', function () {
    actingAdmin();

    User::factory()->create(['nickname' => 'shooting-star', 'email' => 'star@example.com']);
    User::factory()->create(['nickname' => 'cloud-nine', 'email' => 'other@example.com']);

    $this->get(route('admin.accounts.index', ['q' => 'shooting']))
        ->assertOk()
        ->assertSee('shooting-star')
        ->assertDontSee('cloud-nine');

    $this->get(route('admin.accounts.index', ['q' => 'other@example.com']))
        ->assertOk()
        ->assertSee('cloud-nine')
        ->assertDontSee('shooting-star');
});

test('the accounts index filters by grade, status and setup', function () {
    actingAdmin();

    $grade9 = User::factory()->create([
        'nickname' => 'grade-nine',
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade9,
        'reading_level' => 6,
        'is_setup_complete' => true,
    ]);
    $pending = User::factory()->create([
        'nickname' => 'no-setup',
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade7,
        'reading_level' => 3,
        'is_setup_complete' => false,
    ]);
    $suspended = User::factory()->create([
        'nickname' => 'locked-out',
        'role' => Role::Student,
        'suspended_at' => now(),
    ]);

    $this->get(route('admin.accounts.index', ['grade' => '9']))
        ->assertOk()
        ->assertSee('grade-nine')
        ->assertDontSee('no-setup');

    $this->get(route('admin.accounts.index', ['status' => 'suspended']))
        ->assertOk()
        ->assertSee('locked-out')
        ->assertDontSee('grade-nine');

    $this->get(route('admin.accounts.index', ['setup' => 'pending']))
        ->assertOk()
        ->assertSee('no-setup')
        ->assertDontSee('grade-nine');
});

test('the account detail page shows facts, sessions, consents and audit', function () {
    actingAdmin();

    $student = User::factory()->create([
        'nickname' => 'pearl-diver',
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade8,
        'reading_level' => 5,
        'is_setup_complete' => true,
        'last_login_at' => now()->subDay(),
    ]);

    ConsentLog::create([
        'user_id' => $student->id,
        'type' => 'privacy',
        'document_version' => '2026-01',
        'ip_address' => '127.0.0.1',
        'accepted_at' => now(),
    ]);

    Audit::log('user.suspended', $student, ['reason' => 'review']);

    $this->get(route('admin.accounts.show', $student))
        ->assertOk()
        ->assertSee('pearl-diver')
        ->assertSee('Grade 8')
        ->assertSee('Level 5')
        ->assertSee('privacy')
        ->assertSee('user.suspended')
        ->assertSee('Complete');
});

test('an admin can create a teacher account and an invitation email is sent', function () {
    actingAdmin();

    Mail::fake();

    $this->post(route('admin.accounts.store'), [
        'nickname' => 'teacher-maria',
        'email' => 'maria.lopez@example.com',
        'password' => 'Temporary-pass1!',
    ])->assertRedirect();

    $teacher = User::where('email', 'maria.lopez@example.com')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher->role)->toBe(Role::Teacher);
    expect($teacher->is_setup_complete)->toBeTrue();
    expect($teacher->first_name)->toBeNull();
    expect($teacher->last_name)->toBeNull();

    Mail::assertSent(TeacherInvitation::class, function (TeacherInvitation $mail) {
        return $mail->hasTo('maria.lopez@example.com')
            && $mail->password === 'Temporary-pass1!'
            && $mail->user->email === 'maria.lopez@example.com';
    });

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.created',
        'target_type' => 'user',
        'target_id' => $teacher->id,
    ]);

    $this->get(route('admin.accounts.show', $teacher))
        ->assertOk()
        ->assertSee('maria.lopez@example.com');
});

test('creating a teacher with an existing email is rejected with a role-aware message', function () {
    actingAdmin();

    User::factory()->create([
        'nickname' => 'student-maya',
        'role' => Role::Student,
        'email' => 'taken@example.com',
    ]);

    $this->from(route('admin.accounts.create'))
        ->post(route('admin.accounts.store'), [
            'nickname' => 'teacher-maria',
            'email' => 'taken@example.com',
            'password' => 'Temporary-pass1!',
        ])
        ->assertSessionHasErrors(['email' => 'An account with this email already exists. If this person is a student, change their role from their account page instead.']);

    expect(User::where('email', 'taken@example.com')->count())->toBe(1);
    expect(User::where('nickname', 'teacher-maria')->exists())->toBeFalse();
});

test('creating a teacher with a taken or invalid username is rejected', function () {
    actingAdmin();

    User::factory()->create(['nickname' => 'taken-name']);

    $this->from(route('admin.accounts.create'))
        ->post(route('admin.accounts.store'), [
            'nickname' => 'taken-name',
            'email' => 'new.teacher@example.com',
            'password' => 'Temporary-pass1!',
        ])
        ->assertSessionHasErrors('nickname');

    $this->post(route('admin.accounts.store'), [
        'nickname' => 'bad name!',
        'email' => 'another.teacher@example.com',
        'password' => 'Temporary-pass1!',
    ])->assertSessionHasErrors('nickname');
});

test('suspending a user locks the account and is audited', function () {
    actingAdmin();

    $student = User::factory()->create([
        'nickname' => 'rule-bender',
        'role' => Role::Student,
    ]);

    DB::table('sessions')->insert([
        ['id' => str_repeat('s', 40), 'user_id' => $student->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'empty', 'last_activity' => now()->timestamp],
        ['id' => str_repeat('t', 40), 'user_id' => $student->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'empty', 'last_activity' => now()->timestamp],
    ]);

    $this->post(route('admin.accounts.suspend', $student), [
        'reason' => 'Repeated rule breaking',
    ])->assertRedirect()
        ->assertSessionHas('toast.type', 'success')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, '2 active sessions signed out immediately'));

    expect($student->fresh()->suspended_at)->not->toBeNull();
    expect(DB::table('sessions')->where('user_id', $student->id)->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.suspended',
        'target_type' => 'user',
        'target_id' => $student->id,
    ]);

    $entry = AuditLog::where('action', 'user.suspended')->where('target_id', $student->id)->first();

    expect($entry->meta['reason'])->toBe('Repeated rule breaking');

    $this->post('/logout');
    $this->post('/login', [
        'email' => $student->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('suspension requires a reason', function () {
    actingAdmin();

    $student = User::factory()->create(['role' => Role::Student]);

    $this->post(route('admin.accounts.suspend', $student), [
        'reason' => '',
    ])->assertSessionHasErrors('reason');

    expect($student->fresh()->suspended_at)->toBeNull();
});

test('an admin cannot suspend their own account', function () {
    $admin = actingAdmin();

    $this->post(route('admin.accounts.suspend', $admin), [
        'reason' => 'oops',
    ])->assertRedirect();

    expect($admin->fresh()->suspended_at)->toBeNull();
});

test('reactivating a suspended user is audited', function () {
    actingAdmin();

    $student = User::factory()->create([
        'role' => Role::Student,
        'suspended_at' => now()->subDay(),
    ]);

    $this->post(route('admin.accounts.reactivate', $student))->assertRedirect();

    expect($student->fresh()->suspended_at)->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.reactivated',
        'target_type' => 'user',
        'target_id' => $student->id,
    ]);
});

test('a reset link can be sent by an admin', function () {
    actingAdmin();

    Notification::fake();

    $student = User::factory()->create(['role' => Role::Student]);

    $this->post(route('admin.accounts.reset-link', $student))->assertRedirect();

    Notification::assertSentTo($student, ResetPassword::class);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.reset_link',
        'target_type' => 'user',
        'target_id' => $student->id,
    ]);
});

test('role changes are audited and self-role-change is blocked', function () {
    $admin = actingAdmin();

    $student = User::factory()->create(['role' => Role::Student]);

    $this->post(route('admin.accounts.role', $student), ['role' => 'teacher'])
        ->assertRedirect();

    expect($student->fresh()->role)->toBe(Role::Teacher);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.role_changed',
        'target_type' => 'user',
        'target_id' => $student->id,
    ]);

    $this->post(route('admin.accounts.role', $admin), ['role' => 'student'])->assertRedirect();

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('admin role transitions are blocked in the web ui and must go through the cli', function () {
    $admin = actingAdmin();

    $otherAdmin = User::factory()->create(['role' => Role::Admin]);

    $this->post(route('admin.accounts.role', $otherAdmin), ['role' => 'student'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error');

    expect($otherAdmin->fresh()->role)->toBe(Role::Admin);

    $this->post(route('admin.accounts.role', $otherAdmin), ['role' => 'student'])->assertRedirect();

    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'user.role_changed',
        'target_type' => 'user',
        'target_id' => $otherAdmin->id,
    ]);

    $student = User::factory()->create(['role' => Role::Student]);

    $this->post(route('admin.accounts.role', $student), ['role' => 'admin'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error');

    expect($student->fresh()->role)->toBe(Role::Student);

    $this->artisan('admin:manage', ['action' => 'set-role', 'email' => $student->email, 'role' => 'admin'])
        ->assertSuccessful();

    expect($student->fresh()->role)->toBe(Role::Admin);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.role_changed',
        'target_type' => 'user',
        'target_id' => $student->id,
    ]);
});

test('the cli set-role refuses invalid roles and the last admin demotion', function () {
    $admin = actingAdmin();
    $student = User::factory()->create(['role' => Role::Student]);

    $this->artisan('admin:manage', ['action' => 'set-role', 'email' => $student->email, 'role' => 'superuser'])
        ->assertFailed();

    expect($student->fresh()->role)->toBe(Role::Student);

    $this->artisan('admin:manage', ['action' => 'set-role', 'email' => $admin->email, 'role' => 'teacher'])
        ->assertFailed();

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('an admin viewing their own account sees a cli notice instead of action buttons', function () {
    $admin = actingAdmin();

    $this->get(route('admin.accounts.show', $admin))
        ->assertOk()
        ->assertSee('This is your own account')
        ->assertSee('php artisan admin:manage')
        ->assertDontSee('Suspend account')
        ->assertDontSee('Change role')
        ->assertDontSee('Send reset link')
        ->assertDontSee('Sign out all sessions');
});

test('an admin viewing another account still sees the action buttons', function () {
    $admin = actingAdmin();
    $student = User::factory()->create(['role' => Role::Student]);

    $this->get(route('admin.accounts.show', $student))
        ->assertOk()
        ->assertDontSee('This is your own account')
        ->assertSee('Suspend account')
        ->assertSee('Change role');
});

test('signing out all sessions removes the users sessions and is audited', function () {
    actingAdmin();

    $student = User::factory()->create(['role' => Role::Student]);

    DB::table('sessions')->insert([
        'id' => str_repeat('a', 40),
        'user_id' => $student->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'empty',
        'last_activity' => now()->timestamp,
    ]);

    $this->post(route('admin.accounts.sign-out', $student))->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $student->id)->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.signed_out',
        'target_type' => 'user',
        'target_id' => $student->id,
    ]);
});

test('accounts routes are admin-only', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.accounts.index'))
        ->assertForbidden();

    $this->actingAs($student)
        ->get(route('admin.accounts.show', $student))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('admin.accounts.suspend', $student), ['reason' => 'x'])
        ->assertForbidden();
});

test('the csv export downloads the filtered accounts and is audited', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'Riz, Jr',
        'email' => 'riz@example.org',
        'grade_level' => GradeLevel::Grade7,
        'reading_level' => 7,
        'is_setup_complete' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.accounts.export', ['role' => 'student']))
        ->assertOk()
        ->assertDownload();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('Nickname,Email,"First name","Last name",Role,Grade,"Reading level",Status,Joined,"Last login"')
        ->and($content)->toContain('"Riz, Jr",riz@example.org')
        ->and($content)->toContain('"Grade 7"')
        ->and($content)->toContain('"Level 7"')
        ->and($content)->toContain('Active');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.exported_csv',
        'actor_id' => $admin->id,
    ]);
});

test('the csv export respects the current filters', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $suspended = User::factory()->create(['role' => Role::Student, 'suspended_at' => now()]);
    $active = User::factory()->create(['role' => Role::Student]);

    $content = $this->actingAs($admin)
        ->get(route('admin.accounts.export', ['status' => 'suspended']))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain($suspended->email)
        ->and($content)->not->toContain($active->email)
        ->and($content)->not->toContain($teacher->email);
});

test('an admin can schedule a student for deletion, which scrambles them and is audited', function () {
    $admin = actingAdmin();
    Mail::fake();

    $student = User::factory()->create([
        'nickname' => 'goodbye-kid',
        'email' => 'kid@example.org',
        'role' => Role::Student,
    ]);

    DB::table('sessions')->insert([
        ['id' => str_repeat('d', 40), 'user_id' => $student->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'empty', 'last_activity' => now()->timestamp],
    ]);

    $this->post(route('admin.accounts.destroy', $student))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'scheduled for deletion'));

    $fresh = $student->fresh();

    expect($fresh->deleted_at)->not->toBeNull();
    expect($fresh->email)->toBe('deleted-'.$student->id.'@invalid');
    expect($fresh->nickname)->toBeNull();
    expect(DB::table('sessions')->where('user_id', $student->id)->count())->toBe(0);

    $entry = AuditLog::where('action', 'user.deletion_requested')->where('target_id', $student->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->meta['initiated_by'] ?? null)->toBe('admin');
    expect($entry->actor_id)->toBe($admin->id);

    Mail::assertSent(AccountDeletionRequested::class, fn ($mail) => $mail->hasTo('kid@example.org'));
});

test('an admin cannot schedule their own account or another admin for deletion', function () {
    $admin = actingAdmin();
    $otherAdmin = User::factory()->create(['role' => Role::Admin]);

    $this->post(route('admin.accounts.destroy', $admin))
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'cannot delete your own'));

    $this->post(route('admin.accounts.destroy', $otherAdmin))
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'Administrator accounts cannot be deleted'));

    expect($admin->fresh()->deleted_at)->toBeNull();
    expect($otherAdmin->fresh()->deleted_at)->toBeNull();
});

test('scheduling an already-pending account for deletion is refused', function () {
    $admin = actingAdmin();

    $student = User::factory()->create(['role' => Role::Student, 'deleted_at' => now()]);

    $this->post(route('admin.accounts.destroy', $student))
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'already scheduled'));

    expect($student->fresh()->email)->not->toBe('deleted-'.$student->id.'@invalid');
});

test('the account page shows the delete button only for deletable accounts', function () {
    $admin = actingAdmin();

    $student = User::factory()->create(['role' => Role::Student]);
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $otherAdmin = User::factory()->create(['role' => Role::Admin]);
    $pending = User::factory()->create(['role' => Role::Student, 'deleted_at' => now()]);

    $this->get(route('admin.accounts.show', $student))
        ->assertOk()
        ->assertSee('Schedule deletion');

    $this->get(route('admin.accounts.show', $teacher))
        ->assertOk()
        ->assertSee('Schedule deletion');

    $this->get(route('admin.accounts.show', $otherAdmin))
        ->assertOk()
        ->assertDontSee('Schedule deletion');

    $this->get(route('admin.accounts.show', $pending))
        ->assertOk()
        ->assertDontSee('Schedule deletion');

    $this->get(route('admin.accounts.show', $admin))
        ->assertOk()
        ->assertDontSee('Schedule deletion');
});
