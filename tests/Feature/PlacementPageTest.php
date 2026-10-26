<?php

use App\Models\User;
use Database\Seeders\PlacementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlacementSeeder::class);
});

test('a fresh user can start placement at step 1', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('placement.step1'))
        ->assertOk();
});

test('a fresh user is redirected from later steps back to step 1', function () {
    $user = User::factory()->create();

    foreach (['placement.intro', 'placement.step2', 'placement.step3', 'placement.step4', 'placement.step5'] as $routeName) {
        $this->actingAs($user)
            ->get(route($routeName))
            ->assertRedirect(route('placement.step1'));
    }
});

test('guests are redirected to login when visiting any placement step', function () {
    foreach (['placement.step1', 'placement.intro', 'placement.step2', 'placement.step3', 'placement.step4', 'placement.step5'] as $routeName) {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }
});
