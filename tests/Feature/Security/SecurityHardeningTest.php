<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('every page sends the core security headers', function () {
    $this->get(route('home'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), geolocation=()');
});

test('HSTS is only sent over a secure production connection', function () {
    $this->get(route('home'))
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('HSTS is sent over secure production connections', function () {
    app()->instance('env', 'production');

    try {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    } finally {
        app()->instance('env', 'testing');
    }
});

test('failed logins are recorded in login_attempts', function () {
    $this->post(route('login.submit'), [
        'email' => 'brute@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertDatabaseHas('login_attempts', [
        'email' => 'brute@example.com',
        'successful' => false,
    ]);
});

test('successful logins are recorded in login_attempts', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post(route('login.submit'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('placement.step1'));

    $this->assertDatabaseHas('login_attempts', [
        'email' => $user->email,
        'successful' => true,
    ]);
});

test('request telemetry records the client IP and user agent', function () {
    $this->withHeader('User-Agent', 'Alice-Test-Agent/1.0')
        ->get(route('home'));

    $row = DB::table('request_logs')->latest('id')->first();

    expect($row->ip_address)
        ->toBeString()
        ->not->toBeEmpty();

    expect($row->user_agent)->toBe('Alice-Test-Agent/1.0');
});

test('the admin status security overview shows top IPs and login attempts', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    DB::table('request_logs')->insert([
        ['method' => 'GET', 'path' => 'hub', 'status' => 200, 'duration_ms' => 20, 'ip_address' => '10.0.0.7', 'user_agent' => 'A', 'created_at' => now()->subMinutes(5)],
        ['method' => 'GET', 'path' => 'admin/status', 'status' => 403, 'duration_ms' => 10, 'ip_address' => '10.0.0.9', 'user_agent' => 'B', 'created_at' => now()->subMinutes(3)],
        ['method' => 'GET', 'path' => 'admin/settings', 'status' => 403, 'duration_ms' => 10, 'ip_address' => '10.0.0.9', 'user_agent' => 'B', 'created_at' => now()->subMinutes(2)],
    ]);

    DB::table('login_attempts')->insert([
        ['email' => 'ok@example.com', 'ip_address' => '10.0.0.1', 'user_agent' => 'C', 'successful' => true, 'created_at' => now()->subMinutes(4)],
        ['email' => 'bad@example.com', 'ip_address' => '10.0.0.2', 'user_agent' => 'D', 'successful' => false, 'created_at' => now()->subMinutes(1)],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.status'))
        ->assertOk()
        ->assertSee('Security overview')
        ->assertSee('10.0.0.7')
        ->assertSee('bad@example.com');
});
