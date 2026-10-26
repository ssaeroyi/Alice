<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Games\Journal\AuthoredActions;
use App\Games\Journal\BattleService;
use App\Games\Journal\Generators\AffixGenerator;
use App\Games\Journal\Generators\AlphabetizeGenerator;
use App\Games\Journal\Generators\ArrangeGenerator;
use App\Games\Journal\Generators\DoublesGenerator;
use App\Games\Journal\Generators\FindGenerator;
use App\Games\Journal\Generators\LadderGenerator;
use App\Games\Journal\Generators\RiddleGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function missionJournalTeacher(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

function missionJournalStudent(): User
{
    return User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
}

function missionJournalStory(string $content): Story
{
    return Story::create([
        'title' => 'Mission Journal Story',
        'author' => 'Jane Tester',
        'license' => 'Public domain',
        'content' => $content,
        'status' => StoryStatus::Published,
    ]);
}

/** Rich story: 7 distinct -ed words, plenty for a 3-word find move. */
function missionJournalRichText(): string
{
    return 'The knight walked swiftly to the castle. He jumped over the gate and smiled at the guards. '
        .'The excited crowd cheered loudly while the tired soldiers rested near the wall.';
}

/** Thin story: only 2 distinct -ed words, so a need of 3 must be rejected. */
function missionJournalThinText(): string
{
    return 'He walked and smiled by the gate.';
}

test('teacher adds a journal action for the story pool', function () {
    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'suffix',
            'rule_value' => 'ed',
            'need' => 3,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'find-ed',
        'kind' => 'find',
        'enabled' => true,
    ]);
});

test('journal action need cannot exceed the story word count', function () {
    $story = missionJournalStory(missionJournalThinText());

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'suffix',
            'rule_value' => 'ed',
            'need' => 3,
        ])
        ->assertSessionHasErrors('need');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('the same journal action rule cannot be added twice', function () {
    $story = missionJournalStory(missionJournalRichText());

    $payload = ['rule_type' => 'suffix', 'rule_value' => 'ed', 'need' => 3];

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), $payload)
        ->assertRedirect();

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), $payload)
        ->assertSessionHasErrors('rule_type');

    $this->assertDatabaseCount('story_mission_actions', 1);
});

test('unknown rule types are rejected', function () {
    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'injected',
            'rule_value' => 'zz',
            'need' => 3,
        ])
        ->assertSessionHasErrors('rule_type');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('battle draws the authored action first then pads to three unique moves', function () {
    $story = missionJournalStory(missionJournalRichText());

    $story->missionActions()->create([
        'action_key' => 'find-ed',
        'kind' => 'find',
        'config' => ['rule' => ['type' => 'suffix', 'value' => 'ed', 'label' => 'words ending in -ED'], 'need' => 3],
        'enabled' => true,
        'position' => 0,
    ]);

    $student = missionJournalStudent();
    $battle = app(BattleService::class)->stateFor($student, $story);

    expect($battle->actions)->toHaveCount(3);
    expect($battle->actions->pluck('action_key')->all())->toHaveCount(3); // sanity
    expect($battle->actions->pluck('action_key')->unique()->count())->toBe(3);
    expect($battle->actions->first()->action_key)->toBe('find-ed');
});

test('battle without authored actions still builds three auto moves', function () {
    $story = missionJournalStory(missionJournalRichText());

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);

    expect($battle->actions)->toHaveCount(3);
    expect($battle->actions->pluck('action_key')->unique()->count())->toBe(3);
    // Slot 1 is always find; the rest come from the story-derived pool
    // (fill for affix, choice for alphabetize).
    $kinds = $battle->actions->pluck('kind')->unique()->values()->all();
    expect($kinds)->toContain('find');
    expect(array_diff($kinds, ['find', 'fill', 'choice', 'arrange']))->toBe([]);
});

test('an authored action that no longer fits the text is skipped and auto-padded', function () {
    $story = missionJournalStory('The singing bird sat quietly near the little brook. Quickly it flew away toward a green leaf.');

    $story->missionActions()->create([
        'action_key' => 'find-ed',
        'kind' => 'find',
        'config' => ['rule' => ['type' => 'suffix', 'value' => 'ed', 'label' => 'words ending in -ED'], 'need' => 3],
        'enabled' => true,
        'position' => 0,
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);

    expect($battle->actions)->toHaveCount(3);
    expect($battle->actions->pluck('action_key')->all())->not->toContain('find-ed');
    expect($battle->actions->pluck('action_key')->unique()->count())->toBe(3);
});

test('teacher toggles journal battles per story and the student route follows the flag', function () {
    $story = missionJournalStory(missionJournalRichText());
    $teacher = missionJournalTeacher();
    $student = missionJournalStudent();

    expect($story->fresh()->missions_enabled)->toBeFalse();

    $this->actingAs($student)
        ->get(route('reader.battle.state', $story->slug))
        ->assertNotFound();

    $this->actingAs($teacher)
        ->post(route('teacher.stories.missions.toggle', $story))
        ->assertRedirect();

    expect($story->fresh()->missions_enabled)->toBeTrue();

    $this->actingAs($student)
        ->get(route('reader.battle.state', $story->slug))
        ->assertOk();

    $this->actingAs($teacher)
        ->post(route('teacher.stories.missions.toggle', $story))
        ->assertRedirect();

    expect($story->fresh()->missions_enabled)->toBeFalse();
});

test('teacher edits and deletes a journal action', function () {
    $story = missionJournalStory(missionJournalRichText());
    $teacher = missionJournalTeacher();

    $action = $story->missionActions()->create([
        'action_key' => 'find-ed',
        'kind' => 'find',
        'config' => ['rule' => ['type' => 'suffix', 'value' => 'ed', 'label' => 'words ending in -ED'], 'need' => 3],
        'enabled' => true,
        'position' => 0,
    ]);

    $this->actingAs($teacher)
        ->put(route('teacher.stories.mission-actions.update', $action), [
            'rule_type' => 'suffix',
            'rule_value' => 'ed',
            'need' => 2,
        ])
        ->assertRedirect();

    expect($action->fresh()->config['need'])->toBe(2);

    $this->actingAs($teacher)
        ->delete(route('teacher.stories.mission-actions.destroy', $action))
        ->assertRedirect();

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('story editor shows the journal tab with the feasibility gallery', function () {
    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('Field Journal actions')
        ->assertSee('words ending in -ED')
        ->assertSee('Scan the story')
        ->assertSee('mission-action-modal');

    expect($story->fresh()->mission_features)->toBeArray();
    expect($story->fresh()->mission_features['suffix:ed'] ?? 0)->toBeGreaterThan(0);
});

test('sweep migration enables the journal for published stories only', function () {
    $published = missionJournalStory(missionJournalRichText());
    $draft = Story::create([
        'title' => 'Draft Journal Story',
        'author' => 'Jane Tester',
        'license' => 'Public domain',
        'content' => missionJournalRichText(),
        'status' => StoryStatus::Draft,
    ]);

    $migration = include database_path('migrations/2026_10_23_100002_enable_missions_for_published_stories.php');
    $migration->up();

    expect($published->fresh()->missions_enabled)->toBeTrue();
    expect($draft->fresh()->missions_enabled)->toBeFalse();
});

/* ------------------------------ Wave A: new types ----------------------------- */

/**
 * Seed the rows the sense moves resolve against: story_vocab (the extract
 * command is out of band here) AND the dictionary entries the live
 * pos/defmatch lexicon reads at instantiation time.
 */
function missionJournalSeedVocab(Story $story, array $rows): void
{
    foreach ($rows as [$word, $definition, $pos]) {
        DB::table('story_vocab')->insert([
            'story_id' => $story->id,
            'word' => $word,
            'definition' => $definition,
            'part_of_speech' => $pos,
            'freq' => 10,
            'frequency_tier' => 'medium',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DictionaryEntry::query()->create([
            'lemma' => $word,
            'pos' => $pos,
            'sense_num' => 1,
            'definition' => $definition,
            'freq' => 10,
        ]);
    }
}

/** 5 verbs + 5 nouns, each with a simplified definition. */
function missionJournalVocabRows(): array
{
    return [
        ['walked', 'moved on foot', 'verb'],
        ['jumped', 'leaped into the air', 'verb'],
        ['smiled', 'showed a happy face', 'verb'],
        ['cheered', 'shouted with joy', 'verb'],
        ['rested', 'stopped to sleep', 'verb'],
        ['knight', 'a warrior in armor', 'noun'],
        ['castle', 'a big fortified building', 'noun'],
        ['crowd', 'a large group of people', 'noun'],
        ['soldier', 'a person in an army', 'noun'],
        ['gate', 'a movable barrier', 'noun'],
    ];
}

test('teacher adds a pos hunt action and it stores as a set rule', function () {
    $story = missionJournalStory(missionJournalRichText());
    missionJournalSeedVocab($story, missionJournalVocabRows());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'pos',
            'rule_value' => 'verb',
            'need' => 3,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'pos-verb',
        'kind' => 'find',
    ]);

    $config = $story->missionActions()->first()->config;
    expect($config['rule']['type'])->toBe('pos');
    expect($config['rule']['value'])->toBe('verb');
    expect($config['need'])->toBe(3);
});

test('pos hunt need cannot exceed the tagged word count', function () {
    $story = missionJournalStory(missionJournalRichText());
    missionJournalSeedVocab($story, [
        ['walked', 'moved on foot', 'verb'],
        ['jumped', 'leaped into the air', 'verb'],
    ]);

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'pos',
            'rule_value' => 'verb',
            'need' => 3,
        ])
        ->assertSessionHasErrors('need');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

// Meaning match is temporarily hidden; re-enable with the catalog row.
test('defmatch action requires a live definition pool', function () {
    $this->markTestSkipped('Meaning match is temporarily hidden.');

    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'defmatch',
            'need' => 1,
        ])
        ->assertSessionHasErrors('rule_type');

    missionJournalSeedVocab($story, missionJournalVocabRows());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'defmatch',
            'need' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'defmatch',
        'kind' => 'choice',
    ]);
});

test('sentblank action requires three blankable sentences', function () {
    $thin = missionJournalStory('He walked and smiled by the gate.');

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $thin))
        ->post(route('teacher.stories.mission-actions.store', $thin), [
            'rule_type' => 'sentblank',
            'need' => 1,
        ])
        ->assertSessionHasErrors('rule_type');

    $rich = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $rich), [
            'rule_type' => 'sentblank',
            'need' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $rich->id,
        'action_key' => 'sentblank',
        'kind' => 'fill',
    ]);
});

test('teacher adds an authored-only length find rule', function () {
    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'length',
            'rule_value' => '4',
            'need' => 3,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'find-4',
        'kind' => 'find',
    ]);
});

test('auto-generation never picks authored-only length rules', function () {
    $profile = new \App\Games\Journal\StoryProfile(missionJournalRichText());

    foreach ((new FindGenerator())->bestRules($profile, 9) as [, $rule]) {
        expect($rule['type'])->not->toBe('length');
    }
});

test('battle instantiates an authored defmatch move without leaking answers', function () {
    $story = missionJournalStory(missionJournalRichText());
    missionJournalSeedVocab($story, missionJournalVocabRows());

    $story->missionActions()->create([
        'action_key' => 'defmatch',
        'kind' => 'choice',
        'config' => ['type' => 'defmatch', 'prompt' => 'Match each word to its meaning'],
        'enabled' => true,
        'position' => 0,
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);
    $action = $battle->actions->firstWhere('action_key', 'defmatch');

    expect($action)->not->toBeNull();
    expect($action->kind)->toBe('choice');
    expect($action->payload['items'])->toHaveCount(3);
    expect($action->public_payload['items'])->toHaveCount(3);

    foreach ($action->payload['items'] as $item) {
        expect($item)->toHaveKey('q')->toHaveKey('answer')->toHaveKey('choices');
        expect($item['choices'])->toHaveCount(3);
        expect($item['choices'])->toContain($item['answer']);
    }

    foreach ($action->public_payload['items'] as $item) {
        expect($item)->not->toHaveKey('answer');
        expect($item)->toHaveKey('q')->toHaveKey('choices');
    }
});

test('battle instantiates an authored sentblank move', function () {
    $story = missionJournalStory(missionJournalRichText());

    $story->missionActions()->create([
        'action_key' => 'sentblank',
        'kind' => 'fill',
        'config' => ['type' => 'sentblank', 'prompt' => 'Type the missing word from each sentence'],
        'enabled' => true,
        'position' => 0,
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);
    $action = $battle->actions->firstWhere('action_key', 'sentblank');

    expect($action)->not->toBeNull();
    expect($action->kind)->toBe('fill');
    expect($action->payload['items'])->toHaveCount(3);

    foreach ($action->payload['items'] as $item) {
        expect($item)->toHaveKey('shown')->toHaveKey('answer');
        expect($item['shown'])->toContain('___');
        expect($item['answer'])->not->toBe('');
    }

    foreach ($action->public_payload['items'] as $item) {
        expect($item)->not->toHaveKey('answer');
        expect($item)->toHaveKey('shown');
    }
});

test('battle instantiates an authored pos hunt with a runtime set rule', function () {
    $story = missionJournalStory(missionJournalRichText());
    missionJournalSeedVocab($story, missionJournalVocabRows());

    $story->missionActions()->create([
        'action_key' => 'pos-verb',
        'kind' => 'find',
        'config' => [
            'rule' => ['type' => 'pos', 'value' => 'verb', 'label' => 'verbs'],
            'need' => 3,
            'prompt' => 'Find {need} verbs in this story',
        ],
        'enabled' => true,
        'position' => 0,
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);
    $action = $battle->actions->firstWhere('action_key', 'pos-verb');

    expect($action)->not->toBeNull();
    expect($action->kind)->toBe('find');
    expect($action->payload['rule']['type'])->toBe('set');
    expect($action->payload['rule']['tokens'])->toContain('walked');
    expect($action->payload['need'])->toBe(3);

    // The same wordMatches the client mirror uses grades the tap.
    expect(FindGenerator::wordMatches($action->payload['rule'], 'walked'))->toBeTrue();
    expect(FindGenerator::wordMatches($action->payload['rule'], 'castle'))->toBeFalse();
});

test('wordMatches supports the length and set rule types', function () {
    expect(FindGenerator::wordMatches(['type' => 'length', 'value' => '4'], 'gate'))->toBeTrue();
    expect(FindGenerator::wordMatches(['type' => 'length', 'value' => '4'], 'castle'))->toBeFalse();
    expect(FindGenerator::wordMatches(['type' => 'set', 'tokens' => ['walked']], 'walked'))->toBeTrue();
    expect(FindGenerator::wordMatches(['type' => 'set', 'tokens' => ['walked']], 'rested'))->toBeFalse();
});

test('a multi-POS word counts for every POS the lexicon lists', function () {
    $story = missionJournalStory('We clean the room each day and leave it clean.');

    DictionaryEntry::query()->create([
        'lemma' => 'clean', 'pos' => 'verb', 'sense_num' => 1,
        'definition' => 'make free of dirt', 'freq' => 50,
    ]);
    DictionaryEntry::query()->create([
        'lemma' => 'clean', 'pos' => 'adj', 'sense_num' => 1,
        'definition' => 'free from dirt', 'freq' => 50,
    ]);

    expect(AuthoredActions::posTokens($story, 'verb'))->toContain('clean');
    expect(AuthoredActions::posTokens($story, 'adj'))->toContain('clean');
    expect(AuthoredActions::posTokens($story, 'noun'))->not->toContain('clean');
});

test('a pos hunt resolves inflected forms through the dictionary forms table', function () {
    $story = missionJournalStory('The wolves circled the flock at dusk.');

    $wolf = DictionaryEntry::query()->create([
        'lemma' => 'wolf', 'pos' => 'noun', 'sense_num' => 1,
        'definition' => 'a wild carnivorous mammal', 'freq' => 30,
    ]);
    DictionaryForm::query()->create(['form' => 'wolves', 'entry_id' => $wolf->id]);

    expect(AuthoredActions::posTokens($story, 'noun'))->toContain('wolves');
});

test('defmatch definitions are POS-matching and deterministic', function () {
    $story = missionJournalStory('The knight polished the bright armor before dawn.');

    // Single-POS lemma: its only row is the pool's definition.
    DictionaryEntry::query()->create([
        'lemma' => 'knight', 'pos' => 'noun', 'sense_num' => 1,
        'definition' => 'a man who served as a mounted soldier', 'freq' => 20,
    ]);
    // Multi-POS lemma: lowest sense then alphabetical POS wins, never an arbitrary row.
    DictionaryEntry::query()->create([
        'lemma' => 'bright', 'pos' => 'noun', 'sense_num' => 1,
        'definition' => 'an artist paint', 'freq' => 5,
    ]);
    DictionaryEntry::query()->create([
        'lemma' => 'bright', 'pos' => 'adj', 'sense_num' => 1,
        'definition' => 'giving out or reflecting a lot of light', 'freq' => 40,
    ]);

    $pool = AuthoredActions::definitionPool($story);
    $byWord = array_column($pool, 'definition', 'word');

    expect($byWord['knight'])->toBe('a man who served as a mounted soldier');
    expect($byWord['bright'])->toBe('giving out or reflecting a lot of light');
    // Single-POS words lead the pool so ambiguity never crowds them out.
    expect(array_column($pool, 'word')[0])->toBe('knight');
});

test('story editor groups the new action families in the gallery', function () {
    $story = missionJournalStory(missionJournalRichText());
    missionJournalSeedVocab($story, missionJournalVocabRows());

    $this->actingAs(missionJournalTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('Scan the story')
        ->assertSee('Word sense')
        ->assertSee('Build the sentence')
        ->assertSee('words tagged as verbs')
        // Meaning match is temporarily hidden (see AuthoredActions::catalog).
        ->assertDontSee('Match words to their meanings');

    $snapshot = $story->fresh()->mission_features;
    expect($snapshot['pos:verb'] ?? 0)->toBe(5);
    expect($snapshot)->not->toHaveKey('defmatch');
    expect($snapshot['sentblank'] ?? 0)->toBeGreaterThan(0);
});

test('sentblank blanks only plain interior words', function () {
    $blanks = AuthoredActions::blankCandidates(missionJournalRichText());

    expect($blanks)->not->toBeEmpty();

    foreach ($blanks as $blank) {
        expect($blank['shown'])->toContain('___');
        expect(preg_match('/^\p{L}+$/u', $blank['answer']))->toBe(1);
        expect(mb_strlen($blank['answer']))->toBeGreaterThanOrEqual(4);
    }
});

/* ------------------------------ Wave B: arrange + initial --------------------- */

test('teacher adds an initial-letter find action for a letter', function () {
    $this->markTestSkipped('ABC (initial letter) move is temporarily hidden.');

    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'initial',
            'rule_value' => 'c',
            'need' => 2,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'find-c',
        'kind' => 'find',
    ]);

    $config = $story->missionActions()->first()->config;
    expect($config['rule']['type'])->toBe('prefix');
    expect($config['rule']['value'])->toBe('c');

    // Two-letter starts share the path: "th" is the only th-word in the
    // rich text, so need 2 is rejected and need 1 is accepted.
    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'initial',
            'rule_value' => 'th',
            'need' => 2,
        ])
        ->assertSessionHasErrors('need');

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'initial',
            'rule_value' => 'th',
            'need' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'find-th',
    ]);
});

test('initial letter need cannot exceed that letter word count', function () {
    $this->markTestSkipped('ABC (initial letter) move is temporarily hidden.');

    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'initial',
            'rule_value' => 'c', // rich text has exactly 3 c-words
            'need' => 4,
        ])
        ->assertSessionHasErrors('need');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('invalid initial letters are rejected', function () {
    $this->markTestSkipped('ABC (initial letter) move is temporarily hidden.');

    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'initial',
            'rule_value' => '7',
            'need' => 1,
        ])
        ->assertSessionHasErrors('rule_value');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('the same initial letter cannot be added twice', function () {
    $this->markTestSkipped('ABC (initial letter) move is temporarily hidden.');

    $story = missionJournalStory(missionJournalRichText());

    $payload = ['rule_type' => 'initial', 'rule_value' => 'c', 'need' => 2];

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), $payload)
        ->assertRedirect();

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), $payload)
        ->assertSessionHasErrors('rule_type');

    $this->assertDatabaseCount('story_mission_actions', 1);
});

test('teacher adds an arrange scramble action', function () {
    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'arrange',
            'need' => 3,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'arrange',
        'kind' => 'arrange',
    ]);
});

test('arrange action needs a scramble pool in the text', function () {
    $story = missionJournalStory('A cat sat.');

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'arrange',
            'need' => 3,
        ])
        ->assertSessionHasErrors('rule_type');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('battle instantiates an authored arrange move with scrambled words', function () {
    $story = missionJournalStory(missionJournalRichText());

    $story->missionActions()->create([
        'action_key' => 'arrange',
        'kind' => 'fill',
        'config' => ['type' => 'arrange', 'need' => 3, 'prompt' => 'Unscramble {need} words from the story'],
        'enabled' => true,
        'position' => 0,
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);
    $action = $battle->actions->firstWhere('action_key', 'arrange');

    expect($action)->not->toBeNull();
    expect($action->kind)->toBe('arrange');
    expect($action->payload['items'])->toHaveCount(3);

    foreach ($action->payload['items'] as $item) {
        $tiles = $item['tiles'];
        expect(implode('', $tiles))->not->toBe($item['answer']);
        $sortedTiles = $tiles;
        $answerLetters = str_split($item['answer']);
        sort($sortedTiles);
        sort($answerLetters);
        expect($sortedTiles)->toBe($answerLetters);
    }

    foreach ($action->public_payload['items'] as $item) {
        expect($item)->not->toHaveKey('answer');
        expect($item)->toHaveKey('tiles');
    }
});

test('arrange grades the tiles rebuilt in order and rejects a wrong order', function () {
    $story = missionJournalStory(missionJournalRichText());

    $story->missionActions()->create([
        'action_key' => 'arrange',
        'kind' => 'arrange',
        'config' => ['type' => 'arrange', 'need' => 2, 'prompt' => 'Tap the letters back into order for {need} words'],
        'enabled' => true,
        'position' => 0,
    ]);

    $student = missionJournalStudent();
    $service = app(BattleService::class);
    $battle = $service->stateFor($student, $story);
    $action = $battle->actions->firstWhere('action_key', 'arrange');

    $service->startAction($student, $story, $action->id);

    $answers = array_column($action->payload['items'], 'answer');
    $wrong = array_map(fn (string $w) => strrev($w).'x', $answers);

    expect($service->completeAction($student, $story, $action->id, ['items' => $wrong])['correct'])->toBeFalse();
    expect($service->completeAction($student, $story, $action->id, ['items' => $answers])['correct'])->toBeTrue();
});

test('wordMatches supports the prefix rule type', function () {
    expect(FindGenerator::wordMatches(['type' => 'prefix', 'value' => 'b'], 'by'))->toBeTrue();
    expect(FindGenerator::wordMatches(['type' => 'prefix', 'value' => 'b'], 'castle'))->toBeFalse();
    expect(FindGenerator::wordMatches(['type' => 'prefix', 'value' => 'B'], 'by'))->toBeTrue();
});

test('scrambleWord never returns the original word', function () {
    foreach (['knight', 'castle', 'level', 'a', 'bb'] as $word) {
        $scrambled = AuthoredActions::scrambleWord($word);

        if (mb_strlen($word) > 1 && count(array_unique(str_split($word))) > 1) {
            expect($scrambled)->not->toBe($word);
        }

        $a = str_split($scrambled);
        $b = str_split($word);
        sort($a);
        sort($b);
        expect($a)->toBe($b);
    }
});

test('story editor shows the initial-letter grid and the arrange tile', function () {
    $this->markTestSkipped('ABC (initial letter) move is temporarily hidden.');

    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('data-mission-letter-grid', false)
        ->assertSee('words starting with a chosen letter')
        ->assertSee('Unscramble words from the story')
        ->assertSee('LETTER SCRAMBLE');
});

/* ------------------------------ leak + affix family --------------------------- */

/** Dictionary lemmas, plus inflected forms mapped to a lemma (form => lemma). */
function missionJournalSeedDictionary(array $lemmas, array $forms = []): void
{
    foreach ($lemmas as $lemma) {
        DictionaryEntry::create([
            'lemma' => $lemma,
            'pos' => 'verb',
            'sense_num' => 1,
            'definition' => 'test sense',
            'freq' => 10,
        ]);
    }

    foreach ($forms as $form => $lemma) {
        $entry = DictionaryEntry::where('lemma', $lemma)->firstOrFail();
        DictionaryForm::create(['form' => $form, 'entry_id' => $entry->id]);
    }
}

/** Story with 4 valid ex-words: excited (cited), explain (plain), express (press), exact (act). */
function missionJournalAffixText(): string
{
    return 'Excited kids explain the plan. Express the plan. Exact plans press on.';
}

test('no generator sends answers to the client', function () {
    $story = new Story(['content' => 'The little bell rang and the soccer ball rolled. Sally sat by the cottage.']);
    $profile = new StoryProfile($story->content);

    $generators = [
        new RiddleGenerator(),
        new LadderGenerator(),
        new DoublesGenerator('story'),
        new DoublesGenerator('classic'),
        new AlphabetizeGenerator(),
        new ArrangeGenerator(),
    ];

    foreach ($generators as $generator) {
        $row = $generator->generate($profile, $story);

        expect($row['payload']['items'][0])->toHaveKey('answer');

        foreach ($row['public_payload']['items'] as $item) {
            expect($item)->not->toHaveKey('answer');
        }
    }
});

test('affix generator blanks a dictionary-checked prefix and keeps the answer server-side', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = new Story(['content' => missionJournalAffixText()]);
    $profile = new StoryProfile($story->content);
    $generator = new AffixGenerator();

    expect($generator->feasibility($profile))->toBeGreaterThan(0);

    $row = $generator->generate($profile, $story);

    expect($row['kind'])->toBe('fill');
    expect($row['key'])->toBe('affix-ex');
    expect($row['flavor'])->toBe('PREFIX PUZZLE');

    foreach ($row['payload']['items'] as $item) {
        expect($item['answer'])->toBe('ex');
        expect($item['shown'])->toStartWith('__');
    }

    foreach ($row['public_payload']['items'] as $item) {
        expect($item)->not->toHaveKey('answer');
        expect($item['shown'])->toStartWith('__');
    }
});

test('affix stems that are not dictionary words are never offered', function () {
    // Nothing seeded: "otic" and "hibit" are not words, so no affix is feasible.
    $story = new Story(['content' => 'Exotic exhibit near the hall.']);
    $profile = new StoryProfile($story->content);

    expect((new AffixGenerator())->feasibility($profile))->toBe(0);
});

test('battle includes a dictionary-checked affix move alongside find', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    // Only find and affix are in play, so the weighted draw is deterministic.
    config(['missions.generators' => ['find', 'affix']]);

    $story = missionJournalStory(missionJournalAffixText().' The knight walked swiftly to the castle.');

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);
    $affix = $battle->actions->first(fn ($action) => str_starts_with($action->action_key, 'affix-'));

    expect($battle->actions)->toHaveCount(3);
    expect($battle->actions->first()->kind)->toBe('find');
    expect($affix)->not->toBeNull();
    expect($affix->kind)->toBe('fill');
    expect($affix->action_key)->toBe('affix-ex');
    expect($affix->public_payload['items'])->toHaveCount(3);
});

test('an affix is offered with only two valid words', function () {
    missionJournalSeedDictionary(['beau', 'safe']);

    $profile = new StoryProfile('Beauty and safety matter.');
    $feasible = (new AffixGenerator())->feasibleAffixes($profile);

    expect($feasible)->toHaveKey('ty');
    expect(count($feasible['ty']['items']))->toBe(2);

    $row = (new AffixGenerator())->generate($profile, new Story(['content' => 'Beauty and safety matter.']));
    expect($row['payload']['items'])->toHaveCount(2);
});

test('an affix with only one valid word is still not offered', function () {
    missionJournalSeedDictionary(['beau']);

    $profile = new StoryProfile('Beauty matters here.');

    expect((new AffixGenerator())->feasibleAffixes($profile))->not->toHaveKey('ty');
});

test('alphabetize offers three words with distinct first letters and the ABC-first answer', function () {
    $story = new Story(['content' => missionJournalRichText()]);
    $profile = new StoryProfile($story->content);
    $generator = new AlphabetizeGenerator();

    expect($generator->feasibility($profile))->toBeGreaterThan(0);

    $row = $generator->generate($profile, $story);

    expect($row['kind'])->toBe('choice');
    expect($row['key'])->toBe('alphabetize');
    expect($row['payload']['items'])->toHaveCount(3);

    foreach ($row['payload']['items'] as $item) {
        expect($item['choices'])->toHaveCount(3);
        expect($item['choices'])->toContain($item['answer']);

        $letters = array_map(fn (string $w) => mb_substr($w, 0, 1), $item['choices']);
        expect(array_unique($letters))->toHaveCount(3);

        $sorted = $item['choices'];
        sort($sorted, SORT_STRING);
        expect($item['answer'])->toBe($sorted[0]);
    }

    foreach ($row['public_payload']['items'] as $item) {
        expect($item)->not->toHaveKey('answer');
    }
});

test('alphabetize is not feasible on text without three distinct first letters', function () {
    $profile = new StoryProfile('Apple apple ankle anchor.');

    expect((new AlphabetizeGenerator())->feasibility($profile))->toBe(0);
});

/* ------------------------------ authored affix family ------------------------ */

test('catalog offers one teacher tile per feasible affix', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = missionJournalStory(missionJournalAffixText());
    $rows = array_values(array_filter(
        AuthoredActions::catalog($story),
        fn (array $row) => $row['type'] === 'affix',
    ));

    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect($row['key'])->toBe('affix:ex');
    expect($row['value'])->toBe('ex');
    expect($row['kind'])->toBe('fill');
    expect($row['group'])->toBe('build');
    expect($row['action_key'])->toBe('affix-ex');
    expect($row['label'])->toBe('Type the missing prefix EX-');
    expect($row['flavor'])->toBe('PREFIX PUZZLE');
    expect($row['prompt'])->toBe('Add the missing prefix to each word');
    expect($row['need_floor'])->toBe(AffixGenerator::MIN_FIT);
    expect($row['count'])->toBeGreaterThanOrEqual(AffixGenerator::MIN_FIT);
    expect($row['unit'])->toBe('words');
});

test('no affix tile exists when the story lacks dictionary-checked stems', function () {
    $story = missionJournalStory('Exotic exhibit near the hall.');

    $rows = array_filter(AuthoredActions::catalog($story), fn (array $row) => $row['type'] === 'affix');

    expect($rows)->toBeEmpty();
});

test('teacher adds an affix action without a need count', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = missionJournalStory(missionJournalAffixText());

    $this->actingAs(missionJournalTeacher())
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'affix',
            'rule_value' => 'ex',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('story_mission_actions', [
        'story_id' => $story->id,
        'action_key' => 'affix-ex',
        'kind' => 'fill',
    ]);

    $action = $story->missionActions()->firstWhere('action_key', 'affix-ex');
    expect($action->config['type'])->toBe('affix');
    expect($action->config['value'])->toBe('ex');
});

test('the same affix cannot be added twice', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = missionJournalStory(missionJournalAffixText());
    $story->missionActions()->create([
        'action_key' => 'affix-ex',
        'kind' => 'fill',
        'config' => ['type' => 'affix', 'value' => 'ex', 'prompt' => 'Add the missing prefix to each word'],
        'enabled' => true,
        'position' => 0,
    ]);

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'affix',
            'rule_value' => 'ex',
        ])
        ->assertSessionHasErrors('rule_type');

    expect($story->missionActions()->count())->toBe(1);
});

test('an affix the story cannot support is rejected', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = missionJournalStory(missionJournalAffixText());

    $this->actingAs(missionJournalTeacher())
        ->from(route('teacher.stories.edit', $story))
        ->post(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'affix',
            'rule_value' => 'ness',
        ])
        ->assertSessionHasErrors('rule_type');

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('battle instantiates an authored affix move without leaking answers', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = missionJournalStory(missionJournalAffixText());

    $story->missionActions()->create([
        'action_key' => 'affix-ex',
        'kind' => 'fill',
        'config' => ['type' => 'affix', 'value' => 'ex', 'prompt' => 'Add the missing prefix to each word'],
        'enabled' => true,
        'position' => 0,
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);
    $action = $battle->actions->firstWhere('action_key', 'affix-ex');

    expect($action)->not->toBeNull();
    expect($action->kind)->toBe('fill');
    expect($action->flavor)->toBe('PREFIX PUZZLE');
    expect($action->payload['items'])->toHaveCount(3);

    foreach ($action->payload['items'] as $item) {
        expect($item['answer'])->toBe('ex');
        expect($item['shown'])->toStartWith('__');
    }

    foreach ($action->public_payload['items'] as $item) {
        expect($item)->not->toHaveKey('answer');
        expect($item['shown'])->toStartWith('__');
    }
});

test('an authored affix move is skipped and auto-padded when the story edit breaks it', function () {
    missionJournalSeedDictionary(['cite', 'plain', 'press', 'act'], ['cited' => 'cite']);

    $story = missionJournalStory(missionJournalAffixText());

    $story->missionActions()->create([
        'action_key' => 'affix-ex',
        'kind' => 'fill',
        'config' => ['type' => 'affix', 'value' => 'ex', 'prompt' => 'Add the missing prefix to each word'],
        'enabled' => true,
        'position' => 0,
    ]);

    // Rich enough for the auto pool, but no ex-/in- words remain.
    $story->update([
        'content' => 'The knight walked swiftly to the castle. He jumped over the gate and smiled at the guards. '
            .'The tired soldiers cheered loudly while the brave heroes rested near the wall.',
    ]);

    $battle = app(BattleService::class)->stateFor(missionJournalStudent(), $story);

    expect($battle->actions)->toHaveCount(3);
    expect($battle->actions->pluck('action_key'))->not->toContain('affix-ex');
});

/* ------------------------------ no-reload mutations ------------------------- */

test('toggle answers pool and gallery fragments for no-reload requests', function () {
    $story = missionJournalStory(missionJournalRichText());
    $before = (bool) $story->missions_enabled;

    $response = $this->actingAs(missionJournalTeacher())
        ->postJson(route('teacher.stories.missions.toggle', $story))
        ->assertOk()
        ->assertJsonStructure(['pool', 'gallery', 'toast' => ['message']]);

    expect($response->json('pool'))->toContain('Journal: '.($before ? 'OFF' : 'ON'));
    expect($story->refresh()->missions_enabled)->toBe(! $before);
});

test('move answers the swapped pool order for no-reload requests', function () {
    $story = missionJournalStory(missionJournalRichText());

    $first = $story->missionActions()->create([
        'action_key' => 'arrange',
        'kind' => 'arrange',
        'config' => ['type' => 'arrange', 'need' => 3, 'prompt' => 'Unscramble {need} words from the story'],
        'enabled' => true,
        'position' => 0,
    ]);
    $story->missionActions()->create([
        'action_key' => 'sentblank',
        'kind' => 'fill',
        'config' => ['type' => 'sentblank', 'prompt' => 'Type the missing word from each sentence'],
        'enabled' => true,
        'position' => 1,
    ]);

    $response = $this->actingAs(missionJournalTeacher())
        ->postJson(route('teacher.stories.mission-actions.move', $first), ['direction' => 'down'])
        ->assertOk()
        ->assertJsonStructure(['pool', 'gallery', 'toast' => ['message']]);

    $pool = $response->json('pool');
    expect(strpos($pool, 'Type the missing word'))->toBeLessThan(strpos($pool, 'Unscramble'));
    expect($first->refresh()->position)->toBe(1);
});

test('reorder answers the swapped pool order for no-reload requests', function () {
    $story = missionJournalStory(missionJournalRichText());

    $first = $story->missionActions()->create([
        'action_key' => 'arrange',
        'kind' => 'arrange',
        'config' => ['type' => 'arrange', 'need' => 3, 'prompt' => 'Unscramble {need} words from the story'],
        'enabled' => true,
        'position' => 1,
    ]);
    $second = $story->missionActions()->create([
        'action_key' => 'sentblank',
        'kind' => 'fill',
        'config' => ['type' => 'sentblank', 'prompt' => 'Type the missing word from each sentence'],
        'enabled' => true,
        'position' => 2,
    ]);

    $response = $this->actingAs(missionJournalTeacher())
        ->postJson(route('teacher.stories.mission-actions.reorder', $story), [
            'ids' => [$second->id, $first->id],
        ])
        ->assertOk()
        ->assertJsonStructure(['pool', 'gallery', 'toast' => ['message']]);

    $pool = $response->json('pool');
    expect(strpos($pool, 'Type the missing word'))->toBeLessThan(strpos($pool, 'Unscramble'));
    expect($second->refresh()->position)->toBe(1);
    expect($first->refresh()->position)->toBe(2);
    expect($response->json('toast.message'))->toBe('Journal move order saved.');
});

test('reorder rejects ids outside the full pool', function () {
    $story = missionJournalStory(missionJournalRichText());

    $first = $story->missionActions()->create([
        'action_key' => 'arrange',
        'kind' => 'arrange',
        'config' => ['type' => 'arrange', 'need' => 3, 'prompt' => 'Unscramble {need} words from the story'],
        'enabled' => true,
        'position' => 1,
    ]);
    $second = $story->missionActions()->create([
        'action_key' => 'sentblank',
        'kind' => 'fill',
        'config' => ['type' => 'sentblank', 'prompt' => 'Type the missing word from each sentence'],
        'enabled' => true,
        'position' => 2,
    ]);

    // Partial pool: the second move is missing.
    $this->actingAs(missionJournalTeacher())
        ->postJson(route('teacher.stories.mission-actions.reorder', $story), [
            'ids' => [$first->id],
        ])
        ->assertStatus(422);

    // A move from another story is never accepted.
    $foreign = missionJournalStory(missionJournalRichText())
        ->missionActions()
        ->create([
            'action_key' => 'arrange',
            'kind' => 'arrange',
            'config' => ['type' => 'arrange', 'need' => 3, 'prompt' => 'Unscramble {need} words from the story'],
            'enabled' => true,
            'position' => 1,
        ]);

    $this->actingAs(missionJournalTeacher())
        ->postJson(route('teacher.stories.mission-actions.reorder', $story), [
            'ids' => [$first->id, $second->id, $foreign->id],
        ])
        ->assertStatus(422);

    expect($first->refresh()->position)->toBe(1);
    expect($second->refresh()->position)->toBe(2);
});

test('store validation answers 422 field errors for no-reload requests', function () {
    $story = missionJournalStory(missionJournalRichText());

    $this->actingAs(missionJournalTeacher())
        ->postJson(route('teacher.stories.mission-actions.store', $story), [
            'rule_type' => 'injected',
            'rule_value' => 'zz',
            'need' => 3,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rule_type']);

    $this->assertDatabaseCount('story_mission_actions', 0);
});

test('delete answers pool and gallery fragments for no-reload requests', function () {
    $story = missionJournalStory(missionJournalRichText());

    $action = $story->missionActions()->create([
        'action_key' => 'arrange',
        'kind' => 'arrange',
        'config' => ['type' => 'arrange', 'need' => 3, 'prompt' => 'Unscramble {need} words from the story'],
        'enabled' => true,
        'position' => 0,
    ]);

    $response = $this->actingAs(missionJournalTeacher())
        ->deleteJson(route('teacher.stories.mission-actions.destroy', $action))
        ->assertOk()
        ->assertJsonStructure(['pool', 'gallery', 'toast' => ['message']]);

    expect($response->json('pool'))->toContain('No moves authored yet');
    $this->assertDatabaseCount('story_mission_actions', 0);
});
