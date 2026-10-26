<?php

use App\Support\IntegritySignals;

test('buildFlags detects focus loss', function () {
    $flags = IntegritySignals::buildFlags([
        'focus_lost' => 3,
        'focus_duration' => 45,
    ]);

    $this->assertSame(3, $flags['focus_lost']);
    $this->assertSame(45, $flags['focus_duration']);
});

test('buildFlags ignores zero focus loss', function () {
    $flags = IntegritySignals::buildFlags([
        'focus_lost' => 0,
        'focus_duration' => 0,
    ]);

    $this->assertArrayNotHasKey('focus_lost', $flags);
    $this->assertArrayNotHasKey('focus_duration', $flags);
});

test('buildFlags detects significant pastes', function () {
    $flags = IntegritySignals::buildFlags([
        'pastes' => [
            ['question' => 3, 'chars' => 50],
            ['question' => 1, 'chars' => 5],
        ],
    ]);

    $this->assertArrayHasKey('pastes', $flags);
    $this->assertCount(1, $flags['pastes']);
    $this->assertSame(3, $flags['pastes'][0]['question']);
    $this->assertSame(50, $flags['pastes'][0]['chars']);
});

test('buildFlags ignores tiny pastes', function () {
    $flags = IntegritySignals::buildFlags([
        'pastes' => [
            ['question' => 1, 'chars' => 3],
        ],
    ]);

    $this->assertArrayNotHasKey('pastes', $flags);
});

test('buildFlags detects implausible speed', function () {
    $flags = IntegritySignals::buildFlags([
        'time_spent' => 20,
        'question_count' => 5,
    ]);

    $this->assertArrayHasKey('speed_implausible', $flags);
    $this->assertSame(20, $flags['speed_implausible']['time']);
    $this->assertSame(5, $flags['speed_implausible']['questions']);
});

test('buildFlags ignores plausible speed', function () {
    $flags = IntegritySignals::buildFlags([
        'time_spent' => 100,
        'question_count' => 5,
    ]);

    $this->assertArrayNotHasKey('speed_implausible', $flags);
});

test('reviewNotes produces neutral text for focus loss', function () {
    $notes = IntegritySignals::reviewNotes(['focus_lost' => 2, 'focus_duration' => 30]);

    $this->assertCount(1, $notes);
    $this->assertSame('neutral', $notes[0]['tone']);
    $this->assertStringContainsString('2 times', $notes[0]['text']);
    $this->assertStringContainsString('30s', $notes[0]['text']);
});

test('reviewNotes produces neutral text for pastes', function () {
    $notes = IntegritySignals::reviewNotes([
        'pastes' => [['question' => 5, 'chars' => 25]],
    ]);

    $this->assertCount(1, $notes);
    $this->assertSame('neutral', $notes[0]['tone']);
    $this->assertStringContainsString('Question 5', $notes[0]['text']);
    $this->assertStringContainsString('25 characters', $notes[0]['text']);
});

test('reviewNotes produces hint tone for speed', function () {
    $notes = IntegritySignals::reviewNotes([
        'speed_implausible' => ['time' => 30, 'questions' => 5],
    ]);

    $this->assertCount(1, $notes);
    $this->assertSame('hint', $notes[0]['tone']);
});

test('hasFlags returns false for empty array', function () {
    $this->assertFalse(IntegritySignals::hasFlags([]));
});

test('hasFlags returns true for non-empty array', function () {
    $this->assertTrue(IntegritySignals::hasFlags(['focus_lost' => 1]));
});

test('disclaimer is present', function () {
    $text = IntegritySignals::disclaimer();
    $this->assertNotEmpty($text);
    $this->assertStringContainsString('not proof', $text);
});
