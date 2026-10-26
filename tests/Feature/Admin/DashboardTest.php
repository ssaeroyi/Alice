<?php

use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\ViewAs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the dashboard shows real account and activity numbers', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    User::factory()->count(3)->create(['role' => Role::Student, 'grade_level' => GradeLevel::Grade7, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Teacher]);
    User::factory()->create(['role' => Role::Student, 'suspended_at' => now()]);
    User::factory()->create(['role' => Role::Student, 'is_setup_complete' => false]);

    DB::table('sessions')->insert([
        'id' => str_repeat('b', 40),
        'user_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'empty',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Total accounts')
        ->assertSee('Students')
        ->assertSee('Teachers')
        ->assertSee('Suspended')
        ->assertSee('Active sessions')
        ->assertDontSee('New this week')
        ->assertDontSee('Finished quick check')
        ->assertDontSee('Setup rate')
        ->assertSee('Onboarding')
        ->assertSee('60%')
        ->assertSee('3 of 5 students finished the quick check — 2 still pending')
        ->assertSee('Manage accounts');
});

test('onboarding only counts students — admins and teachers never inflate it', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Teacher, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Student, 'is_setup_complete' => false]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('50%')
        ->assertSee('1 of 2 students finished the quick check — 1 still pending')
        ->assertDontSee('150%')
        ->assertDontSee('3 of 2');
});

test('the dashboard lists recent audit events and recent joins', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student, 'nickname' => fake()->unique()->userName()]);

    $this->actingAs($admin);
    Audit::log('user.suspended', $student, ['reason' => 'dashboard-test']);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('user.suspended')
        ->assertSee($student->nickname)
        ->assertSee($admin->nickname);
});

test('the dashboard is admin-only', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('onboarding shows an empty state when there are no students', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('No student accounts yet — this fills in as students register and finish the placement quick check.');
});

test('the dashboard shares one date range across all charts', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $students = User::factory()->count(2)->create(['role' => Role::Student, 'is_setup_complete' => true]);

    $setId = DB::table('placement_sets')->insertGetId([
        'slug' => 'range-test-set',
        'title' => 'Range test set',
        'author' => 'Test',
        'source_url' => 'https://example.com/set',
        'content' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($students as $student) {
        DB::table('placement_attempts')->insert([
            'user_id' => $student->id,
            'set_id' => $setId,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-onboarding-canvas')
        ->assertSee('7 days')
        ->assertSee('30 days')
        ->assertSee('90 days')
        ->assertSee(now()->subDays(29)->format('M j'));

    $this->get(route('admin.dashboard', ['days' => 7]))
        ->assertOk()
        ->assertSee(now()->subDays(6)->format('M j'));

    $this->get(route('admin.dashboard', ['from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertSee(now()->subDays(10)->format('M j'))
        ->assertDontSee(now()->subDays(29)->format('M j'));
});

test('the dashboard shows the platform health explorer', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Platform health')
        ->assertSee('data-metric-canvas', false)
        ->assertSee('data-metric-tile', false)
        ->assertSee('data-metric-tile="traffic.requests"', false)
        ->assertSee('Page loads')
        ->assertDontSee('data-metric-tile="system.db_size"', false)
        ->assertDontSee('DB size')
        ->assertDontSee('All numbers are live')
        ->assertDontSee('data-freshness-stamp', false);
});

test('the dashboard shows the vitals strip', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Uptime')
        ->assertSee('data-uptime', false)
        ->assertSee('Storage used')
        ->assertSee('PHP memory')
        ->assertSee('MB')
        ->assertDontSee('Database size')
        ->assertDontSee('data-metric-tile="system.db_size"', false);
});

test('a metric tile link reloads the dashboard with that metric selected', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['metric' => 'traffic.five_xx', 'days' => 7]))
        ->assertOk()
        ->assertSee('data-metric="traffic.five_xx"', false)
        ->assertSee('data-metric-active', false);

    $this->get(route('admin.dashboard', ['metric' => 'not-a-metric']))
        ->assertOk()
        ->assertSee('data-metric="traffic.requests"', false);
});

test('the series endpoint only answers for admins', function () {
    $student = User::factory()->create(['role' => Role::Student]);
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->get(route('admin.dashboard.series'))->assertRedirect(route('login'));

    $this->actingAs($student)->get(route('admin.dashboard.series'))->assertForbidden();

    $this->actingAs($teacher)->get(route('admin.dashboard.series'))->assertForbidden();
});

test('the series endpoint rejects metrics that are not on the whitelist', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->getJson(route('admin.dashboard.series', ['metric' => 'students.reading_minutes']))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Unknown metric.');
});

test('the series endpoint answers with the shared range for the chosen metric', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $payload = $this->actingAs($admin)
        ->getJson(route('admin.dashboard.series', ['metric' => 'traffic.requests', 'days' => 7]))
        ->assertOk()
        ->assertJsonStructure(['metric', 'label', 'unit', 'hint', 'headline', 'subtitle', 'detail_url', 'detail_label', 'points'])
        ->json();

    expect($payload['metric'])->toBe('traffic.requests')
        ->and($payload['subtitle'])->toContain(now()->subDays(6)->format('M j'))
        ->and($payload['detail_url'])->toBe(route('admin.status').'#traffic')
        ->and($payload['detail_label'])->toBe('See request and IP details on Status')
        ->and($payload['points'])->toHaveCount(7);

    $this->getJson(route('admin.dashboard.series', ['metric' => 'traffic.requests', 'days' => 30]))
        ->assertOk()
        ->assertJsonPath('points', fn (array $points) => count($points) === 30);
});

test('the explorer detail link points at the records behind the metric', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['metric' => 'client.errors']))
        ->assertOk()
        ->assertSee('data-metric-detail', false)
        ->assertSee('href="'.e(route('admin.logs.index', ['tab' => 'browser', 'preset' => 'last_7_days'])).'"', false)
        ->assertSee('Review the reported errors in Logs', false);

    $this->get(route('admin.dashboard', ['metric' => 'accounts.new']))
        ->assertOk()
        ->assertSee('href="'.route('admin.accounts.index', ['sort' => 'newest']).'"', false)
        ->assertSee('See the newest accounts', false);
});

test('the new accounts metric excludes preview accounts', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    DB::table('users')->where('id', $admin->id)->update(['created_at' => now()->subDays(20)]);

    User::factory()->create(['role' => Role::Student, 'email' => 'preview.student@'.ViewAs::SCRATCH_DOMAIN]);
    User::factory()->create(['role' => Role::Student]);

    $payload = $this->actingAs($admin)
        ->getJson(route('admin.dashboard.series', ['metric' => 'accounts.new', 'days' => 7]))
        ->assertOk()
        ->json();

    expect($payload['headline'])->toBe('1');
});
