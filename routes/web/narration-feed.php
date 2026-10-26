<?php

use App\Http\Controllers\Tools\NarrationFeedController;
use Illuminate\Support\Facades\Route;

// Narration tooling feed (TTS_AND_DICTIONARY.md §3.6): the dev machine's
// generator pulls wanted stories + export text and pushes progress heartbeats.
// The narration.feed middleware 404s every route unless NARRATION_FEED_TOKEN
// is configured — inert on localhost by design. No auth group: the generator
// is not a browser session, it authenticates with the bearer token.
Route::middleware(['narration.feed', 'throttle:60,1'])->prefix('tools/narration')->name('narration.tools.')->group(function () {
    Route::get('/wanted-export', [NarrationFeedController::class, 'wantedExport'])->name('wanted-export');
    Route::post('/progress', [NarrationFeedController::class, 'progress'])->name('progress');
});
