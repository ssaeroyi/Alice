<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function settingsStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'Rusty',
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
        'total_keys' => 7,
        'current_streak' => 3,
        'longest_streak' => 5,
        'streak_freezes' => 1,
        'points' => 120,
        ...$overrides,
    ]);
}

test('guests are redirected from settings', function () {
    $this->get(route('settings'))->assertRedirect(route('login'));
});

test('settings page renders the audio section with a working sound toggle', function () {
    $this->actingAs(settingsStudent())
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('AUDIO')
        ->assertSee('data-sound-toggle')
        ->assertSee('Sound Effects');
});

test('settings page renders the sectioned profile dropdown', function () {
    $this->actingAs(settingsStudent())
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('My Progress')
        ->assertSee('My Collection')
        ->assertSee('My Badges')
        ->assertSee('Settings')
        ->assertSee('Logout');
});

test('settings page renders the avatar picker with the vendored catalog', function () {
    $this->actingAs(settingsStudent())
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('AVATAR')
        ->assertSee('Choose your avatar')
        ->assertSee('data-modal-open="avatar-modal"', false);

    foreach (config('avatars', []) as $av) {
        $this->get(route('settings'))
            ->assertSee(asset('image/avatar/'.$av['file']));
    }
});

test('a user can update their avatar to any catalog option', function () {
    $student = settingsStudent();

    $this->actingAs($student)
        ->post(route('settings.avatar'), ['avatar' => 'fox.png'])
        ->assertRedirect(route('settings'))
        ->assertSessionHas('toast');

    expect($student->fresh()->avatar)->toBe('fox.png');
});

test('an invalid avatar value is rejected', function () {
    $student = settingsStudent();

    $this->actingAs($student)
        ->post(route('settings.avatar'), ['avatar' => '../../etc/passwd.jpg'])
        ->assertSessionHasErrors('avatar');

    expect($student->fresh()->avatar)->toBeNull();
});
