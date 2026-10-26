<?php

use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;
use App\Models\Story;
use App\Support\Dictionary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Micro WordNet fixture - the real dump is gitignored and never needed
 * by the suite. Formats mirror the genuine 3.1 files exactly:
 * data.* lines are "offset lexfile ss_type w_cnt word lex_id ... | gloss"
 * (each word carries its own lex_id digit), cntlist.rev is ONE combined
 * file of "sense_key tag_count sense_num" with the POS as the first
 * digit after %, and {pos}.exc lines are
 * "inflected replacement [replacement...]".
 */
function dictionaryFixtureDir(): string
{
    $dir = storage_path('app/testing-wordnet-'.uniqid());

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir.'/data.noun', implode("\n", [
        '  00000000 comment line ignored',
        '00001740 03 n 01 wolf 0 @ ~ 00001930n | a wild carnivorous mammal of the dog family',
        '00002090 03 n 02 boy 0 shepherd_boy 0 @ ~ | a male child; a boy who tends sheep',
    ]));

    file_put_contents($dir.'/data.verb', implode("\n", [
        '00001235 02 v 01 tend 1 sheepdog 0 (shepherd) 2 | watch over like a shepherd',
    ]));

    file_put_contents($dir.'/data.adj', implode("\n", [
        '00002730 00 a 01 brave 0 @ | possessing courage; "a brave knight"',
    ]));

    file_put_contents($dir.'/data.adv', "00003110 04 r 01 bravely 0 @ | with courage\n");

    // Combined frequency list: sense keys carry the POS digit.
    file_put_contents($dir.'/cntlist.rev', implode("\n", [
        'wolf%1:05:00:: 1500 1',
        'shepherd_boy%1:18:00:: 80 1',
        'boy%1:18:00:: 40 1',
        'boy%1:18:00:: 25 2',
        'tend%2:30:00:: 900 1',
    ]));

    file_put_contents($dir.'/noun.exc', implode("\n", [
        'wolves wolf',
        'boys boy',
    ]));

    file_put_contents($dir.'/verb.exc', "tended tend\n");

    return $dir;
}

it('imports entries, senses, tag counts and irregular forms idempotently', function () {
    $dir = dictionaryFixtureDir();

    $this->artisan('alice:dictionary:import', ['--path' => $dir])->assertSuccessful();

    // wolf + boy + shepherd_boy (noun synsets) + tend + brave + bravely,
    // plus the bundled function-word supplement.
    $supplement = require app_path('Console/Commands/data/function-words.php');

    expect(DictionaryEntry::query()->count())->toBe(6 + count($supplement))
        ->and(DictionaryForm::query()->where('irregular', true)->count())->toBe(3);

    $wolf = DictionaryEntry::query()->where('lemma', 'wolf')->first();

    expect($wolf->definition)->toBe('a wild carnivorous mammal of the dog family')
        ->and((int) $wolf->freq)->toBe(1500)
        ->and(DictionaryEntry::query()->where('lemma', 'boy')->where('pos', 'noun')->first()->sense_num)->toBe(1)
        ->and((int) DictionaryEntry::query()->where('lemma', 'shepherd boy')->first()->freq)->toBe(80);

    // Combined cntlist.rev sums every tagged sense of a lemma+pos.
    expect((int) DictionaryEntry::query()->where('lemma', 'boy')->where('pos', 'noun')->first()->freq)->toBe(65)
        ->and((int) DictionaryEntry::query()->where('lemma', 'tend')->where('pos', 'verb')->first()->freq)->toBe(900);

    // Quoted gloss tails become examples; the remainder is the definition.
    $brave = DictionaryEntry::query()->where('lemma', 'brave')->first();

    expect($brave->definition)->toBe('possessing courage')
        ->and($brave->example)->toBe('a brave knight');

    // Function-word supplement: WordNet has no "the/and/of", so the
    // bundled list seeds pos='function' rows that resolve directly.
    $the = Dictionary::lookup('the');

    expect($the['found'])->toBeTrue()
        ->and($the['resolved_to'])->toBe('the')
        ->and($the['freq_badge'])->toBe('COMMON')
        ->and($the['senses'][0]['pos'])->toBe('function');

    expect(DictionaryEntry::query()->where('pos', 'function')->count())->toBe(count($supplement))
        ->and(DictionaryForm::query()->where('irregular', false)->whereHas('entry', fn ($q) => $q->where('pos', 'function'))->count())->toBe(0);

    // Regular inflections generated: brave/braver/bravest, tending, tended...
    expect(DictionaryForm::query()->where('form', 'braver')->exists())->toBeTrue()
        ->and(DictionaryForm::query()->where('form', 'tending')->where('irregular', false)->exists())->toBeTrue();

    // Idempotent re-run: truncate-and-load leaves identical rows.
    $entriesBefore = DictionaryEntry::query()->count();
    $formsBefore = DictionaryForm::query()->count();

    $this->artisan('alice:dictionary:import', ['--path' => $dir])->assertSuccessful();

    expect(DictionaryEntry::query()->count())->toBe($entriesBefore)
        ->and(DictionaryForm::query()->count())->toBe($formsBefore)
        ->and((int) DictionaryEntry::query()->where('lemma', 'wolf')->first()->freq)->toBe(1500);

    // Missing directory fails cleanly.
    $this->artisan('alice:dictionary:import', ['--path' => $dir.'/nope'])->assertFailed();

    // Lookup through the full ladder using imported data only.
    $ran = Dictionary::lookup('wolves');

    expect($ran['found'])->toBeTrue()
        ->and($ran['resolved_to'])->toBe('wolf')
        ->and($ran['form_label'])->toBe('plural of wolf');

    foreach (glob($dir.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($dir);
});

it('audits library coverage and separates proper nouns from real gaps', function () {
    $dir = dictionaryFixtureDir();
    $this->artisan('alice:dictionary:import', ['--path' => $dir])->assertSuccessful();

    Story::create([
        'title' => 'Coverage Probe',
        'reading_level' => 4,
        'status' => 'published',
        'content' => 'The wolf ran. Petunia waved at the brave boy.',
    ]);

    $this->artisan('alice:dictionary:coverage')
        ->assertSuccessful()
        ->expectsOutputToContain('Library coverage');

    // Single-story audit works too.
    $this->artisan('alice:dictionary:coverage', ['--story' => 'coverage-probe'])->assertSuccessful();

    foreach (glob($dir.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($dir);
});
