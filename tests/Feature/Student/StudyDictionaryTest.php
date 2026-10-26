<?php

use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['is_setup_complete' => true]);
});

function seedDictionaryEntry(string $lemma): void
{
    DictionaryEntry::create([
        'lemma' => $lemma,
        'pos' => 'noun',
        'sense_num' => 1,
        'definition' => "definition of {$lemma}",
        'example' => "Look at that {$lemma}.",
        'freq' => 800,
    ]);
}

test('the dictionary opens with an empty prompt', function () {
    $this->actingAs($this->student)
        ->get(route('study.dictionary'))
        ->assertOk()
        ->assertSee('Type a word')
        ->assertSee('Look up any word', false);
});

test('a lookup renders the entry with senses, frequency and a save action', function () {
    seedDictionaryEntry('falcon');

    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'falcon']))
        ->assertOk()
        ->assertSee('falcon')
        ->assertSee('COMMON')
        ->assertSee('definition of falcon')
        ->assertSee('Save to my words');
});

test('a found entry renders the say-it-out-loud pronunciation check for the headword', function () {
    seedDictionaryEntry('falcon');

    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'falcon']))
        ->assertOk()
        ->assertSee('Say it out loud')
        ->assertSee('data-pronounce-check="falcon"', false);
});

test('an unknown word shows a friendly miss instead of an error', function () {
    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'zzqxj']))
        ->assertOk()
        ->assertSee('No results')
        ->assertDontSee('Save to my words');
});

test('a saved word shows its In-your-words state instead of the save button', function () {
    seedDictionaryEntry('otter');

    $this->student->savedWords()->create([
        'word' => 'otter',
        'definition' => 'definition of otter',
        'part_of_speech' => 'noun',
    ]);

    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'otter']))
        ->assertOk()
        ->assertSee('In your words')
        ->assertDontSee('Save to my words');
});

test('a reader round-trip back target on our own host becomes the back link', function () {
    seedDictionaryEntry('falcon');

    $readerUrl = rtrim(config('app.url'), '/').'/read/some-story';
    $url = route('study.dictionary', ['word' => 'falcon', 'back' => $readerUrl]);

    $this->actingAs($this->student)
        ->get($url)
        ->assertOk()
        ->assertSee('Back to the story')
        ->assertSee(rtrim(config('app.url'), '/').'/read/some-story');
});

test('a foreign back target is rejected and the back link falls back to Study', function () {
    seedDictionaryEntry('falcon');

    $evil = 'https://evil.example/phish';
    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'falcon', 'back' => $evil]))
        ->assertOk()
        ->assertDontSee($evil)
        ->assertSee(route('study'));
});

test('a block listed word is filtered on the dictionary page even when it exists in the index', function () {
    seedDictionaryEntry('fuck');

    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'fuck']))
        ->assertOk()
        ->assertSee('Blocked word')
        ->assertSee("We can't show that word.", false)
        ->assertDontSee('No results')
        ->assertDontSee('Save to my words');
});

test('an inflection resolving to a block listed lemma is filtered too', function () {
    $entry = DictionaryEntry::create([
        'lemma' => 'fuck',
        'pos' => 'verb',
        'sense_num' => 1,
        'definition' => 'definition of fuck',
        'example' => null,
        'freq' => 800,
    ]);

    DictionaryForm::create([
        'form' => 'fucked',
        'entry_id' => $entry->id,
        'irregular' => true,
    ]);

    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'fucked']))
        ->assertOk()
        ->assertSee('Blocked word')
        ->assertDontSee('definition of fuck');
});

test('the save endpoint refuses a block listed word', function () {
    seedDictionaryEntry('sex');

    $this->actingAs($this->student)
        ->postJson(route('words.save'), ['word' => 'sex'])
        ->assertStatus(422)
        ->assertJsonPath('message', "That word isn't available on ALICE.");
});

test('a normal word is unaffected by the blocklist', function () {
    seedDictionaryEntry('falcon');

    $this->actingAs($this->student)
        ->get(route('study.dictionary', ['word' => 'falcon']))
        ->assertOk()
        ->assertSee('definition of falcon');
});

test('hate speech terms like hitler and jew are filtered too', function () {
    foreach (['hitler', 'jew'] as $word) {
        $this->actingAs($this->student)
            ->get(route('study.dictionary', ['word' => $word]))
            ->assertOk()
            ->assertSee('Blocked word')
            ->assertDontSee('No results')
            ->assertDontSee('Save to my words');
    }
});

test('the dictionary uses ink black text, never pale grays', function () {
    seedDictionaryEntry('falcon');

    $pages = [
        route('study.dictionary'),
        route('study.dictionary', ['word' => 'falcon']),
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
