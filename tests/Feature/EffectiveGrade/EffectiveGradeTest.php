<?php

use App\Enums\GradeLevel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('grade stays the same within the same school year', function () {
    Carbon::setTestNow('2026-01-10');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => 2025,
    ]);

    expect($user->effectiveGrade())->toBe(8);
});

test('grade advances at the start of the next school year', function () {
    Carbon::setTestNow('2026-06-01');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => 2025,
    ]);

    expect($user->effectiveGrade())->toBe(9);
});

test('grade advances once per school year', function () {
    Carbon::setTestNow('2026-06-01');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade7,
        'grade_anchor_year' => 2024,
    ]);

    expect($user->effectiveGrade())->toBe(9);
});

test('grade is capped at grade 10', function () {
    Carbon::setTestNow('2026-06-01');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade9,
        'grade_anchor_year' => 2024,
    ]);

    expect($user->effectiveGrade())->toBe(10);

    $senior = User::factory()->create([
        'grade_level' => GradeLevel::Grade10,
        'grade_anchor_year' => 2020,
    ]);

    expect($senior->effectiveGrade())->toBe(10);
});

test('an anchor from the future does not lower the grade', function () {
    Carbon::setTestNow('2026-05-01');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => 2026,
    ]);

    expect($user->effectiveGrade())->toBe(8);
});

test('a held grade (re-anchored to this school year) stays after rollover', function () {
    Carbon::setTestNow('2026-06-01');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => 2026,
    ]);

    expect($user->effectiveGrade())->toBe(8);
});

test('users without an anchor keep their reported grade', function () {
    Carbon::setTestNow('2026-06-01');
    $user = User::factory()->create([
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => null,
    ]);

    expect($user->effectiveGrade())->toBe(8);
});

test('users without a grade level have an effective grade of zero', function () {
    $user = User::factory()->create(['grade_level' => null]);

    expect($user->effectiveGrade())->toBe(0);
});
