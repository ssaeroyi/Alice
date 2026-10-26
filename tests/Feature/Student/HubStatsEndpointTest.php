<?php

use App\Models\User;
use App\Models\UserDailyActivity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function statsStudent(): User
{
    return User::factory()->create(['is_setup_complete' => true]);
}

test('the stats feed defaults to this-week mode and returns a Mon-to-today series with the calendar', function () {
    $student = statsStudent();

    $thisWeekDays = now()->copy()->startOfWeek(Carbon::MONDAY)->diffInDays(now()) + 1;

    $this->actingAs($student)
        ->getJson(route('hub.stats'))
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('mode', 'thisweek')
            ->has('series', $thisWeekDays)
            ->has('seriesMax')
            ->has('calendar.weeks')
            ->where('calendar.month_name', now()->format('F'))
            ->where('calendar.year', now()->year));
});

test('the stats feed allow-lists today/this-week/last-week and all, and falls back to this-week', function () {
    $student = statsStudent();

    $thisWeekDays = now()->copy()->startOfWeek(Carbon::MONDAY)->diffInDays(now()) + 1;

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=today')
        ->assertOk()
        ->assertJsonPath('mode', 'today')
        ->assertJsonCount(1, 'series');

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=thisweek')
        ->assertOk()
        ->assertJsonPath('mode', 'thisweek')
        ->assertJsonCount($thisWeekDays, 'series');

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=lastweek')
        ->assertOk()
        ->assertJsonPath('mode', 'lastweek')
        ->assertJsonCount(7, 'series');

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=all')
        ->assertOk()
        ->assertJsonPath('mode', 'all')
        ->assertJsonMissingPath('series')
        ->assertJsonMissingPath('seriesMax')
        ->assertJsonStructure(['monthlySeries' => []]);

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=999')
        ->assertOk()
        ->assertJsonPath('mode', 'thisweek')
        ->assertJsonCount($thisWeekDays, 'series');

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=30')
        ->assertOk()
        ->assertJsonPath('mode', 'thisweek')
        ->assertJsonCount($thisWeekDays, 'series');

    $this->actingAs($student)
        ->getJson(route('hub.stats').'?days=7')
        ->assertOk()
        ->assertJsonPath('mode', 'thisweek');
});

test('the calendar marks stamped days and reflects the requested month', function () {
    $student = statsStudent();
    $today = now();
    $lastMonth = $today->copy()->subMonthNoOverflow();

    UserDailyActivity::create([
        'user_id' => $student->id,
        'activity_date' => $lastMonth->copy()->startOfMonth()->toDateString(),
        'stamped_at' => $lastMonth->copy()->startOfMonth()->hour(10),
    ]);

    $monthParam = $lastMonth->format('Y-m');

    $this->actingAs($student)
        ->getJson(route('hub.stats')."?month={$monthParam}")
        ->assertOk()
        ->assertJsonPath('calendar.year', $lastMonth->year)
        ->assertJsonPath('calendar.month', $lastMonth->month)
        ->assertJsonPath('calendar.stamped_count', 1);
});

test('the stats feed clamps an out-of-range month back to the current month', function () {
    $student = statsStudent();

    $farFuture = now()->addYears(2)->format('Y-m');

    $this->actingAs($student)
        ->getJson(route('hub.stats')."?month={$farFuture}")
        ->assertOk()
        ->assertJsonPath('calendar.year', now()->year)
        ->assertJsonPath('calendar.month', now()->month);
});

test('the calendar browses to a past month with no activity instead of snapping back to the current month', function () {
    $student = statsStudent();

    // No activity at all: the calendar must still honor a requested past month
    // within the lookback window (the bug clamped every past month back to now).
    $past = now()->copy()->subMonths(3)->startOfMonth();

    $this->actingAs($student)
        ->getJson(route('hub.stats')."?month={$past->format('Y-m')}")
        ->assertOk()
        ->assertJsonPath('calendar.year', $past->year)
        ->assertJsonPath('calendar.month', $past->month);
});

test('the calendar clamps a month older than the 24-month lookback to the current month', function () {
    $student = statsStudent();

    $ancient = now()->copy()->subYears(3)->format('Y-m');

    $this->actingAs($student)
        ->getJson(route('hub.stats')."?month={$ancient}")
        ->assertOk()
        ->assertJsonPath('calendar.year', now()->year)
        ->assertJsonPath('calendar.month', now()->month);
});
