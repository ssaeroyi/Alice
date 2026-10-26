<?php

namespace App\Support;

use App\Enums\PlacementAttemptStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the per-student activity stream for the teacher portal.
 *
 * Sources (all real, existing data — no synthetic events):
 *  - attributed request telemetry (page visits, grouped by student+path+day)
 *  - account creation (joined)
 *  - completed placement attempts (quick check finished)
 *
 * Privacy note: request telemetry is collected for every web request; the
 * admin Traffic panel reads it as anonymous aggregates, while the teacher
 * side reads it per student. Only students appear here — staff requests are
 * never surfaced.
 */
class StudentActivity
{
    /** Student-facing paths that show up in the stream, plus prefixes. */
    public const VISIT_EXACT = ['hub', 'library', 'gym', 'game', 'settings'];

    public const VISIT_PREFIXES = ['step', 'placement/'];

    /**
     * The school-wide stream, newest first.
     *
     * @return Collection<int, array{type: string, user: User, label: string, count: int, at: Carbon, meta: array<string, mixed>}>
     */
    public static function stream(int $limit = 60, ?int $userId = null, ?string $type = null, ?int $days = null): Collection
    {
        $visits = $type === 'milestone' ? collect() : self::visits($limit * 4, $userId, $days);
        $milestones = $type === 'visit' ? collect() : self::milestones($limit * 4, $userId, $days);

        return $visits
            ->concat($milestones)
            ->sortByDesc('at')
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * A single student's recent activity for the detail page.
     *
     * @return Collection<int, array{type: string, user: User, label: string, count: int, at: Carbon, meta: array<string, mixed>}>
     */
    public static function recentFor(User $student, int $limit = 8): Collection
    {
        return self::stream($limit, $student->id);
    }

    /**
     * Page visits from attributed request telemetry, grouped per
     * student + path + day so a refresh burst reads as one row.
     *
     * @return Collection<int, array{type: string, user: User, label: string, count: int, at: Carbon, meta: array<string, mixed>}>
     */
    private static function visits(int $limit, ?int $userId, ?int $days): Collection
    {
        $rows = DB::table('request_logs')
            ->join('users', 'users.id', '=', 'request_logs.user_id')
            ->where('users.role', Role::Student->value)
            ->whereNot('users.email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
            ->whereNot('users.email', 'like', '%@example.com')
            ->whereNot('users.email', 'like', '%@alice.test')
            ->where(function ($query) {
                $query->whereIn('request_logs.path', self::VISIT_EXACT)
                    ->orWhere(function ($or) {
                        foreach (self::VISIT_PREFIXES as $prefix) {
                            $or->orWhere('request_logs.path', 'like', $prefix.'%');
                        }
                    });
            })
            ->when($userId, fn ($query) => $query->where('request_logs.user_id', $userId))
            ->when($days, fn ($query) => $query->where('request_logs.created_at', '>=', now()->subDays($days)))
            ->groupBy('request_logs.user_id', 'request_logs.path', 'day')
            ->selectRaw('request_logs.user_id, request_logs.path, DATE(request_logs.created_at) AS day, COUNT(*) AS count, MAX(request_logs.created_at) AS at')
            ->orderByDesc('at')
            ->limit($limit)
            ->get();

        $users = User::realAccounts()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);

            return [
                'type' => 'visit',
                'user' => $user,
                'label' => self::pageLabel($row->path),
                'count' => (int) $row->count,
                'at' => Carbon::parse($row->at),
                'meta' => ['path' => $row->path],
            ];
        })->filter(fn ($item) => $item['user'] !== null);
    }

    /**
     * Milestones: account creation + completed placement attempts.
     *
     * @return Collection<int, array{type: string, user: User, label: string, count: int, at: Carbon, meta: array<string, mixed>}>
     */
    private static function milestones(int $limit, ?int $userId, ?int $days): Collection
    {
        $joins = User::realAccounts()
            ->where('role', Role::Student)
            ->when($userId, fn ($query) => $query->where('id', $userId))
            ->when($days, fn ($query) => $query->where('created_at', '>=', now()->subDays($days)))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'type' => 'joined',
                'user' => $user,
                'label' => 'joined ALICE',
                'count' => 1,
                'at' => $user->created_at,
                'meta' => [],
            ]);

        $attempts = DB::table('placement_attempts')
            ->join('users', 'users.id', '=', 'placement_attempts.user_id')
            ->where('placement_attempts.status', PlacementAttemptStatus::Completed->value)
            ->where('users.role', Role::Student->value)
            ->whereNot('users.email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
            ->whereNot('users.email', 'like', '%@example.com')
            ->whereNot('users.email', 'like', '%@alice.test')
            ->when($userId, fn ($query) => $query->where('placement_attempts.user_id', $userId))
            ->when($days, fn ($query) => $query->whereNotNull('placement_attempts.completed_at')->where('placement_attempts.completed_at', '>=', now()->subDays($days)))
            ->orderByDesc('placement_attempts.completed_at')
            ->limit($limit)
            ->get(['placement_attempts.user_id', 'placement_attempts.completed_at', 'users.nickname', 'users.email']);

        $attemptUsers = User::realAccounts()->whereIn('id', $attempts->pluck('user_id'))->get()->keyBy('id');

        $completions = $attempts->map(function ($attempt) use ($attemptUsers) {
            return [
                'type' => 'quick_check',
                'user' => $attemptUsers->get($attempt->user_id),
                'label' => 'finished the placement quick check',
                'count' => 1,
                'at' => Carbon::parse($attempt->completed_at),
                'meta' => [],
            ];
        })->filter(fn ($item) => $item['user'] !== null);

        return $joins->concat($completions)->sortByDesc('at')->values();
    }

    /**
     * A friendly label for a request path.
     */
    public static function pageLabel(string $path): string
    {
        return match (true) {
            $path === 'hub' => 'opened the Hub',
            $path === 'library' => 'opened the Library',
            $path === 'gym' => 'opened the Word Gym',
            $path === 'game' => 'opened Mini Games',
            $path === 'settings' => 'opened Settings',
            str_starts_with($path, 'step') => 'worked on the placement quick check',
            str_starts_with($path, 'placement/') => 'viewed the quick-check report',
            default => 'visited '.$path,
        };
    }
}
