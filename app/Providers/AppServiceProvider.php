<?php

namespace App\Providers;

use App\Listeners\RecordLoginAttempt;
use App\Models\Question;
use App\Models\Story;
use App\Models\StoryReport;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'story' => Story::class,
            'question' => Question::class,
        ]);

        // House paginator: pixel-styled, amber pressed current page
        // (resources/views/vendor/pagination/alice.blade.php).
        Paginator::defaultView('pagination::alice');
        Paginator::defaultSimpleView('pagination::alice');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('deletion-restore', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by((string) $request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Class invite links (/a/CODE): public and guessable-ish, so a
        // per-IP bucket keeps code guessing slow without hurting a class
        // that opens the same link together.
        RateLimiter::for('a-share', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Named limiter keys are md5(limiterName . key), so these buckets are
        // independent — the 2.5s status poll can never drain the request
        // budget (inline throttle:N,M shares one key = sha1(user id) for an
        // authenticated user, which is how status polling once ate the
        // Generate button's 20/min).
        RateLimiter::for('narration-status', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?? (string) $request->ip());
        });

        RateLimiter::for('narration-request', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->getAuthIdentifier() ?? (string) $request->ip());
        });

        // Share the pending reports badge with the admin layout so the query
        // runs once per request, not inline in the blade (avoids recomputing
        // on every admin page load).
        View::composer('layouts.admin', function ($view) {
            $view->with('adminPendingReports', StoryReport::query()->where('status', 'pending')->count());
        });

        // Record every login attempt (success or failure) for the admin
        // security view (SYSTEM_DESIGN §46.6).
        $loginAttemptListener = new RecordLoginAttempt;
        Event::listen(Login::class, [$loginAttemptListener, 'login']);
        Event::listen(Failed::class, [$loginAttemptListener, 'failed']);
    }
}
