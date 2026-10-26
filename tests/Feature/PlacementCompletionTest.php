<?php

use App\Enums\ComprehensionLevel;
use App\Enums\PlacementAttemptStatus;
use App\Enums\PlacementStep;
use App\Models\KeyTransaction;
use App\Models\Notification;
use App\Models\PlacementAttempt;
use App\Models\PlacementResponse;
use App\Models\PlacementSet;
use App\Models\User;
use App\Services\PlacementService;
use Database\Seeders\PlacementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlacementSeeder::class);
});

function correctAnswersFor(array $items, string $prefix): array
{
    $answers = [];

    foreach ($items as $i => $item) {
        $answers[$prefix.'_'.($i + 1)] = $item['answer'];
    }

    return $answers;
}

function wrongAnswersFor(array $items, string $prefix): array
{
    $answers = [];

    foreach ($items as $i => $item) {
        $answers[$prefix.'_'.($i + 1)] = collect($item['options'])->first(fn ($option) => $option !== $item['answer']);
    }

    return $answers;
}

function completePlacement(User $user, callable $answersFor): PlacementAttempt
{
    test()->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;

    test()->actingAs($user)->post(route('placement.step2.submit'), $answersFor($content['vocab'], 'vocab'));
    test()->actingAs($user)->post(route('placement.step3.submit'), $answersFor($content['cloze']['blanks'], 'cloze'));
    test()->actingAs($user)->post(route('placement.step4.submit'), $answersFor($content['comprehension']['questions'], 'comp'));

    return $attempt;
}

/**
 * Complete placement with an exact number of correct answers across all
 * 25 items (sections filled in order: vocab -> cloze -> comprehension),
 * everything else deliberately wrong.
 */
function completePlacementWithCorrectCount(User $user, int $correctCount): PlacementAttempt
{
    test()->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;

    $remaining = $correctCount;
    $payloads = [];

    foreach (['vocab' => $content['vocab'], 'cloze' => $content['cloze']['blanks'], 'comp' => $content['comprehension']['questions']] as $prefix => $items) {
        $answers = [];

        foreach ($items as $i => $item) {
            if ($remaining > 0) {
                $answers[$prefix.'_'.($i + 1)] = $item['answer'];
                $remaining--;
            } else {
                $answers[$prefix.'_'.($i + 1)] = collect($item['options'])->first(fn ($option) => $option !== $item['answer']);
            }
        }

        $payloads[$prefix] = $answers;
    }

    test()->actingAs($user)->post(route('placement.step2.submit'), $payloads['vocab']);
    test()->actingAs($user)->post(route('placement.step3.submit'), $payloads['cloze']);
    test()->actingAs($user)->post(route('placement.step4.submit'), $payloads['comp']);

    return $attempt;
}

test('a perfect score awards independent level, full bar, unlocks the hub, and queues the claimable starter reward', function () {
    $user = User::factory()->create();

    $attempt = completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));

    $user->refresh();
    $attempt->refresh();

    expect($user->is_setup_complete)->toBeTrue();
    expect($user->comprehension_level)->toBe(ComprehensionLevel::Independent);
    expect($user->comprehension_bar)->toBe(100);
    expect($user->reading_level)->toBe(8);
    expect($attempt->status)->toBe(PlacementAttemptStatus::Completed);

    // The starter reward is NOT paid at completion (keys only, no points) —
    // it waits for the CLAIM click on the inbox notification.
    expect($user->total_keys)->toBe(0)
        ->and($user->points)->toBe(0);

    expect(KeyTransaction::where('user_id', $user->id)->where('reason', 'welcome_kit')->exists())->toBeFalse();

    $notice = Notification::where('user_id', $user->id)->where('type', 'reward.welcome_kit')->first();
    expect($notice)->not->toBeNull()
        ->and($notice->read_at)->toBeNull()
        ->and((string) $notice->link)->toBe('reward:welcome-kit');

    $this->actingAs($user)->get(route('hub'))->assertOk();
});

test('claiming the starter reward pays keys once and consumes the notification', function () {
    $user = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));
    $user->refresh();

    $notice = Notification::where('user_id', $user->id)->where('type', 'reward.welcome_kit')->firstOrFail();
    $keys = (int) config('placement.welcome_kit.keys');

    $this->actingAs($user)->from(route('hub'))
        ->post(route('inbox.claim', $notice))
        ->assertRedirect(route('hub'))
        ->assertSessionHas('toast');

    $user->refresh();

    expect($user->total_keys)->toBe($keys)
        ->and($user->points)->toBe(0);

    $kit = KeyTransaction::where('user_id', $user->id)->where('reason', 'welcome_kit')->get();
    expect($kit)->toHaveCount(1)
        ->and($kit[0]->amount)->toBe($keys)
        ->and($kit[0]->balance_after)->toBe($keys);

    expect(Notification::where('user_id', $user->id)->where('type', 'reward.welcome_kit')->exists())->toBeFalse();

    // A stale replay of the consumed ticket is a 404.
    $this->actingAs($user)->post(route('inbox.claim', $notice))->assertNotFound();
});

test('the starter reward cannot be claimed twice even if the ticket is re-created', function () {
    $user = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));
    $user->refresh();

    $notice = Notification::where('user_id', $user->id)->where('type', 'reward.welcome_kit')->firstOrFail();
    $keys = (int) config('placement.welcome_kit.keys');

    $this->actingAs($user)->post(route('inbox.claim', $notice))->assertRedirect();

    // A ghost ticket reappears — the ledger guard still refuses a double pay.
    Notification::create([
        'user_id' => $user->id,
        'type' => 'reward.welcome_kit',
        'title' => 'Welcome to ALICE!',
        'body' => 'Starter reward.',
        'link' => 'reward:welcome-kit',
    ]);

    $ghost = Notification::where('user_id', $user->id)->where('type', 'reward.welcome_kit')->firstOrFail();

    $this->actingAs($user)->from(route('hub'))
        ->post(route('inbox.claim', $ghost))
        ->assertRedirect(route('hub'));

    $user->refresh();

    expect($user->total_keys)->toBe($keys);
    expect(KeyTransaction::where('user_id', $user->id)->where('reason', 'welcome_kit')->count())->toBe(1);
});

test('claiming a non-reward notification or someone else’s ticket is refused', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));
    $user->refresh();

    $plain = Notification::create([
        'user_id' => $user->id,
        'type' => 'assignment.new',
        'title' => 'New assignment',
        'body' => 'Read the story.',
        'link' => 'assignment:1',
    ]);

    $this->actingAs($user)
        ->post(route('inbox.claim', $plain))
        ->assertNotFound();

    $otherNotice = Notification::create([
        'user_id' => $other->id,
        'type' => 'reward.welcome_kit',
        'title' => 'Welcome to ALICE!',
        'body' => 'Starter reward.',
        'link' => 'reward:welcome-kit',
    ]);

    $this->actingAs($user)
        ->post(route('inbox.claim', $otherNotice))
        ->assertForbidden();
});

test('an all-wrong score awards the critical level at the served floor', function () {
    $user = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => wrongAnswersFor($items, $prefix));

    $user->refresh();

    expect($user->comprehension_level)->toBe(ComprehensionLevel::Critical);
    expect($user->comprehension_bar)->toBe(0);
    expect($user->reading_level)->toBe((int) config('placement.reading_level_floor'));
});

test('one mistake (24/25) still lands independent at full grade level', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();
    $content = $attempt->set->content;

    $vocab = correctAnswersFor($content['vocab'], 'vocab');
    $vocab['vocab_1'] = collect($content['vocab'][0]['options'])->first(fn ($option) => $option !== $content['vocab'][0]['answer']);

    $this->actingAs($user)->post(route('placement.step2.submit'), $vocab);
    $this->actingAs($user)->post(route('placement.step3.submit'), correctAnswersFor($content['cloze']['blanks'], 'cloze'));
    $this->actingAs($user)->post(route('placement.step4.submit'), correctAnswersFor($content['comprehension']['questions'], 'comp'));

    $user->refresh();

    expect($user->comprehension_level)->toBe(ComprehensionLevel::Independent);
    expect($user->comprehension_bar)->toBe(96);
    expect($user->reading_level)->toBe(8);
});

test('21 of 25 correct (84%) lands instructional one grade below', function () {
    $user = User::factory()->create();

    completePlacementWithCorrectCount($user, 21);

    $user->refresh();

    expect($user->comprehension_level)->toBe(ComprehensionLevel::Instructional);
    expect($user->comprehension_bar)->toBe(84);
    expect($user->reading_level)->toBe(7);
});

test('17 of 25 correct (68%) lands frustration at the served floor', function () {
    $user = User::factory()->create();

    completePlacementWithCorrectCount($user, 17);

    $user->refresh();

    expect($user->comprehension_level)->toBe(ComprehensionLevel::Frustration);
    expect($user->comprehension_bar)->toBe(68);
    expect($user->reading_level)->toBe((int) config('placement.reading_level_floor'));
});

test('12 of 25 correct (48%) lands critical at the served floor', function () {
    $user = User::factory()->create();

    completePlacementWithCorrectCount($user, 12);

    $user->refresh();

    expect($user->comprehension_level)->toBe(ComprehensionLevel::Critical);
    expect($user->comprehension_bar)->toBe(48);
    expect($user->reading_level)->toBe((int) config('placement.reading_level_floor'));
});

test('the result screen shows the starting shelf and section scores, but no welcome kit pill', function () {
    $user = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));

    $user->refresh();

    $this->actingAs($user)
        ->get(route('placement.step5'))
        ->assertOk()
        ->assertSee('YOUR STARTING SHELF')
        ->assertSee('Grade 8')
        ->assertDontSee('Welcome kit')
        ->assertSee('100%')
        ->assertSee('10/10')
        ->assertSee('5/5')
        ->assertDontSee('Independent');
});

test('an incomplete user is blocked from the hub', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('placement.step1.submit'), [
        'grade_level' => '8',
    ]);

    $this->actingAs($user)
        ->get(route('hub'))
        ->assertRedirect(route('placement.step1'));
});

test('a completed user is redirected from placement steps to the hub', function () {
    $user = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));

    $user->refresh();

    $this->actingAs($user)
        ->get(route('placement.step1'))
        ->assertRedirect(route('hub'));
});

test('the score is frozen in a snapshot at completion and cannot be changed later', function () {
    $user = User::factory()->create();

    $attempt = completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));

    $attempt->refresh();

    expect($attempt->score)->not->toBeNull();
    expect($attempt->score['correct'])->toBe(25);
    expect($attempt->score['percentage'])->toBe(100);

    $live = app(PlacementService::class)->score($attempt);
    expect($attempt->score['correct'])->toBe($live['correct']);

    $user->refresh();

    $wrong = wrongAnswersFor($attempt->set->content['vocab'], 'vocab');
    $this->actingAs($user)
        ->post(route('placement.step2.submit'), $wrong)
        ->assertRedirect(route('hub'));

    $attempt->refresh();

    expect($attempt->score['correct'])->toBe(25);
    expect(array_values(PlacementResponse::where('attempt_id', $attempt->id)->where('step', PlacementStep::Vocab->value)->first()->payload))
        ->toBe(array_values(correctAnswersFor($attempt->set->content['vocab'], 'vocab')));
});

test('answers submitted before step 1 are bounced without saving', function () {
    $user = User::factory()->create();

    $content = PlacementSet::query()->first()->content;

    $this->actingAs($user)
        ->post(route('placement.step2.submit'), correctAnswersFor($content['vocab'], 'vocab'))
        ->assertRedirect(route('placement.step1'));

    $attempt = PlacementAttempt::where('user_id', $user->id)->firstOrFail();

    expect(PlacementResponse::where('attempt_id', $attempt->id)->count())->toBe(0);
});

test('the result screen includes the per-item answer review', function () {
    $user = User::factory()->create();

    $attempt = completePlacement($user, function (array $items, string $prefix) {
        return wrongAnswersFor($items, $prefix);
    });

    $user->refresh();

    $this->actingAs($user)
        ->get(route('placement.step5'))
        ->assertOk()
        ->assertSee('SEE MY ANSWERS')
        ->assertSee('WRONG')
        ->assertSee('Correct answer:')
        ->assertSee($attempt->set->content['vocab'][0]['answer']);
});

test('a completed user bounced to the hub sees a finish toast', function () {
    $user = User::factory()->create();

    completePlacement($user, fn (array $items, string $prefix) => correctAnswersFor($items, $prefix));

    $user->refresh();

    $this->actingAs($user)->followingRedirects()
        ->get(route('placement.step1'))
        ->assertSee('You already finished the quick check.');
});

test('the diagnostic report is owner-only and shows the scoring proof', function () {
    $student = User::factory()->create();
    $attempt = completePlacement($student, fn (array $items, string $prefix) => wrongAnswersFor($items, $prefix));

    $this->actingAs($student)
        ->get(route('placement.report', $attempt))
        ->assertOk()
        ->assertSee('DIAGNOSTIC REPORT')
        ->assertSee($attempt->set->title)
        ->assertSee('Threshold mapping')
        ->assertSee('Diagnosed reading level')
        ->assertSee('snapshot')
        ->assertSee('0 / 25')
        ->assertSee('PASSAGE READABILITY')
        ->assertSee('Flesch-Kincaid Grade')
        ->assertSee('METHODOLOGY')
        ->assertSee('Betts, 1946');

    $other = User::factory()->create();
    $this->actingAs($other)
        ->get(route('placement.report', $attempt))
        ->assertForbidden();
});
