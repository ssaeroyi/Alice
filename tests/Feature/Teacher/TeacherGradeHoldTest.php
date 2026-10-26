<?php

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Models\User;
use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function holdGradeActor(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected to login when holding a grade', function () {
    $student = User::factory()->create(['role' => Role::Student, 'grade_level' => GradeLevel::Grade8]);

    $this->post(route('teacher.students.hold-grade', $student), [], ['Accept' => 'text/html'])
        ->assertRedirect(route('login'));
});

test('students are forbidden from holding a grade', function () {
    $student = User::factory()->create(['role' => Role::Student, 'grade_level' => GradeLevel::Grade8]);
    $other = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($other)->post(route('teacher.students.hold-grade', $student))->assertForbidden();
});

test('holding a grade re-anchors it to the current school year', function () {
    Carbon::setTestNow('2026-06-01');
    $student = User::factory()->create([
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => 2025,
    ]);

    expect($student->effectiveGrade())->toBe(9);

    $this->actingAs(holdGradeActor())
        ->post(route('teacher.students.hold-grade', $student))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($student->fresh()->grade_anchor_year)->toBe(SchoolYear::startYear());
    expect($student->fresh()->effectiveGrade())->toBe(8);
});

test('admins can hold a grade too', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade9,
        'grade_anchor_year' => 2024,
    ]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->post(route('teacher.students.hold-grade', $student))
        ->assertRedirect();

    expect($student->fresh()->grade_anchor_year)->toBe(SchoolYear::startYear());
});

test('holding a grade does nothing for an unset grade', function () {
    $student = User::factory()->create(['role' => Role::Student, 'grade_level' => null]);

    $this->actingAs(holdGradeActor())
        ->post(route('teacher.students.hold-grade', $student))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($student->fresh()->grade_anchor_year)->toBeNull();
});

test('holding a grade on a non-student returns not found', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs(holdGradeActor())
        ->post(route('teacher.students.hold-grade', $teacher))
        ->assertNotFound();
});
