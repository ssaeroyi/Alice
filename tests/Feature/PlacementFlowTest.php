<?php

use App\Enums\GradeLevel;
use App\Enums\PlacementStep;
use App\Models\PlacementAttempt;
use App\Models\PlacementResponse;
use App\Models\User;
use Database\Seeders\PlacementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlacementSeeder::class);
});

function vocabAnswersFor(array $items): array
{
    $answers = [];

    foreach ($items as $i => $item) {
        $answers['vocab_'.($i + 1)] = $item['options'][0];
    }

    return $answers;
}

function clozeAnswersFor(array $blanks): array
{
    $answers = [];

    foreach ($blanks as $blank) {
        $answers['cloze_'.$blank['key']] = $blank['options'][0];
    }

    return $answers;
}

function comprehensionAnswersFor(array $questions): array
{
    $answers = [];

    foreach ($questions as $i => $question) {
        $answers['comp_'.($i + 1)] = $question['options'][0];
    }

    return $answers;
}

test('submitting step 1 stores the grade and assigns a fixed set', function () {
    $user = User::factory()->create(['nickname' => 'Ali']);

    $this->actingAs($user)->get(route('placement.step1'));

    $this->actingAs($user)
        ->post(route('placement.step1.submit'), [
            'grade_level' => '8',
        ])
        ->assertRedirect(route('placement.intro'));

    $user->refresh();

    expect($user->grade_level)->toBe(GradeLevel::Grade8);
    expect($user->nickname)->toBe('Ali');

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();

    expect($attempt->set)->not->toBeNull();
});

test('the assigned set stays fixed across the attempt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '7',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $setId = $attempt->set_id;

    $this->actingAs($user)->get(route('placement.step1'));
    $this->actingAs($user)->get(route('placement.step2'));

    expect(PlacementAttempt::where('user_id', $user->id)->count())->toBe(1);
    expect($attempt->fresh()->set_id)->toBe($setId);
});

test('a student can answer vocabulary, cloze, and comprehension in sequence', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;

    $this->actingAs($user)->get(route('placement.step2'))->assertOk();

    $this->actingAs($user)
        ->post(route('placement.step2.submit'), vocabAnswersFor($content['vocab']))
        ->assertRedirect(route('placement.step3'));

    $this->actingAs($user)->get(route('placement.step3'))->assertOk();

    $this->actingAs($user)
        ->post(route('placement.step3.submit'), clozeAnswersFor($content['cloze']['blanks']))
        ->assertRedirect(route('placement.step4'));

    $this->actingAs($user)->get(route('placement.step4'))->assertOk();

    $this->actingAs($user)
        ->post(route('placement.step4.submit'), comprehensionAnswersFor($content['comprehension']['questions']))
        ->assertRedirect(route('placement.step5'));

    $this->actingAs($user)->get(route('placement.step5'))->assertOk();

    $responses = $attempt->fresh()->responses->keyBy(fn ($r) => $r->step->value);

    expect($responses)->toHaveKeys(['vocab', 'cloze', 'comprehension']);
    expect($responses['vocab']->payload)->toHaveCount(10);
    expect($responses['cloze']->payload)->toHaveCount(10);
    expect($responses['comprehension']->payload)->toHaveCount(5);
});

test('the intro pitch screen gates on profile setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('placement.intro'))
        ->assertRedirect(route('placement.step1'));

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $this->actingAs($user)->get(route('placement.intro'))->assertOk();
});

test('students cannot skip ahead to a later scored step', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $this->actingAs($user)
        ->get(route('placement.step4'))
        ->assertRedirect(route('placement.step2'));
});

test('re-visiting a completed step shows it again with the saved answers prefilled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;
    $answers = vocabAnswersFor($content['vocab']);

    $this->actingAs($user)->post(route('placement.step2.submit'), $answers);

    $this->actingAs($user)
        ->get(route('placement.step2'))
        ->assertOk();

    expect(PlacementResponse::where('step', PlacementStep::Vocab->value)->count())->toBe(1);
});

test('an answered step can be re-submitted so going back to change an answer works', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;

    $this->actingAs($user)->post(route('placement.step2.submit'), vocabAnswersFor($content['vocab']));

    $changed = vocabAnswersFor($content['vocab']);
    $changed['vocab_1'] = collect($content['vocab'][0]['options'])->last();

    $this->actingAs($user)
        ->post(route('placement.step2.submit'), $changed)
        ->assertRedirect(route('placement.step3'));

    $payload = PlacementResponse::where('step', PlacementStep::Vocab->value)->firstOrFail()->payload;

    expect($payload[1])->toBe($changed['vocab_1']);
});

test('each scored step offers a way back to the previous step', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;

    $this->actingAs($user)
        ->get(route('placement.step2'))
        ->assertOk()
        ->assertSee('id="vocab-back"', false)
        ->assertSee(route('placement.step1'), false);

    $this->actingAs($user)->post(route('placement.step2.submit'), vocabAnswersFor($content['vocab']));

    $this->actingAs($user)
        ->get(route('placement.step3'))
        ->assertOk()
        ->assertSee(route('placement.step2'), false);

    $this->actingAs($user)->post(route('placement.step3.submit'), clozeAnswersFor($content['cloze']['blanks']));

    $this->actingAs($user)
        ->get(route('placement.step4'))
        ->assertOk()
        ->assertSee('id="comp-back"', false)
        ->assertSee(route('placement.step3'), false);
});

test('vocabulary validation rejects a missing answer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $answers = vocabAnswersFor($attempt->set->content['vocab']);
    unset($answers['vocab_10']);

    $this->actingAs($user)
        ->post(route('placement.step2.submit'), $answers)
        ->assertSessionHasErrors('vocab_10');

    expect(PlacementResponse::where('step', PlacementStep::Vocab->value)->count())->toBe(0);
});

test('option order stays stable across revisits of the same attempt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $this->actingAs($user)->get(route('placement.step2'));
    $first = $this->actingAs($user)->get(route('placement.step2'));
    $second = $this->actingAs($user)->get(route('placement.step2'));

    expect($first->getContent())->toBe($second->getContent());
});

test('different attempts get different option orders', function () {
    $first = User::factory()->create();
    $this->actingAs($first)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);
    $firstPage = $this->actingAs($first)->get(route('placement.step2'));

    $second = User::factory()->create();
    $this->actingAs($second)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);
    $secondPage = $this->actingAs($second)->get(route('placement.step2'));

    expect($firstPage->getContent())->not->toBe($secondPage->getContent());
});
