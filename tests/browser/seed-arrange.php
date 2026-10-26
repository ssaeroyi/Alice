<?php

// Browser-test fixture for the tiled arrange panel. Run through tinker by
// tests/browser/arrange-panel.spec.js. Idempotent: re-running resets the
// student's battle on this story so every run starts from a fresh battle.
// Prints one marker line the spec parses; the answers come from the server
// payload, which the client never sees.

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Games\Journal\BattleService;
use App\Models\DictionaryEntry;
use App\Models\Story;
use App\Models\StoryBattle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$email = 'browser-arrange@alice.test';
$slug = 'browser-arrange-test';

$student = User::updateOrCreate(
    ['email' => $email],
    [
        'first_name' => 'Browser',
        'last_name' => 'Arrange',
        'password' => Hash::make('password'),
        'role' => Role::Student,
        'is_setup_complete' => true,
        'email_verified_at' => now(),
    ],
);

// "castle", "stood" and "tall" are the only 4-10 letter story words, so the
// arrange pool is fixed and deterministic. The extra clause feeds the
// definition pool through 3-letter words that never join the arrange pool.
$story = Story::updateOrCreate(
    ['slug' => $slug],
    [
        'title' => 'Browser Arrange Test',
        'author' => 'Test Author',
        'license' => 'Public domain',
        'content' => 'The castle stood tall. A cat sat.',
        'status' => StoryStatus::Published,
        'missions_enabled' => true,
    ],
);

$story->missionActions()->delete();
$story->missionActions()->create([
    'action_key' => 'arrange',
    'kind' => 'arrange',
    'config' => ['type' => 'arrange', 'need' => 1, 'prompt' => 'Tap the letters back into order for {need} words'],
    'enabled' => true,
    'position' => 0,
]);

// A choice move (MEANING STRIKE) for the pagination spec: the definition
// pool resolves live from the dictionary lexicon against words present in
// the prose, so seed rows for five prose words (floor is 4).
DB::table('story_vocab')->where('story_id', $story->id)->delete();
foreach ([
    ['castle', 'a big fortified building', 'noun'],
    ['stood', 'was in an upright position', 'verb'],
    ['tall', 'of more than average height', 'adj'],
    ['cat', 'a small furry pet', 'noun'],
    ['sat', 'rested on the seat', 'verb'],
] as [$word, $definition, $pos]) {
    // firstOrCreate: never clobbers a real dictionary row, only fills gaps,
    // so re-running the seed stays idempotent.
    DictionaryEntry::firstOrCreate(
        ['lemma' => $word, 'pos' => $pos, 'sense_num' => 1],
        ['definition' => $definition, 'freq' => 10],
    );
}
$story->missionActions()->create([
    'action_key' => 'defmatch',
    'kind' => 'choice',
    'config' => ['type' => 'defmatch', 'prompt' => 'Match each word to its meaning'],
    'enabled' => true,
    'position' => 1,
]);

// A find move ("castle" is the only C word): answers happen in the story text,
// the MISSIONS tab only mirrors the rule and live count.
$story->missionActions()->create([
    'action_key' => 'find-c',
    'kind' => 'find',
    'config' => [
        'rule' => ['type' => 'prefix', 'value' => 'c', 'label' => 'words starting with C'],
        'need' => 1,
        'prompt' => 'Find {need} words starting with C in this story',
    ],
    'enabled' => true,
    'position' => 2,
]);

StoryBattle::where('user_id', $student->id)->where('story_id', $story->id)->get()
    ->each(function (StoryBattle $battle) {
        $battle->actions()->delete();
        $battle->delete();
    });

$battle = app(BattleService::class)->stateFor($student, $story);
$arrange = $battle->actions->firstWhere('action_key', 'arrange');
$defmatch = $battle->actions->firstWhere('action_key', 'defmatch');
$find = $battle->actions->firstWhere('action_key', 'find-c');

echo 'ARRANGE_SEED '.json_encode([
    'email' => $email,
    'password' => 'password',
    'slug' => $slug,
    'answers' => array_column($arrange->payload['items'], 'answer'),
    'defmatchAnswers' => $defmatch ? array_column($defmatch->payload['items'], 'answer') : [],
    'findId' => $find?->id,
]).PHP_EOL;
