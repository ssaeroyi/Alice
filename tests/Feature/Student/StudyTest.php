<?php

use App\Http\Controllers\Student\StudyController;
use App\Models\DictionaryEntry;
use App\Models\KeyTransaction;
use App\Models\SavedWord;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Services\StudyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['is_setup_complete' => true]);
    $this->service = app(StudyService::class);
});

function seedDictionaryWord(string $lemma = 'wolf'): void
{
    DictionaryEntry::create([
        'lemma' => $lemma,
        'pos' => 'noun',
        'sense_num' => 1,
        'definition' => "a wild carnivorous mammal ({$lemma})",
        'example' => "The {$lemma} howled at night.",
        'freq' => 1500,
    ]);
}

function saveWord(User $user, string $word, array $overrides = []): SavedWord
{
    return SavedWord::create(array_merge([
        'user_id' => $user->id,
        'word' => $word,
        'definition' => "definition of {$word}",
        'part_of_speech' => 'noun',
        'example_sentence' => "The {$word} ran fast.",
    ], $overrides));
}

// ----------------------------------------------------------------------
// Dashboard
// ----------------------------------------------------------------------

test('dashboard shows the empty state for a student with no words', function () {
    $this->actingAs($this->student)
        ->get(route('study'))
        ->assertOk()
        ->assertSee('No words yet')
        ->assertDontSee('Flashcards for today', false);
});

test('dashboard aggregates tier counts, totals and the daily queue headline', function () {
    saveWord($this->student, 'apple');                                            // new
    saveWord($this->student, 'brave', ['mastery_level' => 1, 'times_wrong' => 2, 'last_drilled_at' => now()->subDays(2), 'due_at' => now()->subDay()]);
    saveWord($this->student, 'calm', ['mastery_level' => 3, 'times_correct' => 3, 'last_drilled_at' => now()->subDays(2), 'due_at' => now()->addDays(4)]);
    saveWord($this->student, 'dusk', ['mastery_level' => 4, 'times_correct' => 5, 'last_drilled_at' => now()->subDays(2), 'due_at' => now()->addDays(10)]);

    $response = $this->actingAs($this->student)->get(route('study'));
    $response->assertOk()
        ->assertSee('Flashcards for today', false);

    expect($this->service->statsFor($this->student))->toBe([
        'strong' => 2,
        'learning' => 1,
        'not_studied' => 1,
        'total' => 4,
    ])
        ->and(min($this->service->dueCount($this->student), $this->service->batchSizeFor($this->student)))->toBe(2);
});

test('the queue caps at the remaining daily allowance', function () {
    $this->student->update(['daily_study_goal' => 5]);

    for ($i = 0; $i < 8; $i++) {
        saveWord($this->student, "word{$i}");
    }

    $response = $this->actingAs($this->student)->get(route('study'));
    $response->assertOk();

    expect($this->service->queueFor($this->student)->count())->toBe(5);
});

test('after meeting the goal the batch becomes a bonus round of goal size', function () {
    $this->student->update(['daily_study_goal' => 5]);

    for ($i = 0; $i < 12; $i++) {
        saveWord($this->student, "bonus{$i}");
    }

    // Simulate 5 already-reviewed today.
    SavedWord::whereKey(SavedWord::query()->limit(5)->pluck('id'))
        ->update(['mastery_level' => 1, 'last_drilled_at' => now(), 'due_at' => now()->addDays(9)]);

    // The 5 reviewed ones are no longer due; 7 remain due. Bonus batch = 5.
    expect($this->service->batchSizeFor($this->student))->toBe(5)
        ->and($this->service->queueFor($this->student)->count())->toBe(5);
});

test('forgotten count only includes failed words below Strong', function () {
    saveWord($this->student, 'eager', ['times_wrong' => 3, 'mastery_level' => 1]);
    saveWord($this->student, 'fable', ['times_wrong' => 1, 'mastery_level' => 4]);   // recovered
    saveWord($this->student, 'gleam');                                                // never drilled

    expect($this->service->forgottenCount($this->student))->toBe(1);
});

// ----------------------------------------------------------------------
// Daily goal
// ----------------------------------------------------------------------

test('the daily goal updates within bounds and reports the new queue size', function () {
    saveWord($this->student, 'hollow');
    saveWord($this->student, 'ivy');

    $response = $this->actingAs($this->student)
        ->patchJson(route('study.goal'), ['goal' => 40])
        ->assertOk()
        ->assertJsonPath('goal', 40)
        ->assertJsonPath('queue_size', 2);

    expect($this->student->fresh()->daily_study_goal)->toBe(40);
});

test('the daily goal rejects out-of-range values', function () {
    $this->actingAs($this->student)
        ->patchJson(route('study.goal'), ['goal' => 4])
        ->assertJsonValidationErrors('goal');

    $this->actingAs($this->student)
        ->patchJson(route('study.goal'), ['goal' => 101])
        ->assertJsonValidationErrors('goal');

    expect($this->student->fresh()->daily_study_goal)->toBe(20);
});

// ----------------------------------------------------------------------
// Flashcard session + grading
// ----------------------------------------------------------------------

test('an empty session bounces back to study with a toast', function () {
    $this->actingAs($this->student)
        ->get(route('study.session'))
        ->assertRedirect(route('study'))
        ->assertSessionHas('toast.message', "You're all caught up. Nice work!");
});

test('the flashcard page ships the flip card, Anki piles, and the say-it modal', function () {
    seedDictionaryWord('meridian');
    saveWord($this->student, 'meridian');

    $html = $this->actingAs($this->student)
        ->get(route('study.session'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-flip')                  // tactile 3D flip card
        ->toContain('data-pronounce-check="meridian"') // the say-it modal targets the headword
        ->toContain('data-definition')            // meaning lives on the card
        ->toContain('data-word')                  // word sits on the back face
        ->toContain('data-pile-hard')             // Anki-style pile counters
        ->toContain('data-pile-forgot')
        ->toContain('data-pile-green')
        ->toContain('data-say-modal-open')        // mic opens the say-it modal
        ->toContain('say-check-modal')
        ->toContain('data-tricky-list')           // session summary tricky list
        ->toContain('Forgot');
});

test('the flashcard session runs immersive without the app chrome', function () {
    seedDictionaryWord('meridian');
    saveWord($this->student, 'meridian');

    $this->actingAs($this->student)
        ->get(route('study.session'))
        ->assertOk()
        ->assertDontSee('MINI-GAMES')
        ->assertDontSee('My Stats')
        ->assertSee('data-pile-green', false);
});

test('the session serves new and due words but skips future-dated ones, weakest first', function () {
    saveWord($this->student, 'newbie');
    saveWord($this->student, 'overdue', ['mastery_level' => 1, 'last_drilled_at' => now()->subDays(4), 'due_at' => now()->subHour()]);
    saveWord($this->student, 'later', ['mastery_level' => 2, 'last_drilled_at' => now(), 'due_at' => now()->addDays(3)]);

    $this->actingAs($this->student)->get(route('study.session'))->assertOk();

    expect($this->service->queueFor($this->student)->pluck('word')->all())
        ->toBe(['newbie', 'overdue']);
});

test('again resets to level zero, tier not-studied, is immediately due, and counts the miss', function () {
    seedDictionaryWord();
    $word = saveWord($this->student, 'wolf', ['mastery_level' => 3, 'times_correct' => 3]);

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'again'])
        ->assertOk()
        ->assertJsonPath('tier', 'not-studied')
        ->assertJsonPath('mastery_level', 0);

    $word->refresh();

    expect($word->mastery_level)->toBe(0)
        ->and($word->times_wrong)->toBe(1)
        ->and($word->times_correct)->toBe(3)
        ->and($word->due_at->lessThanOrEqualTo(now()))->toBeTrue();
});

test('good advances one level with a normal interval and counts the hit', function () {
    seedDictionaryWord('fox');
    $word = saveWord($this->student, 'fox', ['mastery_level' => 1, 'times_wrong' => 1]);

    $before = now();
    $this->travelTo($before);

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'good'])
        ->assertOk()
        ->assertJsonPath('tier', 'learning')
        ->assertJsonPath('mastery_level', 2);

    $word->refresh();

    expect($word->mastery_level)->toBe(2)
        ->and($word->times_correct)->toBe(1)
        ->and((int) round(abs($word->due_at->diffInDays($before))))->toBe(3);
});

test('hard stays at current level with a short interval', function () {
    seedDictionaryWord('fox');
    $word = saveWord($this->student, 'fox', ['mastery_level' => 2, 'times_wrong' => 1]);

    $before = now();
    $this->travelTo($before);

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'hard'])
        ->assertOk()
        ->assertJsonPath('tier', 'learning')
        ->assertJsonPath('mastery_level', 2);

    $word->refresh();

    expect($word->mastery_level)->toBe(2)
        ->and($word->times_correct)->toBe(1)
        ->and((int) round(abs($word->due_at->diffInDays($before))))->toBe(3);
});

test('easy jumps two levels with a long interval', function () {
    seedDictionaryWord('fox');
    $word = saveWord($this->student, 'fox', ['mastery_level' => 1]);

    $before = now();
    $this->travelTo($before);

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'easy'])
        ->assertOk()
        ->assertJsonPath('tier', 'strong')
        ->assertJsonPath('mastery_level', 3);

    $word->refresh();

    expect($word->mastery_level)->toBe(3)
        ->and($word->times_correct)->toBe(1)
        ->and((int) round(abs($word->due_at->diffInDays($before))))->toBe(7);
});

test('good on a brand-new word jumps to level two', function () {
    seedDictionaryWord('newbie');
    $word = saveWord($this->student, 'newbie');

    $before = now();
    $this->travelTo($before);

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'good'])
        ->assertOk()
        ->assertJsonPath('mastery_level', 2);

    $word->refresh();

    expect($word->mastery_level)->toBe(2)
        ->and($word->times_correct)->toBe(1)
        ->and((int) round(abs($word->due_at->diffInDays($before))))->toBe(3);
});

test('good at level four stays capped and keeps the long interval', function () {
    seedDictionaryWord('owl');
    $word = saveWord($this->student, 'owl', ['mastery_level' => 4, 'times_correct' => 6]);

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'good'])
        ->assertOk()
        ->assertJsonPath('mastery_level', 4);

    $word->refresh();

    expect($word->mastery_level)->toBe(4)
        ->and($word->due_at->isAfter(now()->addDays(29)))->toBeTrue();
});

test('a student cannot grade another student’s word', function () {
    $other = User::factory()->create();
    $word = saveWord($other, 'pine');

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'good'])
        ->assertNotFound();
});

test('grading rejects unknown grades', function () {
    $word = saveWord($this->student, 'ash');

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'maybe'])
        ->assertJsonValidationErrors('grade');
});

// ----------------------------------------------------------------------
// Saved word management
// ----------------------------------------------------------------------

test('words list groups chronologically newest first and shows live total', function () {
    $old = saveWord($this->student, 'ancient');
    $old->forceFill(['created_at' => now()->subDays(3)])->save();

    saveWord($this->student, 'recent');

    $this->actingAs($this->student)
        ->get(route('study.words'))
        ->assertOk()
        ->assertSee('All saved words')
        ->assertSee('ancient')
        ->assertSee('recent');

    $groups = array_keys(serviceGroups());

    expect($groups[0])->toBeGreaterThanOrEqual($groups[1]);
});

function serviceGroups(): array
{
    $controller = app(StudyController::class);
    $method = new ReflectionMethod($controller, 'groupedWords');

    return $method->invoke($controller, auth()->user(), ['q' => null, 'sort' => 'date', 'filter' => null]);
}

test('words search matches word names and definitions', function () {
    saveWord($this->student, 'harbor', ['definition' => 'a sheltered port']);
    saveWord($this->student, 'meadow');

    $this->actingAs($this->student)
        ->get(route('study.words', ['q' => 'port']))
        ->assertOk()
        ->assertSee('harbor')
        ->assertDontSee('>meadow<', false);

    $this->actingAs($this->student)
        ->get(route('study.words', ['q' => 'meadow']))
        ->assertOk()
        ->assertSee('meadow');
});

test('words can be sorted alphabetically and by mastery', function () {
    saveWord($this->student, 'zebra', ['mastery_level' => 4, 'times_correct' => 4, 'last_drilled_at' => now(), 'due_at' => now()->addDays(14)]);
    saveWord($this->student, 'apple', ['mastery_level' => 1, 'times_wrong' => 1, 'last_drilled_at' => now(), 'due_at' => now()]);

    $alpha = $this->actingAs($this->student)->get(route('study.words', ['sort' => 'alpha']));
    $content = $alpha->getContent();
    expect(strpos($content, 'apple'))->toBeLessThan(strpos($content, 'zebra'));

    $mastery = $this->actingAs($this->student)->get(route('study.words', ['sort' => 'mastery']));
    $content = $mastery->getContent();
    expect(strpos($content, 'apple'))->toBeLessThan(strpos($content, 'zebra'));
});

test('the forgotten filter narrows to failed words below Strong', function () {
    saveWord($this->student, 'rusty', ['times_wrong' => 2, 'mastery_level' => 1]);
    saveWord($this->student, 'solid', ['mastery_level' => 3, 'times_correct' => 3]);

    $this->actingAs($this->student)
        ->get(route('study.words', ['filter' => 'forgotten']))
        ->assertOk()
        ->assertSee('rusty')
        ->assertDontSee('>solid<', false);
});

test('a saved word detail page renders the dictionary entry with review stats', function () {
    seedDictionaryWord('raven');
    $word = saveWord($this->student, 'raven', ['mastery_level' => 2, 'times_correct' => 2, 'times_wrong' => 1]);

    $this->actingAs($this->student)
        ->get(route('study.words.show', $word))
        ->assertOk()
        ->assertSee('raven')
        ->assertSee('Lookups', false)
        ->assertSee('a wild carnivorous mammal (raven)', false);
});

test('a student cannot open another student’s saved word', function () {
    $other = User::factory()->create();
    $word = saveWord($other, 'secret');

    $this->actingAs($this->student)
        ->get(route('study.words.show', $word))
        ->assertNotFound();
});

// ----------------------------------------------------------------------
// Bulk actions
// ----------------------------------------------------------------------

test('bulk delete removes only the owner’s selected words', function () {
    $mine = [saveWord($this->student, 'one')->id, saveWord($this->student, 'two')->id];
    $other = saveWord(User::factory()->create(), 'foreign')->id;

    $this->actingAs($this->student)
        ->postJson(route('study.words.bulk'), ['action' => 'delete', 'ids' => [...$mine, $other]])
        ->assertOk()
        ->assertJsonPath('affected', 2);

    expect(SavedWord::count())->toBe(1);
});

test('bulk reset zeroes progress and makes words due again', function () {
    $word = saveWord($this->student, 'reset-me', [
        'mastery_level' => 4,
        'times_correct' => 7,
        'times_wrong' => 2,
        'last_drilled_at' => now(),
        'due_at' => now()->addDays(14),
    ]);

    $this->actingAs($this->student)
        ->postJson(route('study.words.bulk'), ['action' => 'reset', 'ids' => [$word->id]])
        ->assertOk();

    $word->refresh();

    expect($word->mastery_level)->toBe(0)
        ->and($word->times_correct)->toBe(0)
        ->and($word->times_wrong)->toBe(0)
        ->and($word->last_drilled_at)->toBeNull()
        ->and($word->due_at)->toBeNull();
});

test('bulk master promotes to Strong, sets last_drilled_at and pushes the due date far out', function () {
    $word = saveWord($this->student, 'promote-me', ['mastery_level' => 1]);

    $this->actingAs($this->student)
        ->postJson(route('study.words.bulk'), ['action' => 'master', 'ids' => [$word->id]])
        ->assertOk();

    $word->refresh();

    expect($word->mastery_level)->toBe(4)
        ->and($word->last_drilled_at)->not->toBeNull()
        ->and($word->due_at->isAfter(now()->addDays(13)))->toBeTrue();
});

test('bulk actions validate the action and require ids', function () {
    $this->actingAs($this->student)
        ->postJson(route('study.words.bulk'), ['action' => 'explode', 'ids' => [1]])
        ->assertJsonValidationErrors('action');

    $this->actingAs($this->student)
        ->postJson(route('study.words.bulk'), ['action' => 'delete', 'ids' => []])
        ->assertJsonValidationErrors('ids');
});

test('grading stamps the drill action into the daily activity ledger', function () {
    seedDictionaryWord('bear');
    $word = saveWord($this->student, 'bear');

    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'good'])
        ->assertOk();

    $activity = UserDailyActivity::query()
        ->where('user_id', $this->student->id)
        ->whereDate('activity_date', today())
        ->first();

    // The dead-dimension guard: a graded flashcard MUST increment drills_completed
    // so the Gym Drill task, the weekly chart's "Drills" series, and the daily
    // drill stat all stay live.
    expect($activity)->not->toBeNull()
        ->and($activity->drills_completed)->toBe(1);

    // Re-grading is a second review action, so the drill task counter climbs.
    $this->actingAs($this->student)
        ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'again'])
        ->assertOk();

    expect($activity->refresh()->drills_completed)->toBe(2);
});

test('study goal pays only through the hub claim, exactly once', function () {
    // 5 is the floor the daily goal clamps to (config/study.min_goal).
    $this->student->update(['daily_study_goal' => 5]);

    $keysBefore = (int) $this->student->total_keys;
    $expectedKeys = (int) config('gamification.goal.study_keys', 3);

    // No reviews yet: the claim endpoint refuses.
    $this->actingAs($this->student)
        ->postJson(route('hub.goal-claim'), ['goal' => 'study'])
        ->assertUnprocessable();

    $words = ['pear', 'plum', 'grape', 'fig', 'date'];

    foreach ($words as $lemma) {
        seedDictionaryWord($lemma);
        $word = saveWord($this->student, $lemma);

        $this->actingAs($this->student)
            ->postJson(route('study.grade'), ['saved_word_id' => $word->id, 'grade' => 'good'])
            ->assertOk();
    }

    $activity = UserDailyActivity::query()
        ->where('user_id', $this->student->id)
        ->whereDate('activity_date', today())
        ->first();

    // Crossing the bar no longer auto-credits anything.
    expect($this->student->refresh()->total_keys)->toBe($keysBefore)
        ->and(KeyTransaction::where('user_id', $this->student->id)->where('reason', 'study_goal')->count())->toBe(0)
        ->and($activity)->not->toBeNull()
        ->and($activity->study_goal_claimed_at)->toBeNull();

    // The claim pays out exactly once.
    $this->actingAs($this->student)
        ->postJson(route('hub.goal-claim'), ['goal' => 'study'])
        ->assertOk()
        ->assertJsonPath('keys', $expectedKeys)
        ->assertJsonPath('already', false);

    expect($this->student->refresh()->total_keys)->toBe($keysBefore + $expectedKeys)
        ->and(KeyTransaction::where('user_id', $this->student->id)->where('reason', 'study_goal')->count())->toBe(1)
        ->and($activity->refresh()->study_goal_claimed_at)->not->toBeNull();

    // A second tap is acknowledged but never double-pays.
    $this->actingAs($this->student)
        ->postJson(route('hub.goal-claim'), ['goal' => 'study'])
        ->assertOk()
        ->assertJsonPath('keys', 0)
        ->assertJsonPath('already', true);

    expect($this->student->refresh()->total_keys)->toBe($keysBefore + $expectedKeys);
});

test('study ui uses ink black text, never pale grays', function () {
    seedDictionaryWord('wolf');
    $word = saveWord($this->student, 'wolf');

    $pages = [
        route('study'),
        route('study.session'),
        route('study.words'),
        route('study.words.show', $word),
    ];

    foreach ($pages as $url) {
        $html = $this->actingAs($this->student)->get($url)->assertOk()->getContent();
        // The layout-wide TITLE LADDER modal keeps its locked-row gray.
        $content = strstr($html, 'id="rank-roadmap-modal"', true) ?: $html;

        expect($html)
            ->not->toContain('text-[#8C7B70]')
            ->and($content)
            ->not->toContain('text-[#B5A89C]');
    }
});
