<?php

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\PlacementAttempt;
use App\Models\User;
use App\Support\Audit;
use App\Support\LocalServices;
use App\Support\SystemStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the system status page renders the full read-only snapshot', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    User::factory()->count(3)->create(['role' => Role::Student, 'grade_level' => GradeLevel::Grade7, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Teacher]);
    User::factory()->create(['role' => Role::Student, 'suspended_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Application')
        ->assertSee('Database')
        ->assertSee('Services')
        ->assertSee('Storage')
        ->assertSee('Laravel')
        ->assertSee('Migrations')
        ->assertSee('Local tools')
        ->assertSee('Kokoro')
        ->assertSee('data-status-tabs');
});

test('the local tools panel reports real on-disk checks for all three tools', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $checks = LocalServices::check();
    expect(array_keys($checks))->toBe(['dictionary', 'kokoro', 'vosk']);
    foreach ($checks as $tool) {
        expect($tool['name'])->toBeString()->not->toBeEmpty();
        expect($tool['ok'])->toBeBool();
        expect($tool['status'])->toBeString()->not->toBeEmpty();
        expect($tool['detail'])->toBeString()->not->toBeEmpty();
    }

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Local tools')
        ->assertSee('WordNet dictionary')
        ->assertSee('Kokoro (narration voice)')
        ->assertSee('Vosk (offline speech-check)');
});

test('the snapshot counts match the database', function () {
    User::factory()->count(3)->create(['role' => Role::Student]);
    User::factory()->create(['role' => Role::Teacher]);
    User::factory()->create(['role' => Role::Admin]);
    User::factory()->create(['role' => Role::Student, 'suspended_at' => now()]);

    $counts = SystemStatus::snapshot()['counts'];

    expect($counts['users'])->toBe(User::count());
    expect($counts['students'])->toBe(User::where('role', Role::Student)->count());
    expect($counts['teachers'])->toBe(User::where('role', Role::Teacher)->count());
    expect($counts['admins'])->toBe(User::where('role', Role::Admin)->count());
    expect($counts['suspended'])->toBe(User::whereNotNull('suspended_at')->count());
    expect($counts['setup_done'])->toBe(User::where('is_setup_complete', true)->count());
    expect($counts['placement_attempts'])->toBe(PlacementAttempt::count());
    expect($counts['audit_today'])->toBe(AuditLog::whereDate('created_at', today())->count());
});

test('system status is admin-only', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.status'))
        ->assertForbidden();
});

test('the audit log page lists events with actor, ip and timestamp', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($admin);
    Audit::log('user.suspended', $student, ['reason' => 'testing']);

    $this->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('user.suspended')
        ->assertSee('user #'.$student->id)
        ->assertSee($admin->nickname)
        ->assertSee('"reason":"testing"')
        ->assertSee('127.0.0.1');
});

test('audit log filters by action and actor', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);
    $marker = 'reason-'.uniqid();

    $this->actingAs($admin);
    Audit::log('user.suspended', $student, ['reason' => $marker]);
    Audit::log('user.reactivated', $student);

    $this->get(route('admin.logs.index', ['action' => 'reactivated']))
        ->assertOk()
        ->assertSee('user.reactivated')
        ->assertDontSee($marker);

    $this->get(route('admin.logs.index', ['actor' => $admin->nickname]))
        ->assertOk()
        ->assertSee($marker);

    $this->get(route('admin.logs.index', ['actor' => 'no-such-actor-'.uniqid()]))
        ->assertOk()
        ->assertDontSee($marker);
});

test('audit log filters by date range', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);
    $oldMarker = 'note-'.uniqid();
    $recentMarker = 'reason-'.uniqid();

    DB::table('audit_logs')->insert([
        'actor_id' => $admin->id,
        'action' => 'user.reactivated',
        'target_type' => 'user',
        'target_id' => $student->id,
        'ip_address' => '127.0.0.1',
        'meta' => json_encode(['note' => $oldMarker]),
        'created_at' => now()->subDays(5),
    ]);

    $this->actingAs($admin);
    Audit::log('user.suspended', $student, ['reason' => $recentMarker]);

    $this->get(route('admin.logs.index', ['from' => now()->subDay()->format('Y-m-d')]))
        ->assertOk()
        ->assertSee($recentMarker)
        ->assertDontSee($oldMarker);

    $this->get(route('admin.logs.index', [
        'from' => now()->subDays(10)->format('Y-m-d'),
        'to' => now()->subDays(3)->format('Y-m-d'),
    ]))
        ->assertOk()
        ->assertSee($oldMarker)
        ->assertDontSee($recentMarker);
});

test('audit log preset ranges filter to the right window', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);
    $oldMarker = 'old-'.uniqid();
    $recentMarker = 'recent-'.uniqid();

    DB::table('audit_logs')->insert([
        'actor_id' => $admin->id,
        'action' => 'user.reactivated',
        'target_type' => 'user',
        'target_id' => $student->id,
        'ip_address' => '127.0.0.1',
        'meta' => json_encode(['note' => $oldMarker]),
        'created_at' => now()->subDays(20),
    ]);

    $this->actingAs($admin);
    Audit::log('user.suspended', $student, ['reason' => $recentMarker]);

    $this->get(route('admin.logs.index', ['preset' => 'last_7_days']))
        ->assertOk()
        ->assertSee($recentMarker)
        ->assertDontSee($oldMarker);

    $this->get(route('admin.logs.index', ['preset' => 'last_30_days']))
        ->assertOk()
        ->assertSee($recentMarker)
        ->assertSee($oldMarker);

    $this->get(route('admin.logs.index', ['preset' => 'all']))
        ->assertOk()
        ->assertSee($recentMarker)
        ->assertSee($oldMarker);

    $this->get(route('admin.logs.index', ['preset' => 'nonsense-preset']))
        ->assertOk()
        ->assertSee($recentMarker)
        ->assertSee($oldMarker);
});

test('audit log last-hour preset is time precise, not date precise', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);

    DB::table('audit_logs')->insert([
        'actor_id' => $admin->id,
        'action' => 'user.reactivated',
        'target_type' => 'user',
        'target_id' => $student->id,
        'ip_address' => '127.0.0.1',
        'meta' => json_encode(['note' => 'two-hours-ago']),
        'created_at' => now()->subHours(2),
    ]);

    $this->actingAs($admin);
    Audit::log('user.suspended', $student, ['reason' => 'just-now']);

    $this->get(route('admin.logs.index', ['preset' => 'last_hour']))
        ->assertOk()
        ->assertSee('just-now')
        ->assertDontSee('two-hours-ago');
});

test('the server errors and browser errors tabs render', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    DB::table('request_logs')->insert([
        'method' => 'GET',
        'path' => '/admin/boom',
        'status' => 500,
        'duration_ms' => 1234,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.logs.index', ['tab' => 'server']))
        ->assertOk()
        ->assertSee('All errors')
        ->assertSee('Server-side (5xx)')
        ->assertSee('/admin/boom');

    $this->get(route('admin.logs.index', ['tab' => 'server', 'status' => '5xx']))
        ->assertOk()
        ->assertSee('/admin/boom');

    $this->get(route('admin.logs.index', ['tab' => 'server', 'status' => '4xx']))
        ->assertOk()
        ->assertDontSee('/admin/boom');

    $this->get(route('admin.logs.index', ['tab' => 'browser']))
        ->assertOk()
        ->assertSee('No client errors reported');
});

test('audit log is admin-only', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.logs.index'))
        ->assertForbidden();
});
