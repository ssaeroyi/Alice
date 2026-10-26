<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('info popovers render on the control, status and dashboard pages', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.control'))
        ->assertOk()
        ->assertSee('data-info-toggle', false)
        ->assertSee('This is the database backup tool', false)
        ->assertSee('Nothing is stored on the server');

    $this->get(route('admin.status'))
        ->assertOk()
        ->assertSee('data-info-toggle', false)
        ->assertSee('95% of requests', false);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-info-toggle', false);
});

test('risky control actions require a confirmation checkbox', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.control'))
        ->assertOk()
        ->assertSee('id="signout-all-modal"', false)
        ->assertSee('data-double-confirm', false)
        ->assertSee('id="clear-jobs-modal"', false)
        ->assertSee('I understand. Everyone except me will be signed out')
        ->assertSee('I understand. These tasks will be deleted forever');
});

test('suspending an account confirms without a checkbox', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($admin)
        ->get(route('admin.accounts.show', $student))
        ->assertOk()
        ->assertSee('id="suspend-modal"', false)
        ->assertSee('data-confirm-approve', false)
        ->assertDontSee('data-double-confirm', false)
        ->assertSee('Suspend account');
});

test('the logs page offers a unified date-range picker', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('data-range-picker', false)
        ->assertSee('Last 1 hour')
        ->assertSee('Last 24 hours')
        ->assertSee('Last 7 days')
        ->assertSee('Last 30 days')
        ->assertSee('All time')
        ->assertSee('Custom range')
        ->assertSee('logs-filter-form', false);

    $this->get(route('admin.logs.index', ['preset' => 'last_7_days']))
        ->assertOk()
        ->assertSee('Last 7 days ('.now()->subDays(6)->format('M j').' – '.now()->format('M j, Y').')');
});

test('button tones are applied on the control page', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.control'))
        ->assertOk()
        ->assertSee('bg-emerald-600', false)
        ->assertSee('bg-rose-600', false)
        ->assertSee('bg-amber-600', false);
});
