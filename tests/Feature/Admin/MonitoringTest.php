<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('every web request is recorded by the telemetry middleware', function () {
    $this->get('/login')->assertOk();

    $this->assertDatabaseHas('request_logs', [
        'method' => 'GET',
        'path' => 'login',
        'status' => 200,
    ]);
});

test('failed logins are audited with the attempted email', function () {
    $this->post('/login', [
        'email' => 'nobody@example.org',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.failed_login',
    ]);

    $entry = DB::table('audit_logs')->where('action', 'auth.failed_login')->latest('id')->first();

    expect(json_decode($entry->meta, true)['email'])->toBe('nobody@example.org');
});

test('the status page shows traffic numbers, p95 and the 30-day chart', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    DB::table('request_logs')->insert([
        ['method' => 'GET', 'path' => 'a', 'status' => 200, 'duration_ms' => 50, 'created_at' => now()->subMinutes(10)],
        ['method' => 'GET', 'path' => 'b', 'status' => 200, 'duration_ms' => 100, 'created_at' => now()->subHours(2)],
        ['method' => 'GET', 'path' => 'c', 'status' => 404, 'duration_ms' => 30, 'created_at' => now()->subHours(5)],
        ['method' => 'POST', 'path' => 'd', 'status' => 500, 'duration_ms' => 900, 'created_at' => now()->subHours(6)],
    ]);

    foreach (range(1, 100) as $i) {
        DB::table('request_logs')->insert([
            'method' => 'GET',
            'path' => 'seed',
            'status' => 200,
            'duration_ms' => $i,
            'created_at' => now()->subMinutes(30),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Traffic')
        ->assertSee('Page views')
        ->assertSee('Slowest loads')
        ->assertSee('Avg load time')
        ->assertSee('typical page-load speed', false)
        ->assertSee('the real user experience')
        ->assertSee('97 ms')->assertSee('Visitor errors')
        ->assertSee('Server failures')
        ->assertSee('Page views per hour, last 24 hours');
});

test('the traffic window tabs switch the reported range', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    DB::table('request_logs')->insert([
        ['method' => 'GET', 'path' => 'a', 'status' => 200, 'duration_ms' => 50, 'created_at' => now()->subDays(10)],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.status', ['range' => 720]))
        ->assertOk()
        ->assertSee('Page views')
        ->assertSee('Page views per day, last 30 days')
        ->assertSee('Last 7 days')
        ->assertSee('Last 24h');
});

test('the traffic panel can be fetched as a standalone partial', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.status', ['range' => 168, 'partial' => 1]))
        ->assertOk()
        ->assertSee('data-traffic-panel', false)
        ->assertSee('Page views')
        ->assertSee('data-traffic-tab', false)
        ->assertDontSee('ALICE Admin - System status', false);
});

test('the services panel and control page use background-task wording', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertDontSee('failed jobs', false)
        ->assertDontSee('failed job', false);

    $this->get(route('admin.control'))
        ->assertOk()
        ->assertDontSee('Background tasks')
        ->assertDontSee('Retry failed tasks')
        ->assertDontSee('Clear failed tasks')
        ->assertDontSee('failed jobs', false);
});

test('server errors in the last 24h surface a warning on the status page', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    DB::table('request_logs')->insert([
        ['method' => 'GET', 'path' => 'x', 'status' => 500, 'duration_ms' => 100, 'created_at' => now()->subHour()],
        ['method' => 'GET', 'path' => 'y', 'status' => 200, 'duration_ms' => 100, 'created_at' => now()->subHour()],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('1 server failure in the last 24h — a failed request here and there is normal');
});

test('many server errors raise the stronger warning', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    DB::table('request_logs')->insert([
        ['method' => 'GET', 'path' => 'x', 'status' => 500, 'duration_ms' => 100, 'created_at' => now()->subHour()],
        ['method' => 'GET', 'path' => 'y', 'status' => 500, 'duration_ms' => 100, 'created_at' => now()->subHour()],
        ['method' => 'GET', 'path' => 'z', 'status' => 500, 'duration_ms' => 100, 'created_at' => now()->subHour()],
    ]);

    foreach (range(1, 9) as $i) {
        DB::table('request_logs')->insert([
            'method' => 'GET',
            'path' => 'w'.$i,
            'status' => 500,
            'duration_ms' => 100,
            'created_at' => now()->subHour(),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('something is regularly failing');
});

test('a failed-login spike raises the brute-force warning', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    foreach (range(1, 12) as $i) {
        DB::table('login_attempts')->insert([
            'email' => 'victim@example.org',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'test',
            'successful' => false,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Failed logins')
        ->assertSee('Unusually many failed logins')
        ->assertSee('12');
});

test('the status page no longer embeds the error log tail', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $marker = 'UNIQUE-TEST-MARKER-'.Str::random(8);

    file_put_contents(
        storage_path('logs/laravel.log'),
        '['.now()->format('Y-m-d H:i:s')."] local.ERROR: {$marker} — something broke. {\"exception\":{}}\n",
        FILE_APPEND
    );

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertDontSee($marker)
        ->assertDontSee('Raw log (last 200 lines)')
        ->assertDontSee('data-status-panel="logs"', false);
});

test('mail health reads the last mail test from the audit trail', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    Audit::log('system.mail_test_sent', null, ['to' => 'a@example.org', 'duration_ms' => 1400]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Mail health')
        ->assertSee('Working')
        ->assertSee('delivered in 1.4s');
});

test('mail health shows the empty state before any test ran', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Mail health')
        ->assertSee('No mail test yet');
});
