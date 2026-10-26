<?php

use App\Games\Journal\AuthoredActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('catalog reads the story lexicon once per build, not once per part of speech', function () {
    $story = missionJournalStory(missionJournalRichText());
    missionJournalSeedVocab($story, missionJournalVocabRows());

    DB::flushQueryLog();
    DB::enableQueryLog();
    AuthoredActions::catalog($story);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $dictionary = $queries->filter(fn ($q) => str_contains($q['query'], 'dictionary_entries')
        || str_contains($q['query'], 'dictionary_forms'))->count();

    // Measured: 17 dictionary queries without the per-build memo, 5 with it.
    expect($dictionary)->toBeLessThanOrEqual(5);
});
