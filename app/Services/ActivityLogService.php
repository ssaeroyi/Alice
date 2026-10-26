<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Badge;
use App\Models\KeyTransaction;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\StreakLeaderboardSnapshot;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Steam-style activity feed: one merged, newest-first timeline built from
 * the key ledger, story progress, earned badges and daily stamps. Every source
 * is capped so the merge stays O(bounded) for heavy users, and never a single
 * query per event (titles are batch-resolved). Purely a read model - nothing
 * here writes.
 */
class ActivityLogService
{
    private const CAP_KEYS = 300;

    private const CAP_READING = 200;

    private const CAP_BADGES = 200;

    private const CAP_STAMPS = 400;

    /**
     * Display copy for each known key-ledger reason. A null subtitle means the
     * event falls back to the linked subject (story titles) or a generic line,
     * and an unknown reason is mapped to a neutral earned/spent row.
     *
     * @return array{title: string, subtitle: ?string}|null
     */
    private function reasonInfo(string $reason): ?array
    {
        return match ($reason) {
            'reading_goal' => ['title' => 'Daily reading goal', 'subtitle' => 'Hit your reading minutes for the day'],
            'study_goal' => ['title' => 'Daily study goal', 'subtitle' => "Cleared today's vocabulary target"],
            'daily_task' => ['title' => 'Daily task complete', 'subtitle' => 'Claimed a finished task'],
            'task_reroll' => ['title' => 'Task reroll', 'subtitle' => "Swapped today's task"],
            'story_clear' => ['title' => 'Story finished', 'subtitle' => null],
            'game_round' => ['title' => 'Mini-game bonus', 'subtitle' => 'Scored keys from a game round'],
            'quiz_first_clear' => ['title' => 'First perfect quiz', 'subtitle' => null],
            'quiz_first_attempt' => ['title' => 'First quiz attempt', 'subtitle' => null],
            'quiz_improvement' => ['title' => 'Quiz improvement', 'subtitle' => null],
            'drill_first_daily' => ['title' => 'Flashcard drill bonus', 'subtitle' => 'First drill of the day'],
            'welcome_kit' => ['title' => 'Welcome kit', 'subtitle' => 'Keys to start your adventure'],
            'hint_token' => ['title' => 'Quiz hint', 'subtitle' => 'Used a hint token'],
            'streak_freeze', 'freeze' => ['title' => 'Streak Restore', 'subtitle' => 'Bought with keys'],
            default => null,
        };
    }

    /**
     * The full merged timeline, newest first.
     *
     * @return Collection<int, array{at: Carbon, type: string, icon: string, icon_class: string, title: string, subtitle: ?string, delta: ?int, balance: ?int}>
     */
    public function timeline(User $user): Collection
    {
        return collect()
            ->merge($this->keyEvents($user))
            ->merge($this->readingEvents($user))
            ->merge($this->badgeEvents($user))
            ->merge($this->stampEvents($user))
            ->sortByDesc('micro')
            ->values();
    }

    /**
     * Manual pagination over an already-sorted event list, keeping the
     * original query string (tab/month) on the pager links.
     */
    public function paginate(Collection $events, int $perPage, int $page, string $tab): LengthAwarePaginator
    {
        $total = $events->count();
        $items = $events->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            max(1, $page),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => ['tab' => $tab],
            ],
        );
    }

    /**
     * A short "X ago"-style label, localised and safe for narrow screens.
     */
    public function relativeTime(Carbon $at, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $seconds = max(0, (int) $now->diffInSeconds($at));

        if ($seconds < 60) {
            return 'Just now';
        }
        if ($seconds < 3600) {
            return (int) floor($seconds / 60).'m ago';
        }

        $startOfToday = $now->copy()->startOfDay();
        $startOfAt = $at->copy()->startOfDay();

        if ($startOfAt->equalTo($startOfToday)) {
            return 'Today, '.$at->format('g:i A');
        }
        if ($startOfAt->equalTo($startOfToday->copy()->subDay())) {
            return 'Yesterday, '.$at->format('g:i A');
        }
        if ($at->year === $now->year) {
            return $at->format('M j, g:i A');
        }

        return $at->format('M j Y');
    }

    /**
     * Global streak leaderboard: active students sorted by current streak,
     * ties broken by longest streak then join order - sequential unique
     * ranks, matching selfRank(). The board is frozen once per week
     * (Monday) into a snapshot table so students compete against a fixed
     * top 10 for the whole week.
     *
     * @return array{entries: array<int, array{rank: int, name: string, streak: int, longest: int, is_self: bool}>, selfRank: int, selfInTop: bool, weekStart: string}
     */
    public function streakBoard(User $user, int $limit = 10): array
    {
        $weekStart = Carbon::now()->startOfWeek()->toDateString();

        $this->freezeWeeklySnapshot($weekStart, $limit);

        $snaps = StreakLeaderboardSnapshot::query()
            ->where('week_start', $weekStart)
            ->orderBy('rank')
            ->get();

        $entries = [];
        foreach ($snaps as $snap) {
            $entries[] = [
                'rank' => (int) $snap->rank,
                'name' => $snap->display_name,
                'avatar' => $snap->avatar,
                'streak' => (int) $snap->streak,
                'longest' => 0,
                'is_self' => (int) $snap->user_id === (int) $user->id,
            ];
        }

        $base = User::query()
            ->realAccounts()
            ->where('role', Role::Student)
            ->whereNull('deleted_at')
            ->whereNull('suspended_at');

        $selfRank = (int) (clone $base)
            ->where(function ($q) use ($user) {
                $q->where('current_streak', '>', $user->current_streak)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('current_streak', $user->current_streak)
                            ->where('longest_streak', '>', $user->longest_streak);
                    })
                    ->orWhere(function ($q3) use ($user) {
                        $q3->where('current_streak', $user->current_streak)
                            ->where('longest_streak', $user->longest_streak)
                            ->where('id', '<', (int) $user->id);
                    });
            })
            ->count() + 1;

        return [
            'entries' => $entries,
            'selfRank' => $selfRank,
            'selfInTop' => collect($entries)->contains(fn ($e) => $e['is_self']),
            'weekStart' => $weekStart,
        ];
    }

    /**
     * Freeze the current top N streaks into the snapshot table for the given
     * week. Idempotent — only writes if no snapshot exists yet for that week.
     */
    private function freezeWeeklySnapshot(string $weekStart, int $limit): void
    {
        $existing = StreakLeaderboardSnapshot::query()
            ->where('week_start', $weekStart)
            ->pluck('rank')
            ->flip();

        if ($existing->isNotEmpty()) {
            return;
        }

        $top = User::query()
            ->realAccounts()
            ->where('role', Role::Student)
            ->whereNull('deleted_at')
            ->whereNull('suspended_at')
            ->orderByDesc('current_streak')
            ->orderByDesc('longest_streak')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'nickname', 'avatar', 'current_streak', 'longest_streak']);

        // Sequential unique ranks (1..N): the table has a unique
        // (week_start, rank) index, so tied streaks must still get distinct
        // ranks - the deterministic order above (streak, longest, id) is the
        // same tie-break selfRank() uses. The transaction keeps a mid-loop
        // failure from leaving a partial board that the $existing guard above
        // would then treat as frozen.
        DB::transaction(function () use ($top, $weekStart) {
            foreach ($top as $i => $row) {
                StreakLeaderboardSnapshot::create([
                    'week_start' => $weekStart,
                    'rank' => $i + 1,
                    'user_id' => $row->id,
                    'display_name' => trim((string) $row->nickname) !== '' ? $row->nickname : 'Student #'.$row->id,
                    'avatar' => $row->avatar,
                    'streak' => (int) $row->current_streak,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function keyEvents(User $user): Collection
    {
        $rows = KeyTransaction::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(self::CAP_KEYS)
            ->get();

        $storyIds = $rows
            ->filter(fn ($r) => $r->subject_type === 'story' && $r->subject_id !== null)
            ->pluck('subject_id')
            ->unique();
        $titles = Story::query()->whereIn('id', $storyIds)->pluck('title', 'id');

        $events = [];
        foreach ($rows as $row) {
            $at = Carbon::parse($row->created_at);
            $info = $this->reasonInfo((string) $row->reason);
            $subtitle = $info['subtitle'] ?? null;

            if ($subtitle === null) {
                if ($row->subject_type === 'story' && $row->subject_id !== null) {
                    $subtitle = (string) ($titles->get((int) $row->subject_id) ?? 'A story you read');
                } else {
                    $subtitle = $row->reason === 'story_clear'
                        ? 'Read a book to the end'
                        : ((int) $row->amount >= 0 ? 'Keys for your efforts' : 'Keys spent');
                }
            }

            $events['key:'.$row->id] = $this->event(
                $at,
                'key',
                'key',
                'text-amber-600 bg-amber-500',
                $info['title'] ?? ((int) $row->amount >= 0 ? 'Keys earned' : 'Keys spent'),
                $subtitle,
                (int) $row->amount,
                (int) $row->balance_after,
            );
        }

        return collect($events);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function readingEvents(User $user): Collection
    {
        $rows = StoryProgress::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(self::CAP_READING)
            ->get();

        $storyIds = $rows->pluck('story_id');
        $titles = Story::query()->whereIn('id', $storyIds)->pluck('title', 'id');
        $slugs = Story::query()->whereIn('id', $storyIds)->pluck('slug', 'id');

        $events = [];
        foreach ($rows as $row) {
            $title = (string) ($titles[$row->story_id] ?? 'A story');
            $slug = (string) ($slugs[$row->story_id] ?? '');

            if ($row->completed_at !== null) {
                $events['read-finish:'.$row->id] = $this->event(
                    Carbon::parse($row->completed_at),
                    'reading',
                    'open-book',
                    'text-emerald-700 bg-emerald-500',
                    'Story finished',
                    $title,
                ) + ['story_id' => (int) $row->story_id, 'story_slug' => $slug];

                continue;
            }

            $at = Carbon::parse($row->updated_at);
            $pct = max(0, min(100, (int) $row->current_page));
            $events['read-progress:'.$row->id] = $this->event(
                $at,
                'reading',
                'open-book',
                'text-emerald-700 bg-emerald-500',
                'Kept reading',
                $title.($pct > 0 ? ', '.$pct.'%' : ''),
            ) + ['story_id' => (int) $row->story_id, 'story_slug' => $slug];
        }

        return collect($events);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function badgeEvents(User $user): Collection
    {
        $rows = UserBadge::query()
            ->where('user_id', $user->id)
            ->latest('awarded_at')
            ->limit(self::CAP_BADGES)
            ->get();

        $names = Badge::query()->whereIn('id', $rows->pluck('badge_id'))->pluck('name', 'id');

        $events = [];
        foreach ($rows as $row) {
            $events['badge:'.$row->id] = $this->event(
                Carbon::parse($row->awarded_at),
                'badge',
                'trophy',
                'text-violet-700 bg-violet-500',
                'Badge earned',
                (string) ($names[$row->badge_id] ?? 'A badge'),
            ) + ['badge_id' => (int) $row->badge_id];
        }

        return collect($events);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function stampEvents(User $user): Collection
    {
        $rows = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->whereNotNull('stamped_at')
            ->latest('stamped_at')
            ->limit(self::CAP_STAMPS)
            ->get();

        $events = [];
        foreach ($rows as $row) {
            $at = Carbon::parse($row->stamped_at);
            $events['stamp:'.$at->toDateString()] = $this->event(
                $at,
                'stamp',
                'flame',
                'text-rose-600 bg-rose-500',
                'Daily stamp',
                'Kept your streak going',
            );
        }

        return collect($events);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(
        Carbon $at,
        string $type,
        string $icon,
        string $iconClass,
        string $title,
        ?string $subtitle = null,
        ?int $delta = null,
        ?int $balance = null,
    ): array {
        return [
            'at' => $at,
            'micro' => $at->getTimestamp() * 1_000_000 + $at->micro,
            'type' => $type,
            'icon' => $icon,
            'icon_class' => $iconClass,
            'title' => $title,
            'subtitle' => $subtitle,
            'delta' => $delta,
            'balance' => $balance,
        ];
    }
}
