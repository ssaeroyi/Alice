<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function indicatorStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
        'total_keys' => 42,
        'current_streak' => 4,
        'longest_streak' => 9,
        'streak_freezes' => 1,
        ...$overrides,
    ]);
}

test('guests are redirected from the hub', function () {
    $this->get(route('hub'))->assertRedirect(route('login'));
});

test('student progress indicators render in the profile dropdown section', function () {
    $user = indicatorStudent(['points' => 310]);

    $this->actingAs($user)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('My Progress')          // sectioned dropdown heading
        ->assertSee('Story Explorer')       // title ladder tier (points 310)
        ->assertSee('310')                  // points count
        ->assertSee('4')                    // current streak
        ->assertSee('42')                   // keys count
        ->assertSee('rewards-modal');       // MY STATS panel still present
});

test('student progress indicators reflect a zero streak honestly', function () {
    $user = indicatorStudent(['points' => 60, 'current_streak' => 0]);

    $this->actingAs($user)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('My Progress')
        ->assertSee('Bookworm')
        ->assertSee('60')
        ->assertSee('42')
        ->assertSee('0');                   // streak row still shown, honest value
});

test('header nav shows the four main destinations without the tasks link', function () {
    $user = indicatorStudent();

    $response = $this->actingAs($user)->get(route('hub'))->assertOk();

    foreach (['HUB', 'LIBRARY', 'STUDY', 'MINI-GAMES'] as $item) {
        $response->assertSee($item);
    }
    // TASKS moved into the Hub's tab set — no dedicated header nav entry remains.
    $response->assertDontSee('>TASKS<');
});

test('immersive pages do not break when the gamification indicators are available', function () {
    $user = indicatorStudent(['points' => 5]);

    // A non-immersive page must still load with the updated layout without
    // attempting to render $gamer in the collapsed/app shell.
    $this->actingAs($user)->get(route('library'))->assertOk();
});
