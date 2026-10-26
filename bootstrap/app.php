<?php

use App\Console\Commands\AdminManage;
use App\Console\Commands\NotifyAssignmentDue;
use App\Console\Commands\ProbeIntegrations;
use App\Console\Commands\PurgeDeletedAccounts;
use App\Console\Commands\SnapshotStatus;
use App\Enums\Role;
use App\Http\Middleware\EnsureDevToolsContext;
use App\Http\Middleware\EnsureNarrationFeedToken;
use App\Http\Middleware\EnsureNotDeleted;
use App\Http\Middleware\EnsureNotStaff;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\RedirectAuthenticatedHome;
use App\Http\Middleware\RequestTelemetry;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AdminManage::class,
        SnapshotStatus::class,
        ProbeIntegrations::class,
        NotifyAssignmentDue::class,
        PurgeDeletedAccounts::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setup.complete' => EnsureSetupComplete::class,
            'role' => EnsureRole::class,
            'not.staff' => EnsureNotStaff::class,
            'not.suspended' => EnsureNotSuspended::class,
            'not.deleted' => EnsureNotDeleted::class,
            'auth.home' => RedirectAuthenticatedHome::class,
            'devtools' => EnsureDevToolsContext::class,
            'narration.feed' => EnsureNarrationFeedToken::class,
        ]);

        $middleware->trimStrings();

        $middleware->web(append: [
            RequestTelemetry::class,
            SecurityHeaders::class,
        ]);

        $middleware->preventRequestsDuringMaintenance(['admin/*', 'teacher/*']);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $request) => match ($request->user()->role) {
            Role::Admin => route('admin.dashboard'),
            Role::Teacher => route('teacher.dashboard'),
            default => route('hub'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
