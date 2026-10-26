<?php

use App\Enums\StoryStatus;
use App\Models\GameRound;
use App\Models\KeyTransaction;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['is_setup_complete' => true]);
});

function saveGameWord(User $user, string $word, string $definition = 'A test word'): SavedWord
{
    return SavedWord::create([
        'user_id' => $user->id,
        'word' => $word,
        'definition' => $definition,
        'part_of_speech' => 'noun',
    ]);
}

function setMastery(User $user, string $word, int $level): void
{
    SavedWord::where('user_id', $user->id)
        ->where('word', $word)
        ->update(['mastery_level' => $level]);
}

function submitGame(object $test, array $overrides = [])
{
    $defaults = [
        'game_type' => 'match',
        'score' => 90,
        'round_id' => (string) Str::uuid(),
        'words_count' => 12,
        'correct_count' => 5,
        'difficulty' => 'medium',
        'source' => 'saved',
    ];

    return $test->actingAs($test->student)
        ->postJson(route('game.submit'), array_merge($defaults, $overrides));
}

// ----------------------------------------------------------------------
// Hub
// ----------------------------------------------------------------------

test('hub loads for authenticated student', function () {
    $this->actingAs($this->student)
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Word Search')
        ->assertSee('Memory')
        ->assertSee('Sentence')
        // Labels and descriptions on the arcade are ink black now.
        ->assertDontSee('text-[#8C7B70]');
});

test('hub shows help button', function () {
    $this->actingAs($this->student)
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('How Games Work');
});

test('hub shows the best score per game', function () {
    GameRound::create(['user_id' => $this->student->id, 'game_type' => 'wordsearch', 'score' => 40, 'words_count' => 4, 'round_id' => (string) Str::uuid()]);
    GameRound::create(['user_id' => $this->student->id, 'game_type' => 'wordsearch', 'score' => 90, 'words_count' => 6, 'round_id' => (string) Str::uuid()]);
    GameRound::create(['user_id' => $this->student->id, 'game_type' => 'match', 'score' => 70, 'words_count' => 6, 'round_id' => (string) Str::uuid()]);

    $this->actingAs($this->student)
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Best: 90')    // wordsearch max
        ->assertSee('Best: 70')    // match max
        ->assertSee('Best: -');    // sentence score pending
});

test('hub leaderboard ranks by personal best and breaks ties by earliest round', function () {
    // Two other readers with the same best score; the one who reached it
    // first must rank higher. The signed-in student sits at the top.
    $otherA = User::factory()->create(['nickname' => 'ReaderA']);
    $otherB = User::factory()->create(['nickname' => 'ReaderB']);

    GameRound::create(['user_id' => $this->student->id, 'game_type' => 'match', 'score' => 90, 'words_count' => 6, 'round_id' => (string) Str::uuid()]);
    // ReaderA reached 70 first.
    $createdA = now()->subDay();
    GameRound::create(['user_id' => $otherA->id, 'game_type' => 'match', 'score' => 70, 'words_count' => 6, 'round_id' => (string) Str::uuid(), 'created_at' => $createdA]);
    // ReaderB also has best 70 but reached it later.
    $createdB = now()->subHours(1);
    GameRound::create(['user_id' => $otherB->id, 'game_type' => 'match', 'score' => 70, 'words_count' => 6, 'round_id' => (string) Str::uuid(), 'created_at' => $createdB]);

    $this->actingAs($this->student)
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee('Weekly Leaderboards')
        ->assertSeeInOrder(['ReaderA', 'ReaderB']);
});

test('hub leaderboard reports the signed-in student own rank and best', function () {
    GameRound::create(['user_id' => $this->student->id, 'game_type' => 'sentence', 'score' => 55, 'words_count' => 5, 'round_id' => (string) Str::uuid()]);

    $this->actingAs($this->student)
        ->get(route('mini-game'))
        ->assertOk()
        ->assertSee("You're #1 (55)", false);
});

test('match result screen drops ALICE bubble and status badge', function () {
    // The match view always renders (synonym pairs seed globally); wordsearch
    // and sentence redirect when the fresh-DB vocabulary pool is too thin.
    $this->actingAs($this->student)
        ->get(route('game.match'))
        ->assertOk()
        ->assertDontSee('alice-lt-talking.png')
        ->assertDontSee('result-badge');
});

// ----------------------------------------------------------------------
// Setup
// ----------------------------------------------------------------------

test('setup loads for authenticated student', function () {
    $this->actingAs($this->student)
        ->get(route('game.setup'))
        ->assertOk()
        ->assertSee('Word Source')
        ->assertSee('Difficulty');
});

test('wordsearch setup shows the how-to-play help button and story picker', function () {
    $this->actingAs($this->student)
        ->get(route('game.setup', ['game' => 'wordsearch']))
        ->assertOk()
        ->assertSee('How to Play')
        ->assertSee('story-picker-modal')
        ->assertSee('Pick a Story');
});

test('match view carries the memorize phase config', function () {
    $this->actingAs($this->student)
        ->get(route('game.match'))
        ->assertOk()
        ->assertSee('data-memorize-seconds', false)
        ->assertSee('Memorize the pairs');
});

test('wordsearch ?story= serves that story words with its title', function () {
    $story = Story::create(['title' => 'The Jade Compass', 'status' => StoryStatus::Published]);
    $words = ['anchor', 'compass', 'jade', 'tide', 'reef', 'map', 'sail', 'coast', 'gull', 'mist'];

    foreach ($words as $i => $word) {
        DB::table('story_vocab')->insert([
            'story_id' => $story->id,
            'word' => $word,
            'definition' => "Definition of $word",
            'part_of_speech' => 'noun',
            'frequency_tier' => $i < 3 ? 'easy' : 'medium',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($this->student)
        ->get(route('game.wordsearch', ['source' => 'open', 'difficulty' => 'medium', 'story' => $story->id]))
        ->assertOk()
        ->assertSee("Words from: {$story->title}")
        ->assertSee('anchor');
});

test('wordsearch ?story= with an unknown story bounces back to setup', function () {
    $this->actingAs($this->student)
        ->get(route('game.wordsearch', ['source' => 'open', 'difficulty' => 'medium', 'story' => 999999]))
        ->assertRedirect(route('game.setup', ['game' => 'wordsearch']))
        ->assertSessionHas('error');
});

// ----------------------------------------------------------------------
// Game views — match (synonym pairs)
// ----------------------------------------------------------------------

test('match loads with synonym pairs', function () {
    $this->actingAs($this->student)
        ->get(route('game.match'))
        ->assertOk()
        ->assertSee('data-game-pairs', false);
});

// ----------------------------------------------------------------------
// Hint
// ----------------------------------------------------------------------

test('hint charges keys and returns new balance', function () {
    $this->student->update(['total_keys' => 20]);

    $this->actingAs($this->student)
        ->postJson(route('game.hint'), ['game_type' => 'match'])
        ->assertOk()
        ->assertJsonPath('spent', 5);

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(15);
});

test('hint rejects when not enough keys', function () {
    $this->student->update(['total_keys' => 2]);

    $this->actingAs($this->student)
        ->postJson(route('game.hint'), ['game_type' => 'match'])
        ->assertStatus(422);

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(2);
});

test('hint rejects invalid game type', function () {
    $this->actingAs($this->student)
        ->postJson(route('game.hint'), ['game_type' => 'nope'])
        ->assertUnprocessable();
});

// ----------------------------------------------------------------------
// Submit
// ----------------------------------------------------------------------

test('submit requires all fields', function () {
    $this->actingAs($this->student)
        ->postJson(route('game.submit'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_type', 'score', 'round_id', 'words_count', 'correct_count', 'difficulty', 'source']);
});

test('submit rejects invalid game_type', function () {
    $this->actingAs($this->student)
        ->postJson(route('game.submit'), [
            'game_type' => 'invalid',
            'score' => 50,
            'round_id' => Str::uuid(),
            'words_count' => 6,
            'correct_count' => 3,
            'difficulty' => 'medium',
            'source' => 'saved',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['game_type']);
});

test('submit rejects score above the 999 arcade cap', function () {
    $this->actingAs($this->student)
        ->postJson(route('game.submit'), [
            'game_type' => 'sentence',
            'score' => 1000,
            'round_id' => Str::uuid(),
            'words_count' => 5,
            'correct_count' => 5,
            'difficulty' => 'medium',
            'source' => 'open',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['score']);
});

test('submit accepts sentence arcade scores above 100 (up to 999)', function () {
    $roundId = (string) Str::uuid();

    $this->actingAs($this->student)
        ->postJson(route('game.submit'), [
            'game_type' => 'sentence',
            'score' => 500,
            'round_id' => $roundId,
            'words_count' => 5,
            'correct_count' => 5,
            'difficulty' => 'hard',
            'source' => 'open',
        ])->assertOk();

    $round = GameRound::where('round_id', $roundId)->sole();
    expect($round->game_type)->toBe('sentence')
        ->and($round->score)->toBe(500);

    $this->student->refresh();
    expect($this->student->points)->toBeGreaterThan(0);
});

test('submit records round and credits points for excellent score', function () {
    $roundId = (string) Str::uuid();

    submitGame($this, [
        'round_id' => $roundId,
        'score' => 90,
    ])->assertOk();

    $round = GameRound::where('round_id', $roundId)->sole();
    expect($round->game_type)->toBe('match')
        ->and($round->score)->toBe(90);

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(0) // games award points only (S1)
        ->and($this->student->points)->toBeGreaterThan(0);
});

test('submit records round and credits points for good score', function () {
    $roundId = (string) Str::uuid();

    submitGame($this, [
        'round_id' => $roundId,
        'score' => 60,
    ])->assertOk();

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(0)
        ->and($this->student->points)->toBe(4);
});

test('submit records round and credits minimum points for poor score', function () {
    $roundId = (string) Str::uuid();

    submitGame($this, [
        'round_id' => $roundId,
        'score' => 20,
    ])->assertOk();

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(0)
        ->and($this->student->points)->toBe(2);
});

test('submit rejects duplicate round_id (idempotency)', function () {
    $roundId = (string) Str::uuid();

    submitGame($this, ['round_id' => $roundId])->assertOk();

    submitGame($this, ['round_id' => $roundId])->assertStatus(409);
});

test('submit stamps the day on first game of the day', function () {
    submitGame($this, ['score' => 70])->assertOk();

    $this->student->refresh();
    expect($this->student->current_streak)->toBe(1)
        ->and($this->student->last_stamped_date->toDateString())->toBe(now()->toDateString());
});

test('submit never writes a key ledger row for games (points only)', function () {
    submitGame($this, ['score' => 85])->assertOk();

    expect(KeyTransaction::query()->where('user_id', $this->student->id)->count())->toBe(0);
});

test('submit increments game_rounds counter in daily activity', function () {
    submitGame($this, ['score' => 70])->assertOk();

    $activity = UserDailyActivity::query()
        ->where('user_id', $this->student->id)
        ->whereDate('activity_date', now()->toDateString())
        ->sole();

    expect($activity->game_rounds)->toBe(1);
});

test('submit awards points by performance tier regardless of difficulty', function () {
    submitGame($this, [
        'score' => 90,
        'correct_count' => 5,
        'difficulty' => 'hard',
    ])->assertOk();

    $this->student->refresh();
    expect($this->student->points)->toBe(6);
});

// ----------------------------------------------------------------------
// B3 same-day decay
// ----------------------------------------------------------------------

test('submit applies B3 same-day decay on repeated game rounds', function () {
    $this->actingAs($this->student);

    submitGame($this, ['score' => 85])->assertOk();

    $this->student->refresh();
    $pointsAfterFirst = $this->student->points;

    submitGame($this, [
        'score' => 85,
        'round_id' => (string) Str::uuid(),
    ])->assertOk();

    $this->student->refresh();
    $pointsGained = $this->student->points - $pointsAfterFirst;

    expect($pointsGained)->toBeLessThan($pointsAfterFirst);
    expect($pointsGained)->toBe((int) round(6 * 0.75));
});
