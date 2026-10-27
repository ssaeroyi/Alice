<?php

use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Teacher\AssignmentController;
use App\Http\Controllers\Teacher\ClassController;
use App\Http\Controllers\Teacher\CoverSearchController;
use App\Http\Controllers\Teacher\InboxController as TeacherInboxController;
use App\Http\Controllers\Teacher\NarrationController;
use App\Http\Controllers\Teacher\StoryController;
use App\Http\Controllers\Teacher\TeacherController;
use Illuminate\Support\Facades\Route;

// Teacher portal (staff: teachers, and admins as a superset)
Route::middleware(['auth', 'role:teacher,admin', 'not.deleted'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', [TeacherController::class, 'index'])->name('dashboard');
    Route::get('/series', [TeacherController::class, 'series'])->name('series');
    Route::get('/students', [TeacherController::class, 'roster'])->name('roster');
    Route::get('/students/{user}', [TeacherController::class, 'student'])->name('student');
        Route::post('/students/{user}/hold-grade', [TeacherController::class, 'holdGrade'])->name('students.hold-grade');
        Route::post('/students/{user}/unhold-grade', [TeacherController::class, 'unholdGrade'])->name('students.unhold-grade');

    Route::get('/inbox', [TeacherInboxController::class, 'index'])->name('inbox');
    Route::get('/inbox/preview', [TeacherInboxController::class, 'preview'])->name('inbox.preview');
    Route::post('/inbox/{notification}/read', [TeacherInboxController::class, 'read'])->name('inbox.read');
    Route::post('/inbox/read-all', [TeacherInboxController::class, 'readAll'])->name('inbox.read-all');

    Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::get('/assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
    Route::put('/assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
    Route::post('/assignments/{assignment}/publish', [AssignmentController::class, 'publish'])->name('assignments.publish');
    Route::post('/assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
    Route::post('/assignments/{assignment}/archive', [AssignmentController::class, 'archive'])->name('assignments.archive');
    Route::post('/assignments/{assignment}/notify', [AssignmentController::class, 'notify'])->name('assignments.notify');
    Route::post('/assignments/{assignment}/mark', [AssignmentController::class, 'markComplete'])->name('assignments.mark');
    Route::delete('/assignments/{assignment}/marks/{student}', [AssignmentController::class, 'markRemove'])->name('assignments.mark-remove');
    Route::get('/assignments/{assignment}/export', [AssignmentController::class, 'export'])->name('assignments.export');
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');

    Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
    Route::get('/classes/create', [ClassController::class, 'create'])->name('classes.create');
    Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
    Route::get('/classes/{group}', [ClassController::class, 'show'])->name('classes.show');
    Route::patch('/classes/{group}', [ClassController::class, 'update'])->name('classes.update');
Route::delete('/classes/{group}', [ClassController::class, 'destroy'])->name('classes.destroy');
    Route::get('/classes/{group}/members/create', [ClassController::class, 'membersCreate'])->name('classes.members.create');
    Route::post('/classes/{group}/members/invite', [ClassController::class, 'membersInvite'])->name('classes.members.invite');
    Route::delete('/classes/{group}/members/{user}', [ClassController::class, 'removeMember'])->name('classes.remove-member');

    Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
    Route::get('/stories/create', [StoryController::class, 'create'])->name('stories.create');
    Route::post('/stories', [StoryController::class, 'store'])->name('stories.store');
    Route::post('/stories/analyze', [StoryController::class, 'analyze'])->name('stories.analyze')->middleware('throttle:10,1');
    Route::post('/stories/cover-search', [CoverSearchController::class, 'search'])->name('stories.cover-search')->middleware('throttle:10,1');
    Route::post('/stories/batch', [StoryController::class, 'batch'])->name('stories.batch');
    Route::post('/stories/library/import', [StoryController::class, 'importLibrary'])->name('stories.library.import');
    Route::get('/stories/{story}', [StoryController::class, 'edit'])->name('stories.edit');
    Route::put('/stories/{story}', [StoryController::class, 'update'])->name('stories.update');
    Route::post('/stories/{story}/publish', [StoryController::class, 'publish'])->name('stories.publish');
    Route::get('/stories/{story}/narration/status', [NarrationController::class, 'status'])->name('stories.narration.status')->middleware('throttle:narration-status');
    Route::post('/stories/{story}/narration/request', [NarrationController::class, 'request'])->name('stories.narration.request')->middleware('throttle:narration-request');
    Route::post('/stories/{story}/archive', [StoryController::class, 'archive'])->name('stories.archive');
    Route::post('/stories/{story}/duplicate', [StoryController::class, 'duplicate'])->name('stories.duplicate');
    Route::post('/stories/{story}/delete', [StoryController::class, 'delete'])->name('stories.delete');
    Route::post('/stories/{story}/questions', [StoryController::class, 'questionsStore'])->name('stories.questions.store');
    Route::put('/stories/questions/{question}', [StoryController::class, 'questionsUpdate'])->name('stories.questions.update');
    Route::delete('/stories/questions/{question}', [StoryController::class, 'questionsDestroy'])->name('stories.questions.destroy');
    Route::post('/stories/questions/{question}/move', [StoryController::class, 'questionsMove'])->name('stories.questions.move');
    Route::post('/stories/{story}/questions/reorder', [StoryController::class, 'questionsReorder'])->name('stories.questions.reorder');
    Route::post('/stories/questions/{question}/duplicate', [StoryController::class, 'questionsDuplicate'])->name('stories.questions.duplicate');
    Route::post('/stories/{story}/sections', [StoryController::class, 'sectionsStore'])->name('stories.sections.store');
    Route::put('/stories/sections/{section}', [StoryController::class, 'sectionsUpdate'])->name('stories.sections.update');
    Route::delete('/stories/sections/{section}', [StoryController::class, 'sectionsDestroy'])->name('stories.sections.destroy');
    Route::post('/stories/sections/{section}/move', [StoryController::class, 'sectionsMove'])->name('stories.sections.move');
    Route::post('/stories/{story}/mission-actions', [StoryController::class, 'missionActionsStore'])->name('stories.mission-actions.store');
    Route::put('/stories/mission-actions/{missionAction}', [StoryController::class, 'missionActionsUpdate'])->name('stories.mission-actions.update');
    Route::delete('/stories/mission-actions/{missionAction}', [StoryController::class, 'missionActionsDestroy'])->name('stories.mission-actions.destroy');
    Route::post('/stories/mission-actions/{missionAction}/move', [StoryController::class, 'missionActionsMove'])->name('stories.mission-actions.move');
    Route::post('/stories/{story}/mission-actions/reorder', [StoryController::class, 'missionActionsReorder'])->name('stories.mission-actions.reorder');
    Route::post('/stories/{story}/missions/toggle', [StoryController::class, 'missionsToggle'])->name('stories.missions.toggle');

    Route::get('/settings', [SettingsController::class, 'teacherIndex'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'updateTeacherProfile'])->name('settings.profile');
    Route::post('/settings/password', [SettingsController::class, 'updateTeacherPassword'])->name('settings.password');
});

// Teacher account deletion — outside the not.deleted teacher group so the
// cancellation path stays reachable right after a request (same rationale as
// the student flow). Admin can still restore from the admin panel.
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::post('/settings/delete/request', [SettingsController::class, 'teacherRequestDelete'])->name('settings.delete.request');
    Route::post('/settings/delete/cancel', [SettingsController::class, 'teacherCancelDelete'])->name('settings.delete.cancel');

    // GET stubs — graceful redirects instead of a raw 405 (see student group).
    Route::get('/settings/delete/request', fn () => redirect()->route('teacher.settings'));
    Route::get('/settings/delete/cancel', fn () => redirect()->route('teacher.settings'));
});
