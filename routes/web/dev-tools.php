<?php

use App\Http\Controllers\Admin\DevToolsController;
use Illuminate\Support\Facades\Route;

// Admin Dev Tools actions. Outside the role:admin group on purpose: during a
// "view as" preview the session user is the scratch student, and the devtools
// middleware lets only a real admin session OR an active preview (scratch
// target) through — so the overlay works from anywhere in the student zone,
// including the immersive reader. Actions only ever touch scratch accounts.
if (config('devtools.enabled')) {
    Route::middleware(['auth', 'devtools'])->prefix('dev')->name('dev.')->group(function () {
        Route::get('/tools/state', [DevToolsController::class, 'state'])->name('state');
        Route::post('/tools', [DevToolsController::class, 'run'])->name('run');
    });
}
