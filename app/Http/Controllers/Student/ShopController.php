<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop (student): the key-sink storefront on its own page, linked from
 * the profile dropdown's APP section. Holds the Field Journal wardrobe
 * (cards render here, buy/equip run through the existing /skins JSON
 * endpoints) and the Streak Restore sink (plain form POST, same card
 * as MY STATS). The keys chip shares data-keys-total with the layout so
 * a purchase updates every visible counter.
 */
class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $skinsCatalog = config('skins');
        $skinsCatalog['background'] = config('backgrounds.list');

        $skinsState = SkinsController::state($user);

        return view('student.shop', [
            'skinsPayload' => ['catalog' => $skinsCatalog, 'state' => $skinsState],
            'skinsState' => $skinsState,
            'freezeHeld' => (int) $user->streak_freezes,
            'freezeCost' => (int) config('gamification.sinks.streak_freeze'),
            'freezeCap' => (int) config('gamification.freeze.hold_cap'),
        ]);
    }
}
