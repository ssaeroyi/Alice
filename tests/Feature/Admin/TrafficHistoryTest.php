<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Monitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function logTraffic(string $day, int $count, int $status = 200, int $durationMs = 120): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table('request_logs')->insert([
            'method' => 'GET',
            'path' => '/teacher',
            'status' => $status,
            'duration_ms' => $durationMs,
            'created_at' => $day.' 10:00:00',
        ]);
    }
}

test('alice:stats:daily rolls request-log traffic into durable daily_stats rows', function () {
    logTraffic(now()->subDays(1)->toDateString(), 3);
    logTraffic(now()->subDays(1)->toDateString(), 1, 404);
    logTraffic(now()->subDays(1)->toDateString(), 1, 500);
    logTraffic(now()->subDays(3)->toDateString(), 2, 200, 300);

    $this->artisan('alice:stats:daily')->assertSuccessful();

    $yesterday = now()->subDay()->toDateString();
    $threeDays = now()->subDays(3)->toDateString();

    expect(DB::table('daily_stats')->where('day', $yesterday)->where('metric', 'traffic.requests')->value('value'))->toBe(5)
        ->and(DB::table('daily_stats')->where('day', $yesterday)->where('metric', 'traffic.four_xx')->value('value'))->toBe(1)
        ->and(DB::table('daily_stats')->where('day', $yesterday)->where('metric', 'traffic.five_xx')->value('value'))->toBe(1)
        ->and(DB::table('daily_stats')->where('day', $yesterday)->where('metric', 'traffic.avg_ms')->value('value'))->toBe(120)
        ->and(DB::table('daily_stats')->where('day', $threeDays)->where('metric', 'traffic.requests')->value('value'))->toBe(2);
});

test('alice:stats:daily is idempotent and skips finished days', function () {
    logTraffic(now()->subDays(1)->toDateString(), 3);

    $this->artisan('alice:stats:daily')->assertSuccessful();
    $before = DB::table('daily_stats')->count();

    $this->artisan('alice:stats:daily')
        ->assertSuccessful()
        ->expectsOutputToContain('up to date');

    expect(DB::table('daily_stats')->count())->toBe($before);
});

test('the traffic series reads rolled-up history plus the live tail', function () {
    logTraffic(now()->subDays(5)->toDateString(), 2);
    logTraffic(now()->toDateString(), 4);

    $this->artisan('alice:stats:daily');

    $series = Monitor::dailySeries(7);

    expect(count($series))->toBe(7)
        ->and($series[0]['date'])->toBe(now()->subDays(6)->toDateString())
        ->and($series[5]['date'])->toBe(now()->subDay()->toDateString())
        ->and(collect($series)->firstWhere('date', now()->subDays(5)->toDateString())['total'])->toBe(2)
        ->and(collect($series)->firstWhere('date', now()->toDateString())['total'])->toBe(4);
});

test('an all-time series starts at the earliest rolled day', function () {
    logTraffic(now()->subDays(10)->toDateString(), 7);
    logTraffic(now()->toDateString(), 1);

    $this->artisan('alice:stats:daily');

    $series = Monitor::dailySeries(0);

    expect(count($series))->toBe(11)
        ->and($series[0]['date'])->toBe(now()->subDays(10)->toDateString())
        ->and($series[0]['total'])->toBe(7)
        ->and($series[array_key_last($series)]['total'])->toBe(1);
});

test('the status traffic panel shows one range control and the rolled-up series', function () {
    logTraffic(now()->subDays(5)->toDateString(), 2);
    $this->artisan('alice:stats:daily');

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Last 24h')
        ->assertSee('Last 7 days')
        ->assertSee('Last 30 days')
        ->assertSee('Last 90 days')
        ->assertDontSee('Chart:', false)
        ->assertSee('Page views per hour, last 24 hours');

    $this->actingAs($admin)
        ->get(route('admin.status', ['range' => 720]))
        ->assertOk()
        ->assertSee('Page views per day, last 30 days');

    $this->actingAs($admin)
        ->get(route('admin.status', ['range' => 2160]))
        ->assertOk()
        ->assertSee('Page views per day, last 90 days');
});

test('the traffic series endpoint returns one metric for the chosen window', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    logTraffic(now()->toDateString(), 3);

    $response = $this->actingAs($admin)
        ->get(route('admin.status.traffic-series', ['metric' => 'traffic.requests', 'range' => 24]))
        ->assertOk()
        ->assertJsonPath('metric', 'traffic.requests')
        ->assertJsonPath('label', 'Page views');

    // The 24h window buckets by hour, oldest first, every slot filled.
    $points = $response->json('points');
    expect(count($points))->toBe(24)
        ->and($points[0]['date'])->toMatch('/^\d{1,2}:00$/')
        ->and(collect($points)->sum('count'))->toBe(3);

    $this->get(route('admin.status.traffic-series', ['metric' => 'nonsense', 'range' => 24]))
        ->assertStatus(422);
});

test('failed logins and distinct emails live in the traffic tile grid', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    foreach (range(1, 3) as $i) {
        DB::table('login_attempts')->insert([
            'email' => "victim{$i}@example.org",
            'ip_address' => '10.0.0.9',
            'user_agent' => 'test',
            'successful' => false,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Failed logins')
        ->assertSee('Distinct emails')
        ->assertDontSee('Every wrong-password attempt is recorded', false)
        ->assertSee('data-traffic-metric="traffic.failed_logins"', false)
        ->assertSee('data-traffic-metric="traffic.distinct_emails"', false);

    // The distinct-emails series counts unique accounts, not attempts.
    $series = $this->actingAs($admin)
        ->get(route('admin.status.traffic-series', ['metric' => 'traffic.distinct_emails', 'range' => 24]))
        ->assertOk()
        ->assertJsonPath('label', 'Distinct emails')
        ->assertJsonPath('unit', 'accounts');

    expect(collect($series->json('points'))->sum('count'))->toBe(3);
});
