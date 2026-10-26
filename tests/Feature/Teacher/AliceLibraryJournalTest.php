<?php

use App\Models\Story;
use App\Support\AliceLibrary;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

/** Two feasible moves on the fixture text: "f" words (fat, farmer, fields...) and a find rule. */
function libraryJournalSpecs(): array
{
    return [
        ['rule_type' => 'initial', 'rule_value' => 'f', 'need' => 2],
        ['rule_type' => 'suffix', 'rule_value' => 'ed', 'need' => 1],
    ];
}

test('a manifest journal list writes the moves, enables the journal and stores the source spec', function () {
    $this->markTestSkipped('Fixture uses the ABC (initial letter) move, temporarily hidden.');

    $entry = libraryEntry(['journal' => libraryJournalSpecs()]);

    $summary = AliceLibrary::importAll([$entry]);
    expect($summary['created'])->toBe(1);

    $story = Story::where('slug', null)->orWhere('title', 'The Test Fable')->firstOrFail();
    $moves = $story->missionActions()->orderBy('position')->get();

    expect($moves)->toHaveCount(2);
    expect($moves->pluck('action_key')->all())->toBe(['find-f', 'find-ed']);
    // Compare values, not key order: MariaDB reorders JSON keys on write.
    $spec = $moves->first()->config['spec'];
    expect($spec['rule_type'])->toBe('initial');
    expect($spec['rule_value'])->toBe('f');
    expect($spec['need'])->toBe(2);
    expect($story->fresh()->missions_enabled)->toBeTrue();
});

test('re-importing an unchanged journal skips the book; a changed journal updates it', function () {
    $this->markTestSkipped('Fixture uses the ABC (initial letter) move, temporarily hidden.');

    AliceLibrary::importAll([libraryEntry(['journal' => libraryJournalSpecs()])]);

    $same = AliceLibrary::importAll([libraryEntry(['journal' => libraryJournalSpecs()])]);
    expect($same['skipped'])->toBe(1);

    $changed = libraryJournalSpecs();
    $changed[1]['need'] = 2;
    $summary = AliceLibrary::importAll([libraryEntry(['journal' => $changed])]);

    expect($summary['updated'])->toBe(1);
    $story = Story::withTrashed()->where('title', 'The Test Fable')->firstOrFail();
    expect($story->missionActions()->where('action_key', 'find-ed')->first()->config['need'])->toBe(2);
});

test('an infeasible manifest move fails that book with a reason and writes nothing', function () {
    // No vocab for this text, so a verb hunt has zero words and fails the floor.
    $entry = libraryEntry(['journal' => [['rule_type' => 'pos', 'rule_value' => 'verb', 'need' => 1]]]);

    $summary = AliceLibrary::importAll([$entry]);

    expect($summary['created'])->toBe(0);
    expect($summary['failed'])->toHaveCount(1);
    expect($summary['failed'][0]['reason'])->not->toBe('');
    $this->assertDatabaseCount('stories', 0);
});

test('a manifest without a journal key leaves teacher-authored moves untouched', function () {
    $this->markTestSkipped('Fixture uses the ABC (initial letter) move, temporarily hidden.');

    AliceLibrary::importAll([libraryEntry(['journal' => libraryJournalSpecs()])]);
    $story = Story::where('title', 'The Test Fable')->firstOrFail();

    $story->missionActions()->create([
        'action_key' => 'defmatch',
        'kind' => 'choice',
        'config' => ['type' => 'defmatch', 'prompt' => 'Match each word to its meaning'],
        'enabled' => true,
        'position' => 9,
    ]);

    // Re-import without a journal key: the teacher's move survives.
    AliceLibrary::importAll([libraryEntry()]);

    expect($story->fresh()->missionActions()->where('action_key', 'defmatch')->exists())->toBeTrue();
});
