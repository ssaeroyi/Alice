<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function actingTeacher(array $attributes = []): User
{
    $teacher = User::factory()->create(array_merge([
        'role' => Role::Teacher,
        'is_setup_complete' => true,
        'password' => Hash::make('oldpassword'),
    ], $attributes));

    test()->actingAs($teacher);

    return $teacher;
}

test('a teacher can view the settings page', function () {
    $teacher = actingTeacher(['nickname' => 'teacher-maria']);

    expect($teacher->password_changed_at)->toBeNull();

    $this->get(route('teacher.settings'))
        ->assertOk()
        ->assertSee('teacher-maria')
        ->assertSee('Password & security')
        ->assertDontSee('Last changed');
});

test('the teacher settings page shows the label only after a real password change', function () {
    $teacher = actingTeacher(['nickname' => 'teacher-maria']);
    $teacher->password_changed_at = now();
    $teacher->save();

    $this->get(route('teacher.settings'))
        ->assertOk()
        ->assertSee('Last changed');
});

test('a teacher can update their profile', function () {
    $teacher = actingTeacher();

    $this->followingRedirects()
        ->put(route('teacher.settings.profile'), [
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'nickname' => 'teacher-maria',
        ])
        ->assertSee('Profile updated.');

    $teacher->refresh();

    expect($teacher->first_name)->toBe('Maria')
        ->and($teacher->last_name)->toBe('Lopez')
        ->and($teacher->nickname)->toBe('teacher-maria');
});

test('a teacher profile update rejects a taken nickname', function () {
    actingTeacher(['nickname' => 'teacher-maria']);

    User::factory()->create(['nickname' => 'taken-name', 'role' => Role::Teacher]);

    $this->put(route('teacher.settings.profile'), [
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'nickname' => 'taken-name',
    ])->assertSessionHasErrors('nickname');
});

test('saving the name from the name modal leaves the nickname untouched', function () {
    $teacher = actingTeacher([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'nickname' => 'teacher-maria',
    ]);

    $this->followingRedirects()
        ->put(route('teacher.settings.profile'), [
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
        ])
        ->assertSee('Profile updated.');

    $teacher->refresh();

    expect($teacher->first_name)->toBe('Maria')
        ->and($teacher->last_name)->toBe('Lopez')
        ->and($teacher->nickname)->toBe('teacher-maria');
});

test('saving the display name from its modal leaves first and last name untouched', function () {
    $teacher = actingTeacher([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'nickname' => 'old-nick',
    ]);

    $this->followingRedirects()
        ->put(route('teacher.settings.profile'), [
            'nickname' => 'new-nick',
        ])
        ->assertSee('Profile updated.');

    $teacher->refresh();

    expect($teacher->nickname)->toBe('new-nick')
        ->and($teacher->first_name)->toBe('Maria')
        ->and($teacher->last_name)->toBe('Lopez');
});

test('a teacher can change their password with the correct current password', function () {
    $teacher = actingTeacher();

    $this->followingRedirects()
        ->post(route('teacher.settings.password'), [
            'current_password' => 'oldpassword',
            'password' => 'Newpassword123!',
            'password_confirmation' => 'Newpassword123!',
        ])
        ->assertSee('Password updated.');

    $teacher->refresh();

    expect(Hash::check('Newpassword123!', $teacher->password))->toBeTrue()
        ->and($teacher->password_changed_at)->not->toBeNull();
});

test('a teacher password change rejects a wrong current password', function () {
    $teacher = actingTeacher();

    $this->post(route('teacher.settings.password'), [
        'current_password' => 'wrong-password',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('newpassword123', $teacher->fresh()->password))->toBeFalse();
});

test('a teacher password change rejects a short password', function () {
    actingTeacher();

    $this->post(route('teacher.settings.password'), [
        'current_password' => 'oldpassword',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('teacher settings are blocked for students', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
    ]);

    $this->actingAs($student)
        ->get(route('teacher.settings'))
        ->assertForbidden();
});

test('teacher settings redirect guests to login', function () {
    $this->get(route('teacher.settings'))->assertRedirect(route('login'));
});

test('teachers stay blocked from the student settings page', function () {
    actingTeacher();

    $this->get(route('settings'))
        ->assertForbidden();
});
