<?php

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Support\AliceLibrary;
use App\Support\QuestionWriter;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Question-integrity audit (Phase G).
 *
 * The quiz renderer trusts that every persisted question is answerable: it
 * grades by type and only touches the fields that type owns. QuestionWriter
 * enforces those invariants at write time, so teacher-authored and library
 * questions are byte-for-byte the same shape. This suite codifies the same
 * rules as a standing audit that a single bad row written around the writer
 * (legacy data, a hand-edited manifest, a future importer bug) would trip.
 *
 * The True/False exemption is explicit: the renderer hardcodes TRUE/FALSE
 * labels, so TF questions are AUTHORED with blank option_a/option_b and a
 * correct_option (A=TRUE / B=FALSE). The audit must never require option
 * text for TF.
 */

function integrityStory(): Story
{
    return Story::create([
        'title' => 'Integrity Audit Story',
        'author' => 'A. Auditor',
        'synopsis' => 'Seeds every quiz question type.',
        'content' => 'The auditor walked slowly down the quiet road at dawn.',
        'word_count' => 500,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Easy',
        'status' => 'published',
    ]);
}

function integritySection(Story $story): int
{
    $section = QuestionSection::query()->create([
        'story_id' => $story->id,
        'name' => 'Checked',
        'position' => 1,
    ]);

    return $section->id;
}

/**
 * Return a list of reason strings describing why a question is not
 * answerable (empty when it is). One dedicated rule per owned column so a
 * failure names exactly what is wrong.
 */
function auditQuestionIntegrity(Question $q): array
{
    $problems = [];

    if (trim((string) $q->question_text) === '') {
        $problems[] = 'no question text';
    }

    switch ($q->type) {
        case QuestionType::Mcq:
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                if (trim((string) $q->{'option_'.$letter}) === '') {
                    $problems[] = 'empty option_'.$letter;
                }
            }
            if ($q->correct_option === null) {
                $problems[] = 'no correct option';
            }
            break;

        case QuestionType::TrueFalse:
            // Exemption: blank options are LEGAL. Only the answer bit matters.
            if ($q->correct_option === null || ! in_array($q->correct_option->value, ['a', 'b'], true)) {
                $problems[] = 'TF needs a/b correct option';
            }
            break;

        case QuestionType::Identification:
            $answers = array_values(array_filter(collect($q->acceptable_answers ?? [])->map(fn ($v) => trim((string) $v))->all(), fn ($v) => $v !== ''));
            if ($answers === []) {
                $problems[] = 'no acceptable answers';
            }
            break;

        case QuestionType::Matching:
            $pairs = collect($q->match_pairs ?? [])
                ->filter(fn ($p) => is_array($p) && trim((string) ($p['left'] ?? '')) !== '' && trim((string) ($p['right'] ?? '')) !== '')
                ->values();
            if ($pairs->count() < 2) {
                $problems[] = 'fewer than 2 complete pairs';
            }
            break;

        case QuestionType::Sequence:
            $items = collect($q->sequence_items ?? [])->filter(fn ($v) => trim((string) $v) !== '')->values();
            if ($items->count() < 3) {
                $problems[] = 'fewer than 3 sequence items';
            }
            break;
    }

    return $problems;
}

/*
 * Anchor-integrity guard.
 *
 * Every answerable fact should trace back to the story text so a student can
 * verify it. This uses a light porter-style stemmer (shared content words)
 * and demands at least one stemmed word drawn from the story's own text:
 *   - identification: the first acceptable answer must share >=1 word
 *   - matching: each completed pair must have >=1 shared word on the LEFT,
 *     the RIGHT, or across both (a pair describes one fact)
 *   - sequence: each item must share >=1 word with the text
 *
 * The match-left/right/combined rule is deliberately a single shared word so
 * faithful paraphrases of longer phrases (classic retellings, side-by-side
 * character descriptions) are accepted while a wholly fabricated phrase is
 * not.
 */
function stemAnchorWords(array $words): array
{
    $out = [];

    foreach ($words as $w) {
        $b = strtolower(trim($w));

        if ($b === '') {
            continue;
        }

        if (preg_match('/ing$/', $b) && strlen($b) > 5) {
            $b = substr($b, 0, -3);
        } elseif (preg_match('/(ed|es)$/', $b) && ! preg_match('/(eed)$/', $b) && strlen($b) > 4) {
            $b = substr($b, 0, -2);
        }

        if (preg_match('/ly$/', $b) && strlen($b) > 4) {
            $b = substr($b, 0, -2);
        }

        if (substr($b, -1) === 'e' && strlen($b) > 4) {
            $b = substr($b, 0, -1);
        }

        if (substr($b, -1) === 's' && substr($b, -2) !== 'ss' && strlen($b) > 2) {
            $b = substr($b, 0, -1);
        }

        if (strlen($b) >= 3) {
            $out[] = $b;
        }
    }

    return array_values(array_unique($out));
}

function anchorWords(string $phrase): array
{
    preg_match_all('/[A-Za-z]{3,}/', strtolower($phrase), $m);

    return stemAnchorWords($m[0]);
}

/**
 * Return a list of reason strings describing any answerable question whose
 * fact cannot be traced back to its story's text (empty when anchored).
 */
function auditStoryAnchoring(Story $story): array
{
    $problems = [];

    $texts = anchorWords($story->content);

    if ($texts === []) {
        return ['story has no anchorable text'];
    }

    foreach ($story->questions as $q) {
        $label = "#{$q->id} ({$q->type->value})";

        switch ($q->type) {
            case QuestionType::Identification:
                $answers = collect($q->acceptable_answers ?? [])
                    ->map(fn ($v) => trim((string) $v))
                    ->filter(fn ($v) => $v !== '')
                    ->values();

                if ($answers->isNotEmpty() && count(array_intersect(anchorWords((string) $answers[0]), $texts)) < 1) {
                    $problems[] = "{$label} identification '{$answers[0]}' shares no word with the story text";
                }
                break;

            case QuestionType::Matching:
                foreach ($q->match_pairs ?? [] as $pair) {
                    if (! is_array($pair) || trim((string) ($pair['left'] ?? '')) === '' || trim((string) ($pair['right'] ?? '')) === '') {
                        continue;
                    }

                    $left = anchorWords((string) $pair['left']);
                    $right = anchorWords((string) $pair['right']);

                    $anchored = count(array_intersect($left, $texts)) >= 1
                        || count(array_intersect($right, $texts)) >= 1
                        || count(array_intersect(array_values(array_unique(array_merge($left, $right))), $texts)) >= 1;

                    if (! $anchored) {
                        $problems[] = "{$label} matching pair '{$pair['left']}' <-> '{$pair['right']}' shares no word with the story text";
                    }
                }
                break;

            case QuestionType::Sequence:
                foreach (collect($q->sequence_items ?? [])->filter(fn ($v) => trim((string) $v) !== '') as $item) {
                    if (count(array_intersect(anchorWords((string) $item), $texts)) < 1) {
                        $problems[] = "{$label} sequence item '{$item}' shares no word with the story text";
                    }
                }
                break;
        }
    }

    return array_values(array_unique($problems));
}

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

it('audits a clean story covering every question type without complaint', function () {
    $story = integrityStory();
    $section = integritySection($story);

    $questions = [
        QuestionWriter::create($story, [
            'type' => 'mcq',
            'question_text' => 'Which word means nearly the same as quiet?',
            'option_a' => 'Still', 'option_b' => 'Loud', 'option_c' => 'Bright', 'option_d' => 'Heavy',
            'correct_option' => 'a',
            'section_id' => $section,
        ]),
        QuestionWriter::create($story, [
            'type' => 'true_false',
            'question_text' => 'The auditor reached the end of the road by sunrise.',
            'correct_option' => 'a',
            'section_id' => $section,
        ]),
        QuestionWriter::create($story, [
            'type' => 'identification',
            'question_text' => 'What kind of light hung low over the road?',
            'acceptable_answers' => ['dawn light', 'morning light'],
            'section_id' => $section,
        ]),
        QuestionWriter::create($story, [
            'type' => 'matching',
            'question_text' => 'Match each person to their part in the walk.',
            'match_pairs' => [
                ['left' => 'The auditor', 'right' => 'Checked the gate'],
                ['left' => 'The gardener', 'right' => 'Watered the beds'],
                ['left' => 'The gatekeeper', 'right' => 'Opened the latch'],
            ],
            'section_id' => $section,
        ]),
        QuestionWriter::create($story, [
            'type' => 'sequence',
            'question_text' => 'Order the morning walk.',
            'sequence_items' => ['Left the house', 'Crossed the lane', 'Opened the gate', 'Reached the hill'],
            'section_id' => $section,
        ]),
    ];

    foreach ($questions as $question) {
        expect(auditQuestionIntegrity($question))->toBeEmpty();
    }
});

it('exempts true-false questions from needing option text', function () {
    $story = integrityStory();
    $section = integritySection($story);

    $tf = QuestionWriter::create($story, [
        'type' => 'true_false',
        'question_text' => 'The road went uphill towards the old mill.',
        'correct_option' => 'b',
        'section_id' => $section,
    ]);

    // Authoring a TF question leaves options blank by design.
    expect(auditQuestionIntegrity($tf))->toBeEmpty()
        ->and(trim((string) $tf->option_a))->toBe('')
        ->and(trim((string) $tf->option_b))->toBe('');
});

it('flags a malformed mcq and an answered-free identification', function () {
    $story = integrityStory();
    $section = integritySection($story);

    $mcq = QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'Which color did the gate keeper favor?',
        'option_a' => 'Green', 'option_b' => 'Blue', 'option_c' => 'Red', 'option_d' => 'Gold',
        'correct_option' => 'a',
        'section_id' => $section,
    ]);
    $mcq->forceFill(['option_c' => ''])->save();

    expect(auditQuestionIntegrity($mcq->refresh()))->toContain('empty option_c');

    $ident = QuestionWriter::create($story, [
        'type' => 'identification',
        'question_text' => 'Which plant grew tallest behind the gate?',
        'acceptable_answers' => ['rose vine'],
        'section_id' => $section,
    ]);
    $ident->forceFill(['acceptable_answers' => []])->save();

    expect(auditQuestionIntegrity($ident->refresh()))->toContain('no acceptable answers');
});

it('flags thin matching and sequence questions and a TF without its answer bit', function () {
    $story = integrityStory();
    $section = integritySection($story);

    $match = QuestionWriter::create($story, [
        'type' => 'matching',
        'question_text' => 'Giant a-n-c match each tool to its trade.',
        'match_pairs' => [
            ['left' => 'Hammer', 'right' => 'Carpentry'],
            ['left' => 'Shears', 'right' => 'Gardening'],
        ],
        'section_id' => $section,
    ]);
    $match->forceFill(['match_pairs' => [['left' => 'Hammer', 'right' => 'Carpentry']]])->save();
    expect(auditQuestionIntegrity($match->refresh()))->toContain('fewer than 2 complete pairs');

    $seq = QuestionWriter::create($story, [
        'type' => 'sequence',
        'question_text' => 'Put every step of the walk in order.',
        'sequence_items' => ['Shut the door', 'Crossed the yard', 'Took the lane'],
        'section_id' => $section,
    ]);
    $seq->forceFill(['sequence_items' => ['Shut the door']])->save();
    expect(auditQuestionIntegrity($seq->refresh()))->toContain('fewer than 3 sequence items');

    $tf = QuestionWriter::create($story, [
        'type' => 'true_false',
        'question_text' => 'The walk ended before noon.',
        'correct_option' => 'a',
        'section_id' => $section,
    ]);
    $tf->forceFill(['correct_option' => 'c'])->save();
    expect(auditQuestionIntegrity($tf->refresh()))->toContain('TF needs a/b correct option');
});

it('every library question traces its fact back to the story text', function () {
    AliceLibrary::importAll();

    $stories = Story::query()->with('questions')->get();

    expect($stories->count())->toBeGreaterThan(10);

    $failures = [];

    foreach ($stories as $story) {
        $problems = auditStoryAnchoring($story);

        if ($problems !== []) {
            $failures["[{$story->slug}]"] = $problems;
        }
    }

    expect($failures)->toBe([]);
});
