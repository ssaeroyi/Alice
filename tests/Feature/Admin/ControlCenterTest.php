<?php

use App\Enums\Role;
use App\Mail\TestEmail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (app()->isDownForMaintenance()) {
        app()->maintenanceMode()->deactivate();
    }
});

afterEach(function () {
    if (app()->isDownForMaintenance()) {
        app()->maintenanceMode()->deactivate();
    }
});

test('the control center renders with cache, maintenance and mail state', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.control'))
        ->assertOk()
        ->assertSee('Control center')
        ->assertSee('Application cache')
        ->assertSee('Maintenance mode')
        ->assertSee('Mail test')
        ->assertSee('Database backup')
        ->assertSee('Sessions')
        ->assertDontSee('Background tasks')
        ->assertDontSee('Say-it debug')
        ->assertSee('Clear cache')
        ->assertSee('Turn maintenance mode on')
        ->assertSee('Send test email')
        ->assertSee('Download database dump')
        ->assertSee('Sign out all sessions except mine');
});

test('control center is admin-only', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.control'))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('admin.control.cache'))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('admin.control.maintenance'))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('admin.control.mail-test'))
        ->assertForbidden();
});

test('clearing the cache flushes entries and is audited', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    Cache::put('control-center-test', 'value');

    $this->actingAs($admin)
        ->post(route('admin.control.cache'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'Cache cleared'));

    expect(Cache::get('control-center-test'))->toBeNull();
    expect(AuditLog::where('action', 'system.cache_cleared')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('clearing the error log empties the file and is audited', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $path = storage_path('logs/laravel.log');
    $hadFile = is_file($path);
    $original = $hadFile ? file_get_contents($path) : null;

    file_put_contents($path, "[2026-01-01] local.ERROR: some old noise\n");

    $this->actingAs($admin)
        ->post(route('admin.control.error-log.clear'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'Error log cleared'));

    expect(file_get_contents($path))->toBe('');
    expect(AuditLog::where('action', 'system.error_log_cleared')->where('actor_id', $admin->id)->exists())->toBeTrue();

    if ($hadFile) {
        file_put_contents($path, $original);
    }
});

test('maintenance mode toggles on and off while keeping the admin panel reachable', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->post(route('admin.control.maintenance'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    expect(app()->isDownForMaintenance())->toBeTrue();
    expect(AuditLog::where('action', 'system.maintenance_on')->where('actor_id', $admin->id)->exists())->toBeTrue();

    $this->get('/')->assertServiceUnavailable();
    $this->get('/register')->assertServiceUnavailable();

    $this->actingAs($admin)
        ->get(route('admin.control'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.control.maintenance'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    expect(app()->isDownForMaintenance())->toBeFalse();
    expect(AuditLog::where('action', 'system.maintenance_off')->where('actor_id', $admin->id)->exists())->toBeTrue();

    auth()->logout();

    $this->get('/')->assertOk();
});

test('the test email sends to the acting admin and is audited', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->post(route('admin.control.mail-test'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'delivered to '.$admin->email.' in'));

    Mail::assertSent(TestEmail::class, fn ($mail) => $mail->hasTo($admin->email));

    expect(AuditLog::where('action', 'system.mail_test_sent')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('a failing mail send is audited and shows an error toast', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->post(route('admin.control.mail-test'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error');

    expect(AuditLog::where('action', 'system.mail_test_failed')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('the database dump downloads a full sql copy and is audited', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student, 'nickname' => 'pearl-diver', 'email' => 'pearl@example.com']);

    $response = $this->actingAs($admin)
        ->get(route('admin.control.dump'))
        ->assertOk()
        ->assertDownload();

    $content = $response->streamedContent();

    expect($content)->toContain('-- Alice database dump');
    expect($content)->toContain('DROP TABLE IF EXISTS `users`;');
    expect($content)->toContain('CREATE TABLE `users`');
    expect($content)->toContain('INSERT INTO `users`');
    expect($content)->toContain('pearl@example.com');
    expect($content)->toContain('-- End of Alice database dump');
    expect($response->headers->get('content-disposition'))->toContain('alice-backup-');

    $log = AuditLog::where('action', 'system.db_dump_downloaded')->where('actor_id', $admin->id)->firstOrFail();
    expect($log->meta['tables'])->toBeGreaterThanOrEqual(1);
});

test('the database dump escapes tricky values safely', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    User::factory()->create([
        'role' => Role::Student,
        'nickname' => "O'Neil 🚀\nline two",
        'email' => 'tricky@example.com',
    ]);

    $content = $this->actingAs($admin)
        ->get(route('admin.control.dump'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain("O\\'Neil 🚀");
    expect($content)->toContain('line two');
});

test('the database dump is refused for non-mysql connections', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    $this->withoutMiddleware()
        ->get(route('admin.control.dump'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', fn ($message) => str_contains($message, 'MySQL or MariaDB'));

    config(['database.default' => 'mysql']);
});

test('signing out all sessions keeps the acting admin and is audited', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);

    DB::table('sessions')->insert([
        ['id' => str_repeat('a', 40), 'user_id' => $admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'empty', 'last_activity' => now()->timestamp],
        ['id' => str_repeat('b', 40), 'user_id' => $student->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'empty', 'last_activity' => now()->timestamp],
        ['id' => str_repeat('c', 40), 'user_id' => $student->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => 'empty', 'last_activity' => now()->timestamp],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.control.sign-out-all'))
        ->assertRedirect()
        ->assertSessionHas('toast.message');

    expect(DB::table('sessions')->count())->toBe(1);
    expect(DB::table('sessions')->value('user_id'))->toBe($admin->id);

    expect(AuditLog::where('action', 'user.signed_out_all')->where('actor_id', $admin->id)->exists())->toBeTrue();
});
