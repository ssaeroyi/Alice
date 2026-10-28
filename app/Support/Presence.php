<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Presence readings shared by the teacher roster, the admin accounts page
 * and the dashboards. "Online" means the account touched the app within
 * the last five minutes (the heartbeat updates last_seen_at at most once
 * a minute per request); "recent" covers the last day; anything else is
 * "away" and shown with the plain last-active time.
 */
class Presence
{
    public static function state(?CarbonInterface $lastActiveAt): string
    {
        if ($lastActiveAt === null) {
            return 'away';
        }

        if ($lastActiveAt->gt(now()->subMinutes(5))) {
            return 'online';
        }

        return $lastActiveAt->gt(now()->subDay()) ? 'recent' : 'away';
    }

    public static function label(?CarbonInterface $lastActiveAt): string
    {
        if ($lastActiveAt === null) {
            return 'Never';
        }

        return match (self::state($lastActiveAt)) {
            'online' => 'Online now',
            'recent' => $lastActiveAt->diffForHumans(),
            default => 'Last seen '.$lastActiveAt->diffForHumans(),
        };
    }

    /** Dot color classes for the shared status dot. */
    public static function dotClass(string $state): string
    {
        return match ($state) {
            'online' => 'bg-emerald-500',
            'recent' => 'bg-amber-400',
            default => 'bg-stone-300',
        };
    }

    /**
     * Compact time token for tight list rows: 5m, 2h, 3d, 1w, then the date.
     * "Online now" keeps its words — the green dot alone is not clear enough.
     */
    public static function token(?CarbonInterface $lastActiveAt): string
    {
        if ($lastActiveAt === null) {
            return '—';
        }

        if ($lastActiveAt->gt(now()->subMinutes(5))) {
            return 'Now';
        }

        return match (true) {
            $lastActiveAt->gt(now()->subHour()) => (int) ($lastActiveAt->floatDiffInMinutes(now())).'m ago',
            $lastActiveAt->gt(now()->subDay()) => (int) ($lastActiveAt->floatDiffInHours(now())).'h ago',
            $lastActiveAt->gt(now()->subWeek()) => max(1, (int) floor($lastActiveAt->floatDiffInHours(now()) / 24)).'d ago',
            $lastActiveAt->gt(now()->subDays(14)) => max(1, (int) floor($lastActiveAt->floatDiffInDays(now()) / 7)).'w ago',
            default => $lastActiveAt->format('M j'),
        };
    }
}
