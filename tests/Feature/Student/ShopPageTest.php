<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from the shop', function () {
    $this->get(route('shop'))->assertRedirect(route('login'));
});

test('the shop renders the wardrobe catalog and the streak restore sink', function () {
    $student = User::factory()->create(['is_setup_complete' => true, 'total_keys' => 120]);

    $alice = (array) config('skins.alice');
    $priced = collect($alice)->first(fn (array $skin) => ($skin['price'] ?? null) !== null);

    $this->actingAs($student)
        ->get(route('shop'))
        ->assertOk()
        ->assertSee('Shop')
        ->assertSee('Wardrobe')
        ->assertSee('Streak Restores')
        ->assertSee($alice['schoolgirl1']['name'])
        ->assertSee($priced['name'])
        ->assertSee('data-skins-grid', false)
        ->assertSee('id="skin-preview"', false)
        ->assertSee(number_format(120), false)
        ->assertSee('15 keys each')
        ->assertSee(route('rewards.freeze.buy'), false);
});

test('the profile dropdown links the shop from the app section', function () {
    $student = User::factory()->create(['is_setup_complete' => true]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee(route('shop'), false);
});

test('staff never reach the student shop', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher, 'is_setup_complete' => true]);

    $this->actingAs($teacher)->get(route('shop'))->assertForbidden();
});

test('the shop refuses the restore form when the cap is reached', function () {
    $student = User::factory()->create([
        'is_setup_complete' => true,
        'total_keys' => 0,
        'streak_freezes' => (int) config('gamification.freeze.hold_cap'),
    ]);

    $this->actingAs($student)
        ->get(route('shop'))
        ->assertOk()
        ->assertSee('At cap')
        ->assertDontSee(route('rewards.freeze.buy'), false);
});

test('the wardrobe payload carries owned and equipped state for the grid', function () {
    $student = User::factory()->create(['is_setup_complete' => true, 'total_keys' => 500]);

    // Defaults stay equipped; the bought skin is owned but not equipped.
    $student->update(['preferences' => ['owned_skins' => ['schoolgirl2']]]);

    $html = $this->actingAs($student)->get(route('shop'))->assertOk()->getContent();

    preg_match("/data-skins='([^']*)'/", $html, $match);
    $payload = json_decode(html_entity_decode($match[1] ?? '', ENT_QUOTES), true);

    expect($payload['state']['equipped']['alice'])->toBe('schoolgirl1')
        ->and($payload['state']['owned'])->toContain('schoolgirl2')
        ->and($payload['state']['keys'])->toBe(500)
        ->and($payload['catalog']['background'])->toHaveKey('forest');
});
