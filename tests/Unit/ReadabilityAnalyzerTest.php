<?php

use App\Support\ReadabilityAnalyzer;

test('analyze returns null for empty text', function () {
    expect(ReadabilityAnalyzer::analyze(null))->toBeNull()
        ->and(ReadabilityAnalyzer::analyze(''))->toBeNull()
        ->and(ReadabilityAnalyzer::analyze('   '))->toBeNull();
});

test('counts words, sentences, syllables and letters', function () {
    $result = ReadabilityAnalyzer::analyze('The quick brown fox jumps over the lazy dog. Alice reads every day!');

    expect($result['word_count'])->toBe(13)
        ->and($result['sentence_count'])->toBe(2)
        ->and($result['syllable_count'])->toBeGreaterThan(0)
        ->and($result['letter_count'])->toBeGreaterThan(0);
});

test('a run of exclamation marks is one sentence end', function () {
    expect(ReadabilityAnalyzer::sentenceCount('Hello!!'))->toBe(1)
        ->and(ReadabilityAnalyzer::sentenceCount('One. Two! Three?'))->toBe(3);
});

test('syllable approximation handles short and long words', function () {
    expect(ReadabilityAnalyzer::syllableCountWord('cat'))->toBe(1)
        ->and(ReadabilityAnalyzer::syllableCountWord('banana'))->toBe(3)
        ->and(ReadabilityAnalyzer::syllableCountWord('hello'))->toBe(2)
        ->and(ReadabilityAnalyzer::syllableCountWord('time'))->toBe(1)
        ->and(ReadabilityAnalyzer::syllableCountWord(''))->toBe(0);
});

test('flesch-kincaid grade level follows the formula', function () {
    // 0.39 x (10/2) + 11.8 x (15/10) - 15.59 = 1.95 + 17.7 - 15.59 = 4.06
    $score = ReadabilityAnalyzer::fleschKincaidGradeLevel(10, 2, 15);

    expect(abs($score - 4.06))->toBeLessThan(0.0001);
});

test('tuned tier bands sit at 5 and 9', function () {
    expect(ReadabilityAnalyzer::tierFor(4.99))->toBe('beginner')
        ->and(ReadabilityAnalyzer::tierFor(5.0))->toBe('intermediate')
        ->and(ReadabilityAnalyzer::tierFor(8.99))->toBe('intermediate')
        ->and(ReadabilityAnalyzer::tierFor(9.0))->toBe('advanced');
});

test('grade band and age range follow the tiers', function () {
    expect(ReadabilityAnalyzer::gradeLevelText(4.0))->toBe('Elementary (Grades 1-4)')
        ->and(ReadabilityAnalyzer::gradeLevelText(7.0))->toBe('Middle School (Grades 5-8)')
        ->and(ReadabilityAnalyzer::gradeLevelText(11.0))->toBe('High School+ (Grades 9-12)')
        ->and(ReadabilityAnalyzer::ageRange(4.0))->toBe('6-9 years')
        ->and(ReadabilityAnalyzer::ageRange(7.0))->toBe('10-13 years')
        ->and(ReadabilityAnalyzer::ageRange(11.0))->toBe('14+ years');
});

test('numeric level rounds the score and clamps to 1-20', function () {
    expect(ReadabilityAnalyzer::levelFor(3.2))->toBe(3)
        ->and(ReadabilityAnalyzer::levelFor(5.5))->toBe(6)
        ->and(ReadabilityAnalyzer::levelFor(-3))->toBe(1)
        ->and(ReadabilityAnalyzer::levelFor(19.6))->toBe(20)
        ->and(ReadabilityAnalyzer::levelFor(25))->toBe(20);
});

test('reading level is only computed for 50+ words', function () {
    expect(ReadabilityAnalyzer::readingLevelFor(null))->toBeNull()
        ->and(ReadabilityAnalyzer::readingLevelFor('A short story about a cat that is far too brief to analyze. It simply is not long enough yet.'))->toBeNull()
        ->and(ReadabilityAnalyzer::readingLevelFor(
            'Once upon a time, there was a little girl named Ruby. She lived in a small house near the sea. '
            .'Every day she walked to the shore and watched the waves. The waves came and went, and she counted them one by one. '
            .'When the sun went down, she walked home with a happy heart.'
        ))->not->toBeNull();
});
