<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('alice:probe-integrations')->hourly();

// Nightly: roll the request-log traffic into durable daily_stats rows. The
// raw request log is pruned to the newest ~10k rows on a rolling basis, so
// without the roll-up the per-day traffic chart would fade over time.
Schedule::command('alice:stats:daily')->dailyAt('02:00');

// Nightly: refresh every student's live, evidence-based reading progress
// (the "reading level that moves") so teacher reports see a stable daily
// snapshot while event-triggered recomputes keep the student bar current.
Schedule::command('alice:reading-progress')->dailyAt('03:00');

// Twice daily: deliver due-soon + overdue assignment reminders (idempotent).
Schedule::command('alice:assignment-reminders')->twiceDaily(7, 15);

// Daily: retire active assignments whose due date has passed into the
// "Finished" bucket so they stop cluttering the active/student listings.
Schedule::command('alice:archive-expired-assignments')->dailyAt('04:00');

// Daily: hard-delete student accounts whose 7-day deletion grace window has
// lapsed (GDPR-style two-phase erasure). Teachers are never deleted here; they
// are anonymized at request time and closed/reassigned by an admin.
Schedule::command('alice:accounts:purge-deleted')->dailyAt('04:15');

// Daily: prune login_attempts older than 30 days (security audit trail, §46.6).
Schedule::call(fn () => DB::table('login_attempts')->where('created_at', '<', now()->subDays(30))->delete())->dailyAt('05:20');
