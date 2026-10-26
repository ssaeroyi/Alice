<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Field Journal wardrobe: catalog status, key-priced purchases (ledger via
 * RewardService::spend) and equip. State lives in users.preferences JSON
 * (owned_skins / alice_skin / boss_skin); the defaults are always owned.
 */
class SkinsController extends Controller
{
    /** GET /skins — current keys + owned + equipped (catalog ships in the page). */
    public function index(Request $request): JsonResponse
    {
        return response()->json(self::state($request->user()));
    }

    /** POST /skins/{key}/buy — debits keys, unlocks the skin. */
    public function buy(Request $request, string $key): JsonResponse
    {
        $skin = self::find($key);

        if ($skin === null) {
            return response()->json(['ok' => false, 'reason' => 'not_found'], 404);
        }
        if ($skin['price'] === null) {
            return response()->json(['ok' => false, 'reason' => 'unpriced'], 422);
        }

        $user = $request->user();

        if (in_array($key, self::owned($user), true)) {
            return response()->json(['ok' => false, 'reason' => 'owned'], 422);
        }

        $ok = DB::transaction(function () use ($user, $key, $skin) {
            if (! RewardService::spend($user, (int) $skin['price'], 'skin_buy', 'skin', null, ['skin' => $key])) {
                return false;
            }

            $prefs = $user->preferences ?? [];
            $prefs['owned_skins'] = array_values(array_unique(array_merge($prefs['owned_skins'] ?? [], [$key])));
            $user->update(['preferences' => $prefs]);

            return true;
        });

        if (! $ok) {
            return response()->json(['ok' => false, 'reason' => 'keys'], 422);
        }

        $user->refresh();

        return response()->json(['ok' => true] + self::state($user));
    }

    /** POST /skins/{key}/equip — must be owned; swaps the active slot. */
    public function equip(Request $request, string $key): JsonResponse
    {
        $skin = self::find($key);

        if ($skin === null) {
            return response()->json(['ok' => false, 'reason' => 'not_found'], 404);
        }

        $user = $request->user();

        if (! in_array($key, self::owned($user), true)) {
            return response()->json(['ok' => false, 'reason' => 'not_owned'], 422);
        }

        $prefs = $user->preferences ?? [];
        $prefs[$skin['type'] . '_skin'] = $key;
        $user->update(['preferences' => $prefs]);

        return response()->json(['ok' => true] + self::state($user));
    }

    /** Shared payload for every response (fresh reads only). */
    public static function state(User $user): array
    {
        $prefs = $user->preferences ?? [];
        $owned = self::owned($user);
        $alice = $prefs['alice_skin'] ?? null;
        $boss = $prefs['boss_skin'] ?? null;
        $background = $prefs['background_skin'] ?? null;

        // Ownership guard: a slot only sticks when the key exists in the
        // catalog AND is owned. Priced skins equipped before prices shipped
        // fall back to the free defaults (no equipped-but-locked state);
        // removed keys still present in owned_skins are ignored too.
        if (! isset(config('skins.alice')[$alice ?? '']) || ! in_array($alice, $owned, true)) {
            $alice = config('skins.default_alice');
        }
        if (! isset(config('skins.boss')[$boss ?? '']) || ! in_array($boss, $owned, true)) {
            $boss = config('skins.default_boss');
        }
        if (! isset(config('backgrounds.list')[$background ?? '']) || ! in_array($background, $owned, true)) {
            $background = config('backgrounds.default');
        }

        return [
            'keys' => (int) $user->total_keys,
            'owned' => $owned,
            'equipped' => ['alice' => $alice, 'boss' => $boss, 'background' => $background],
        ];
    }

    /**
     * Owned = preferences list + both defaults + every unpriced skin.
     * Unpriced skins are free to try; setting a price in config/skins.php
     * automatically flips that skin to the locked/purchase flow.
     */
    public static function owned(User $user): array
    {
        $prefs = $user->preferences ?? [];
        $owned = [config('skins.default_alice'), config('skins.default_boss')];

        foreach (['alice', 'boss'] as $type) {
            foreach ((array) config("skins.$type", []) as $key => $skin) {
                if (($skin['price'] ?? null) === null) {
                    $owned[] = $key;
                }
            }
        }
        foreach ((array) config('backgrounds.list', []) as $key => $bg) {
            if (($bg['price'] ?? null) === null) {
                $owned[] = $key;
            }
        }

        // Ghosts (removed keys still sitting in owned_skins, e.g. dragon)
        // never reach the payload: owned is always a subset of the catalog.
        $catalogKeys = array_merge(
            array_keys((array) config('skins.alice', [])),
            array_keys((array) config('skins.boss', [])),
            array_keys((array) config('backgrounds.list', [])),
        );

        return array_values(array_intersect(
            array_unique(array_merge($owned, $prefs['owned_skins'] ?? [])),
            $catalogKeys,
        ));
    }

    /** @return array{type: string, price: int|null}|null */
    private static function find(string $key): ?array
    {
        foreach (['alice', 'boss'] as $type) {
            if (isset(config('skins.' . $type)[$key])) {
                return ['type' => $type, 'price' => config("skins.$type.$key.price")];
            }
        }
        if (isset(config('backgrounds.list')[$key])) {
            return ['type' => 'background', 'price' => config("backgrounds.list.$key.price")];
        }

        return null;
    }
}
