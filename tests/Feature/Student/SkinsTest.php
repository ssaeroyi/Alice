<?php

use App\Enums\Role;
use App\Models\KeyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function skinsShopStudent(int $keys = 0, array $preferences = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'reading_level' => 8,
        'is_setup_complete' => true,
        'total_keys' => $keys,
        'preferences' => $preferences,
    ]);
}

it('sells a priced skin: debits keys, writes the ledger, grants ownership, never auto-equips', function () {
    $student = skinsShopStudent(60);

    $res = $this->actingAs($student)
        ->postJson(route('skins.buy', 'schoolgirl2'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('keys', 10)
        ->assertJsonPath('equipped.alice', 'schoolgirl1'); // unlock != equip

    expect($student->refresh()->total_keys)->toBe(10)
        ->and($res->json('owned'))->toContain('schoolgirl2');

    $tx = KeyTransaction::where('user_id', $student->id)->where('reason', 'skin_buy')->sole();
    expect($tx->amount)->toBe(-50)
        ->and($tx->metadata['skin'] ?? null)->toBe('schoolgirl2');

    // Re-buying an owned key is refused before any second debit.
    $this->actingAs($student)
        ->postJson(route('skins.buy', 'schoolgirl2'))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'owned');
    expect($student->refresh()->total_keys)->toBe(10);
});

it('refuses a purchase when keys are short and changes nothing', function () {
    $student = skinsShopStudent(49);

    $this->actingAs($student)
        ->postJson(route('skins.buy', 'schoolgirl2'))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'keys');

    $owned = $this->actingAs($student)->getJson(route('skins.index'))->assertOk()->json('owned');

    expect($student->refresh()->total_keys)->toBe(49)
        ->and(KeyTransaction::where('user_id', $student->id)->where('reason', 'skin_buy')->count())->toBe(0)
        ->and($owned)->not->toContain('schoolgirl2');
});

it('refuses unpriced default purchases as unpriced and gates equip on ownership', function () {
    $student = skinsShopStudent(100);

    // Free default has no price: the buy endpoint says so honestly.
    $this->actingAs($student)
        ->postJson(route('skins.buy', 'schoolgirl1'))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'unpriced');

    // Equip requires ownership: priced skin first...
    $this->actingAs($student)
        ->postJson(route('skins.equip', 'sakura'))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'not_owned');

    // ...then the owned default works and switches the slot.
    $this->actingAs($student)
        ->postJson(route('skins.equip', 'schoolgirl1'))
        ->assertOk()
        ->assertJsonPath('equipped.alice', 'schoolgirl1');

    expect($student->refresh()->total_keys)->toBe(100);
});

it('falls back to the free defaults for unequipped-but-unowned, removed, or missing slots', function () {
    // Priced skins equipped before prices shipped + a removed key still
    // lingering in prefs/owned_skins: none of them may stick.
    $student = skinsShopStudent(0, [
        'alice_skin' => 'sakura',
        'boss_skin' => 'vampire_bat',
        'background_skin' => 'war1',
        'owned_skins' => ['dragon'], // removed key still lingering in prefs
    ]);

    $state = $this->actingAs($student)->getJson(route('skins.index'))->assertOk();

    $state->assertJsonPath('equipped.alice', 'schoolgirl1')
        ->assertJsonPath('equipped.boss', 'demon')
        ->assertJsonPath('equipped.background', 'forest')
        ->assertJsonPath('owned.0', 'schoolgirl1');

    expect($state->json('owned'))->not->toContain('dragon');
});

it('keeps a bought background equippable and live in the state payload', function () {
    $student = skinsShopStudent(60);

    $this->actingAs($student)
        ->postJson(route('skins.buy', 'war1'))
        ->assertOk()
        ->assertJsonPath('keys', 10);

    $this->actingAs($student)
        ->postJson(route('skins.equip', 'war1'))
        ->assertOk()
        ->assertJsonPath('equipped.background', 'war1');

    expect($student->refresh()->total_keys)->toBe(10)
        ->and($student->preferences['background_skin'] ?? null)->toBe('war1');
});
