<?php

use App\Http\Controllers\Placement\PlacementController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Student\ActivityLogController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\BadgeController;
use App\Http\Controllers\Student\ClassController as StudentClassController;
use App\Http\Controllers\Student\DailyTaskController;
use App\Http\Controllers\Student\HubController;
use App\Http\Controllers\Student\InboxController;
use App\Http\Controllers\Student\LibraryController as StudentLibraryController;
use App\Http\Controllers\Student\MissionController;
use App\Http\Controllers\Student\MiniGameController;
use App\Http\Controllers\Student\QuizController;
use App\Http\Controllers\Student\ReaderController;
use App\Http\Controllers\Student\RewardStoreController;
use App\Http\Controllers\Student\ShopController;
use App\Http\Controllers\Student\SkinsController;
use App\Http\Controllers\Student\StudyController;
use App\Http\Controllers\Student\WordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'not.staff', 'not.suspended', 'not.deleted'])->group(function () {
    Route::get('/step1', [PlacementController::class, 'showStep1'])->name('placement.step1');
    Route::post('/step1', [PlacementController::class, 'storeStep1'])->name('placement.step1.submit');

    Route::get('/intro', [PlacementController::class, 'showIntro'])->name('placement.intro');

    Route::get('/step2', [PlacementController::class, 'showStep2'])->name('placement.step2');
    Route::post('/step2', [PlacementController::class, 'storeStep2'])->name('placement.step2.submit');

    Route::get('/step3', [PlacementController::class, 'showStep3'])->name('placement.step3');
    Route::post('/step3', [PlacementController::class, 'storeStep3'])->name('placement.step3.submit');

    Route::get('/step4', [PlacementController::class, 'showStep4'])->name('placement.step4');
    Route::post('/step4', [PlacementController::class, 'storeStep4'])->name('placement.step4.submit');

    Route::get('/step5', [PlacementController::class, 'showStep5'])->name('placement.step5');

    Route::get('/placement/report/{attempt}', [PlacementController::class, 'showReport'])->name('placement.report');

    Route::middleware('setup.complete')->group(function () {
        Route::get('/hub', [HubController::class, 'index'])->name('hub');
        Route::get('/hub/stats', [HubController::class, 'stats'])->name('hub.stats');
        Route::patch('/hub/reading-goal', [HubController::class, 'updateReadingGoal'])->name('hub.reading-goal');
        Route::post('/hub/claim', [HubController::class, 'claimGoal'])->middleware('throttle:30,1')->name('hub.goal-claim');

        // Teacher-assigned work + inbox
        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show'])->name('student.assignments.show');
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('/inbox/preview', [InboxController::class, 'preview'])->name('inbox.preview');
Route::get('/inbox/unread.json', [InboxController::class, 'unread'])->name('inbox.unread');
        Route::post('/inbox/{notification}/read', [InboxController::class, 'read'])->name('inbox.read');
        Route::post('/inbox/{notification}/claim', [InboxController::class, 'claim'])->name('inbox.claim');
        Route::post('/inbox/read-all', [InboxController::class, 'readAll'])->name('inbox.read-all');

        // Class enrollment (My Class)
        Route::get('/classwork', [StudentClassController::class, 'classwork'])->name('student.classwork');
        Route::post('/classes/join', [StudentClassController::class, 'join'])->name('student.classes.join');
        Route::post('/classes/invitation/{notification}/decide', [StudentClassController::class, 'decide'])->name('student.classes.decide');
        Route::delete('/classes/{group}/leave', [StudentClassController::class, 'leave'])->name('student.classes.leave');

        Route::get('/library', [StudentLibraryController::class, 'index'])->name('library');
Route::get('/library/suggest', [StudentLibraryController::class, 'suggest'])->name('library.suggest');
        Route::get('/library/all/{row}', [StudentLibraryController::class, 'all'])->name('library.all');
        Route::get('/library/all/genre/{slug}', [StudentLibraryController::class, 'genre'])->name('library.genre');
        Route::get('/story/{story:slug}', [StudentLibraryController::class, 'preview'])->name('story.preview');
        Route::post('/story/{story:slug}/favorite', [StudentLibraryController::class, 'toggleFavorite'])->name('story.favorite');

        Route::get('/collection', [StudentLibraryController::class, 'collection'])->name('collection');

        Route::get('/read/{story:slug}', [ReaderController::class, 'show'])->name('reader.show');
        Route::post('/read/{story:slug}/heartbeat', [ReaderController::class, 'heartbeat'])->middleware('throttle:30,1')->name('reader.heartbeat');
        Route::post('/read/{story:slug}/finish', [ReaderController::class, 'finish'])->name('reader.finish');
        Route::get('/read/{story:slug}/summary', [ReaderController::class, 'summary'])->name('reader.summary');
        Route::patch('/read/{story:slug}/preferences', [ReaderController::class, 'preferences'])->name('reader.preferences');
        Route::post('/read/{story:slug}/report', [ReaderController::class, 'reportStore'])->middleware('throttle:10,1')->name('reader.report.store');
        Route::get('/read/{story:slug}/quiz', [QuizController::class, 'show'])->name('reader.quiz');
        Route::post('/read/{story:slug}/quiz', [QuizController::class, 'submit'])->middleware('throttle:20,1')->name('reader.quiz.submit');
        Route::post('/read/{story:slug}/quiz/hint', [QuizController::class, 'hint'])->middleware('throttle:30,1')->name('reader.quiz.hint');
        Route::get('/read/{story:slug}/quiz/results/{attempt_uuid}', [QuizController::class, 'results'])->name('reader.quiz.results');

        // Field Journal battles (demo: missions_enabled stories only)
        Route::get('/read/{story:slug}/battle', [MissionController::class, 'state'])->name('reader.battle.state');
        Route::post('/read/{story:slug}/battle/start', [MissionController::class, 'start'])->middleware('throttle:60,1')->name('reader.battle.start');
        Route::post('/read/{story:slug}/battle/complete', [MissionController::class, 'complete'])->middleware('throttle:60,1')->name('reader.battle.complete');
        Route::post('/read/{story:slug}/battle/fail', [MissionController::class, 'fail'])->middleware('throttle:30,1')->name('reader.battle.fail');
        Route::post('/read/{story:slug}/battle/reset', [MissionController::class, 'reset'])->middleware('throttle:10,1')->name('reader.battle.reset');

        // Field Journal wardrobe: skins catalog state, key purchases, equip
        Route::get('/skins', [SkinsController::class, 'index'])->name('skins.index');
        Route::post('/skins/{key}/buy', [SkinsController::class, 'buy'])->middleware('throttle:10,1')->where('key', '[a-z0-9_]+')->name('skins.buy');
        Route::post('/skins/{key}/equip', [SkinsController::class, 'equip'])->middleware('throttle:30,1')->where('key', '[a-z0-9_]+')->name('skins.equip');

        // Shop: the key-sink storefront (wardrobe + streak restores) on its
        // own page, linked from the profile dropdown's APP section.
        Route::get('/shop', [ShopController::class, 'index'])->name('shop');

        Route::get('/words/{word}', [WordController::class, 'lookup'])->middleware('throttle:120,1')->where('word', '[^/]+')->name('words.lookup');
        Route::post('/words', [WordController::class, 'save'])->middleware('throttle:60,1')->name('words.save');
        Route::delete('/words/{word}', [WordController::class, 'destroy'])->middleware('throttle:60,1')->where('word', '[^/]+')->name('words.destroy');

        // Study (formerly Word Gym)
        Route::get('/study', [StudyController::class, 'dashboard'])->name('study');
        Route::patch('/study/goal', [StudyController::class, 'updateGoal'])->name('study.goal');
        Route::get('/study/dictionary', [StudyController::class, 'dictionary'])->name('study.dictionary');
        Route::get('/study/session', [StudyController::class, 'session'])->name('study.session');
        Route::post('/study/grade', [StudyController::class, 'grade'])->middleware('throttle:120,1')->name('study.grade');
        Route::get('/study/words', [StudyController::class, 'words'])->name('study.words');
        Route::get('/study/words/results', [StudyController::class, 'wordsResults'])->name('study.words.results');
        Route::get('/study/words/{savedWord}', [StudyController::class, 'showWord'])->whereNumber('savedWord')->name('study.words.show');
        Route::post('/study/words/bulk', [StudyController::class, 'bulk'])->middleware('throttle:30,1')->name('study.words.bulk');

        // Mini-games
        Route::get('/game', [MiniGameController::class, 'hub'])->name('mini-game');
        Route::get('/game/setup', [MiniGameController::class, 'setup'])->name('game.setup');
        Route::get('/game/wordsearch', [MiniGameController::class, 'wordsearch'])->name('game.wordsearch');
        Route::get('/game/match', [MiniGameController::class, 'match'])->name('game.match');
        Route::get('/game/sentence', [MiniGameController::class, 'sentence'])->name('game.sentence');
        Route::post('/game/submit', [MiniGameController::class, 'submit'])->middleware('throttle:30,1')->name('game.submit');
        Route::post('/game/hint', [MiniGameController::class, 'hint'])->middleware('throttle:10,1')->name('game.hint');

        // Reward store (S6): spend keys on sinks
        Route::post('/rewards/freeze/buy', [RewardStoreController::class, 'buyFreeze'])->middleware('throttle:5,1')->name('rewards.freeze.buy');
        Route::post('/rewards/streak/restore', [RewardStoreController::class, 'restoreStreak'])->middleware('throttle:10,1')->name('rewards.streak.restore');

        // Daily tasks (S7): today's 3 tasks + claim + 1 reroll
        Route::get('/tasks', [DailyTaskController::class, 'show'])->name('tasks');
        Route::post('/tasks/claim', [DailyTaskController::class, 'claim'])->middleware('throttle:20,1')->name('tasks.claim');
        Route::post('/tasks/reroll', [DailyTaskController::class, 'reroll'])->middleware('throttle:10,1')->name('tasks.reroll');
        Route::post('/tasks/claim-all', [DailyTaskController::class, 'claimAll'])->middleware('throttle:10,1')->name('tasks.claim-all');

        // Badges shelf (S8)
        Route::get('/badges', [BadgeController::class, 'index'])->name('badges');

        // Activity log (S9): Steam-style feed across keys/reading/badges/streak
        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/name', [SettingsController::class, 'updateName'])->name('settings.name');
        Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->middleware('throttle:10,1')->name('settings.avatar');
        Route::post('/settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email');
        Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    });
});

// Account deletion — deliberately in a group WITHOUT not.deleted so a student
// who just requested deletion (rendered confirmation keeps the session alive)
// can still reach their cancellation path. Every other authenticated request
// is blocked by not.deleted, which signs the student out.
Route::middleware(['auth', 'not.staff', 'not.suspended'])->group(function () {
    Route::post('/settings/delete/request', [SettingsController::class, 'requestDelete'])->name('settings.delete.request');
    Route::post('/settings/delete/cancel', [SettingsController::class, 'cancelDelete'])->name('settings.delete.cancel');

    // GET stubs: never let a raw 405 page surface on hand-typed/dead links to
    // these POST-only destructive endpoints — redirect instead.
    Route::get('/settings/delete/request', fn () => redirect()->route('settings'));
    Route::get('/settings/delete/cancel', fn () => redirect()->route('settings'));
});
