<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit log records the actor, action, target, and request metadata', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $target = User::factory()->create();

    $this->actingAs($admin)->withHeaders(['User-Agent' => 'TestAgent/1.0'])
        ->get(route('admin.dashboard'));

    $log = Audit::log('user.suspended', $target, ['reason' => 'test']);

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->action)->toBe('user.suspended')
        ->and($log->target_type)->toBe('user')
        ->and($log->target_id)->toBe($target->id)
        ->and($log->meta)->toBe(['reason' => 'test'])
        ->and($log->ip_address)->not->toBeNull();
});

test('audit log records a system event without an actor', function () {
    $log = Audit::log('system.cache_cleared');

    expect($log->actor_id)->toBeNull()
        ->and($log->target_type)->toBeNull()
        ->and($log->meta)->toBeNull();
});

test('audit log is immutable', function () {
    $log = Audit::log('system.maintenance_enabled');

    expect(AuditLog::where('id', $log->id)->exists())->toBeTrue()
        ->and($log->updated_at)->toBeNull();
});
