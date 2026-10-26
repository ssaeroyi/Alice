<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\IntegrationCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function integrationStatusAdmin(): User
{
    return User::factory()->create(['role' => Role::Admin]);
}

test('only admins can open the status page', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)->get(route('admin.status'))->assertForbidden();

    $this->actingAs(integrationStatusAdmin())->get(route('admin.status'))->assertOk();
});

test('the status page lists every service with its badge', function () {
    $this->actingAs(integrationStatusAdmin())
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('External services')
        ->assertSee('Harper')
        ->assertSee('Open Library covers')
        ->assertSee('Wikipedia covers')
        ->assertSee('On-device', false)
        ->assertSee('No checks yet')
        ->assertSee('Test all')
        ->assertSee('Test now');
});

test('testing one integration runs its probe, records it and audits the action', function () {
    $admin = integrationStatusAdmin();

    $this->actingAs($admin)
        ->post(route('admin.status.integrations.test', 'harper'))
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'success' && str_contains($toast['message'], 'Harper'));

    $check = IntegrationCheck::latest('harper');

    expect($check)->not->toBeNull()
        ->and($check->ok)->toBeTrue()
        ->and($check->latency_ms)->toBeGreaterThanOrEqual(0)
        ->and(AuditLog::where('action', 'system.integration_tested')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('a failed probe is recorded as failed and reported in the toast', function () {
    config(['stories.grammar.binary' => '/vendor/harper/missing.wasm']);

    $this->actingAs(integrationStatusAdmin())
        ->post(route('admin.status.integrations.test', 'harper'))
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'error' && str_contains($toast['message'], 'failed'));

    expect(IntegrationCheck::latest('harper')->ok)->toBeFalse();
});

test('testing all integrations probes every service in one go', function () {
    Http::fake(['openlibrary.org/*' => Http::response(['docs' => []]), 'en.wikipedia.org/*' => Http::response(['query' => []])]);

    $this->actingAs(integrationStatusAdmin())
        ->post(route('admin.status.integrations.test-all'))
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'success' && str_contains($toast['message'], 'All 3 external services are working'));

    expect(IntegrationCheck::latest('harper')->ok)->toBeTrue()
        ->and(IntegrationCheck::latest('open_library')->ok)->toBeTrue()
        ->and(IntegrationCheck::latest('wikipedia')->ok)->toBeTrue();
});

test('testing an unknown integration returns a 404', function () {
    $this->actingAs(integrationStatusAdmin())
        ->post(route('admin.status.integrations.test', 'not-a-service'))
        ->assertNotFound();
});

test('the auto-probe command probes every enabled service', function () {
    Http::fake(['openlibrary.org/*' => Http::response(['docs' => []]), 'en.wikipedia.org/*' => Http::response(['query' => []])]);

    $this->artisan('alice:probe-integrations')->assertSuccessful();

    expect(IntegrationCheck::latest('harper')->ok)->toBeTrue()
        ->and(IntegrationCheck::latest('open_library')->ok)->toBeTrue()
        ->and(IntegrationCheck::latest('wikipedia')->ok)->toBeTrue();
});

test('the history strip shows the last checks for each service', function () {
    IntegrationCheck::record('harper', true, 100, 'OK');
    IntegrationCheck::record('harper', false, 300, 'Down');
    IntegrationCheck::record('harper', true, 90, 'OK');

    $this->actingAs(integrationStatusAdmin())
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Last 3 checks')
        ->assertSee('100 ms', false);
});
