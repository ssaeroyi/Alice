<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ActivityLogController extends Controller
{
    private const PAGE_SIZE = 25;

    public function index(Request $request)
    {
        $user = $request->user();
        $service = new ActivityLogService;

        $tab = $request->string('tab', 'everything')->toString();
        $tab = in_array($tab, ['everything', 'keys', 'reading', 'badges', 'streak'], true) ? $tab : 'everything';

        $timeline = $service->timeline($user);
        $page = max(1, (int) $request->integer('page', 1));
        $events = $service->paginate(
            match ($tab) {
                'keys' => $timeline->where('type', 'key')->values(),
                'reading' => $timeline->where('type', 'reading')->values(),
                'badges' => $timeline->where('type', 'badge')->values(),
                default => $timeline,
            },
            self::PAGE_SIZE,
            $page,
            $tab,
        );

        $now = Carbon::now();

        return view('student.activity', [
            'tab' => $tab,
            'events' => $events,
            'relative' => fn (Carbon $at) => $service->relativeTime($at, $now),
            'board' => $service->streakBoard($user),
            'freezeCost' => (int) config('gamification.sinks.streak_freeze'),
            'freezeHeld' => (int) $user->streak_freezes,
            'freezeCap' => (int) config('gamification.freeze.hold_cap'),
            'currentStreak' => (int) $user->current_streak,
            'longestStreak' => (int) $user->longest_streak,
        ]);
    }
}
