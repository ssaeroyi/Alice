<?php

use App\Enums\ComprehensionLevel;

test('clinical labels power the teacher-facing vocabulary only', function () {
    expect(ComprehensionLevel::Independent->label())->toBe('Independent')
        ->and(ComprehensionLevel::Instructional->label())->toBe('Instructional')
        ->and(ComprehensionLevel::Frustration->label())->toBe('Frustration')
        ->and(ComprehensionLevel::Critical->label())->toBe('Critical')
        ->and(ComprehensionLevel::Independent->description())->not->toBe('')
        ->and(ComprehensionLevel::Critical->description())->not->toBe('')
        ->and(method_exists(ComprehensionLevel::class, 'title'))->toBeFalse();
});

test('motivation returns a phrase from the pool for the level', function () {
    foreach (ComprehensionLevel::cases() as $level) {
        $phrase = $level->motivation('attempt-1');

        expect($phrase)->not->toBe('')
            ->and(in_array($phrase, config('placement.motivational_phrases.'.$level->value), true))->toBeTrue();
    }
});

test('motivation is deterministic per seed', function () {
    expect(ComprehensionLevel::Independent->motivation('attempt-7'))
        ->toBe(ComprehensionLevel::Independent->motivation('attempt-7'));
});
