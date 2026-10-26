<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activityTeacher(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

test('telemetry attributes signed-in requests to the user', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)->get(route('hub'));

    $this->assertDatabaseHas('request_logs', [
        'user_id' => $student->id,
        'path' => 'hub',
    ]);
});

test('telemetry leaves guest requests unattributed', function () {
    $this->get(route('login'));

    $this->assertDatabaseHas('request_logs', [
        'user_id' => null,
        'path' => 'login',
    ]);
});

test('student detail shows the recent activity panel', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)->get(route('hub'));
    $this->actingAs($student)->get(route('settings'));

    $this->actingAs(activityTeacher())
        ->get(route('teacher.student', $student))
        ->assertOk()
        ->assertSee('Recent activity')
        ->assertSee('opened the Hub')
        ->assertSee('opened Settings');
});
