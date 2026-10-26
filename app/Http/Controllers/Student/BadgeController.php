<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BadgeController extends Controller
{
    /**
     * BADGES shelf page (S8): every badge from the config catalog, in catalog
     * order, with the student's earned state so the shelf can tint earned vs
     * locked tiles.
     */
    public function index(Request $request): View
    {
        $defs = config('badges.badges', []);

        $badges = Badge::query()
            ->whereIn('key', array_column($defs, 'key'))
            ->get()
            ->keyBy('key');

        $ownedByBadge = UserBadge::query()
            ->where('user_id', $request->user()->id)
            ->pluck('awarded_at', 'badge_id');

        $rows = collect();

        foreach ($defs as $def) {
            $badge = $badges->get($def['key']);

            if ($badge === null) {
                continue;
            }

            $awardedAt = $ownedByBadge->get($badge->id);

            $rows->push([
                'badge' => $badge,
                'rarity' => $badge->rarity->value,
                'icon' => str_replace('ui/', '', (string) $badge->asset_path),
                'category' => $def['category'] ?? 'stories',
                'earned' => $awardedAt !== null,
                'awarded_at' => $awardedAt,
            ]);
        }

        $now = Carbon::now();

        return view('student.badges', [
            'rows' => $rows,
            'earnedCount' => $ownedByBadge->count(),
            'totalCount' => $rows->count(),
            'progressValues' => BadgeService::progressValues($request->user(), $now),
            'unlockStats' => BadgeService::unlockStats(),
            'now' => $now,
        ]);
    }
}
