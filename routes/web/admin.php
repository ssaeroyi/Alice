<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ControlCenterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InboxController as AdminInboxController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Admin only
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // One explorer metric as JSON for the dashboard's tile clicks. Same
    // shared date range as the page; anything off the whitelist is a 422.
    Route::get('/dashboard/series', [DashboardController::class, 'series'])->name('dashboard.series');

    Route::get('/status', [SystemStatusController::class, 'index'])->name('status');
    Route::get('/status/traffic-series', [SystemStatusController::class, 'trafficSeries'])->name('status.traffic-series');
    Route::post('/status/integrations/test/{integration}', [SystemStatusController::class, 'testIntegration'])->middleware('throttle:10,1')->name('status.integrations.test');
    Route::post('/status/integrations/test-all', [SystemStatusController::class, 'testAllIntegrations'])->middleware('throttle:3,10')->name('status.integrations.test-all');
    Route::post('/status/client-errors/clear', [SystemStatusController::class, 'clearClientErrors'])->name('status.client-errors.clear');
    Route::post('/status/stt-debug', [SystemStatusController::class, 'toggleSttDebug'])->name('status.stt-debug');
    Route::post('/status/packages/refresh', [SystemStatusController::class, 'packagesRefresh'])->name('status.packages.refresh');
    Route::post('/status/packages/update', [SystemStatusController::class, 'packagesUpdate'])->middleware('throttle:4,10')->name('status.packages.update');
    Route::get('/logs', [AuditLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/export', [AuditLogController::class, 'export'])->name('logs.export');
    Route::post('/logs/client-errors/clear', [AuditLogController::class, 'clearClientErrors'])->name('logs.client-errors.clear');

    Route::get('/inbox', [AdminInboxController::class, 'index'])->name('inbox');
    Route::get('/inbox/unread.json', [AdminInboxController::class, 'unread'])->name('inbox.unread');
    Route::get('/inbox/preview', [AdminInboxController::class, 'preview'])->name('inbox.preview');
    Route::post('/inbox/{notification}/read', [AdminInboxController::class, 'read'])->name('inbox.read');
    Route::post('/inbox/read-all', [AdminInboxController::class, 'readAll'])->name('inbox.read-all');

    // Admin's own account settings (name / email / password). System-level
    // settings live in Control Center, not here.
    Route::get('/settings', [SettingsController::class, 'adminIndex'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'updateAdminProfile'])->name('settings.profile');
    Route::post('/settings/email', [SettingsController::class, 'updateAdminEmail'])->name('settings.email');
    Route::post('/settings/password', [SettingsController::class, 'updateAdminPassword'])->name('settings.password');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/bulk-resolve', [ReportController::class, 'bulkResolve'])->name('reports.bulk-resolve');
    Route::post('/reports/bulk-delete', [ReportController::class, 'bulkDelete'])->name('reports.bulk-delete');
    Route::post('/reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    Route::get('/control', [ControlCenterController::class, 'index'])->name('control');
    Route::get('/control/dump', [ControlCenterController::class, 'downloadDump'])->name('control.dump');
    Route::post('/control/cache', [ControlCenterController::class, 'clearCache'])->name('control.cache');
    Route::post('/control/error-log/clear', [ControlCenterController::class, 'clearErrorLog'])->name('control.error-log.clear');
    Route::post('/control/maintenance', [ControlCenterController::class, 'toggleMaintenance'])->name('control.maintenance');
    Route::post('/control/mail-test', [ControlCenterController::class, 'testMail'])->name('control.mail-test');
    Route::post('/control/sign-out-all', [ControlCenterController::class, 'signOutAll'])->name('control.sign-out-all');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/export', [AccountController::class, 'exportCsv'])->name('accounts.export');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{user}', [AccountController::class, 'show'])->name('accounts.show');
    Route::post('/accounts/{user}/suspend', [AccountController::class, 'suspend'])->name('accounts.suspend');
    Route::post('/accounts/{user}/reactivate', [AccountController::class, 'reactivate'])->name('accounts.reactivate');
    Route::post('/accounts/{user}/reset-link', [AccountController::class, 'resetLink'])->name('accounts.reset-link');
    Route::post('/accounts/{user}/role', [AccountController::class, 'changeRole'])->name('accounts.role');
    Route::post('/accounts/{user}/sign-out', [AccountController::class, 'signOut'])->name('accounts.sign-out');
    Route::post('/accounts/{user}/restore', [AccountController::class, 'restore'])->name('accounts.restore');
    Route::post('/accounts/{user}/delete', [AccountController::class, 'destroy'])->name('accounts.destroy');

    if (config('devtools.enabled')) {
        Route::post('/preview/switch/{role}', [AccountController::class, 'quickViewAs'])->name('preview.switch')->where('role', 'student|teacher');
    }
});
