<?php

namespace App\Services;

use App\Enums\UserDailyTaskStatus;
use App\Models\DailyTask;
use App\Models\SavedWord;
use App\Models\StoryBattle;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Models\UserDailyTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily-task subsystem (gamification model, slice S7).
 *
 * Three tasks per day, lazily assigned on the first view of a new day (the
 * "nightly job" from §2.6 is realised as an on-demand assignment at read time,
 * which is simpler than cron and identical for a student-facing app). Tasks
 * track progress from server-side activity/domain data, never client claims
 * (B4). Claiming routes every key/point movement through RewardService so the
 * economy axioms hold (ledger rows, single consumer of constants).
 */
class DailyTaskService
{
    public const DAILY_COUNT = 3;

    public const REROLLS_PER_DAY = 1;

    /** Lazily ensure a student holds today's tasks; returns them. */
    public function ensureToday(User $user, Carbon $now): Collection
    {
        $today = $this->today($user, $now);
        if ($today->isNotEmpty()) {
            return $today;
        }

        $pool = DailyTask::query()->where('active', true)->orderBy('id')->get();
        if ($pool->isEmpty()) {
            return collect();
        }

        $rotated = $pool
            ->slice(($now->dayOfYear - 1) % $pool->count())
            ->concat($pool)
            ->unique('id')
            ->take(self::DAILY_COUNT);

        foreach ($rotated as $task) {
            UserDailyTask::create([
                'user_id' => $user->id,
                'daily_task_id' => $task->id,
                'task_date' => $now->toDateString(),
                'progress' => 0,
                'status' => UserDailyTaskStatus::Pending,
            ]);
        }

        return $this->today($user, $now);
    }

    public function today(User $user, Carbon $now): Collection
    {
        return UserDailyTask::query()
            ->where('user_id', $user->id)
            ->whereDate('task_date', $now->toDateString())
            ->with('task')
            ->orderBy('id')
            ->get();
    }

    /** Live, server-computed progress for a day's assignment row. */
    public function progress(UserDailyTask $assignment, Carbon $now): int
    {
        $type = $assignment->task?->type?->value;
        $date = $now->toDateString();

        return match ($type) {
            'read_story' => $this->activityCount($assignment->user_id, $date, 'read_sessions'),
            'gym_drill' => $this->activityCount($assignment->user_id, $date, 'drills_completed'),
            'play_game' => $this->activityCount($assignment->user_id, $date, 'game_rounds'),
            'score_quiz' => TestAttempt::query()
                ->where('user_id', $assignment->user_id)
                ->where('passed', true)
                ->whereDate('created_at', $date)
                ->count(),
            'learn_words' => SavedWord::query()
                ->where('user_id', $assignment->user_id)
                ->whereDate('created_at', $date)
                ->count(),
            // Field Journal: actions finished today (across every battle).
            'battle_action' => StoryBattle::query()
                ->where('story_battles.user_id', $assignment->user_id)
                ->join('story_battle_actions', 'story_battle_actions.story_battle_id', '=', 'story_battles.id')
                ->where('story_battle_actions.status', 'done')
                ->whereDate('story_battle_actions.updated_at', $date)
                ->count(),
            // Field Journal: bosses defeated today.
            'win_battle' => StoryBattle::query()
                ->where('user_id', $assignment->user_id)
                ->where('status', 'won')
                ->whereDate('updated_at', $date)
                ->count(),
            default => 0,
        };
    }

    /** Claim a completed task, awarding keys + points once. */
    public function claim(User $user, UserDailyTask $assignment, Carbon $now): array
    {
        return DB::transaction(function () use ($user, $assignment, $now): array {
            $assign = UserDailyTask::query()->whereKey($assignment->id)->lockForUpdate()->first();

            if ($assign === null || $assign->status === UserDailyTaskStatus::Claimed) {
                return ['ok' => false, 'reason' => 'claimed'];
            }

            $progress = $this->progress($assign, $now);
            if ($progress < (int) $assign->task->target_value) {
                return ['ok' => false, 'reason' => 'incomplete'];
            }

            $assign->progress = $progress;
            $assign->status = UserDailyTaskStatus::Claimed;
            $assign->claimed_at = $now;
            $assign->save();

            $keys = (int) $assign->task->reward_keys;
            $points = (int) $assign->task->reward_xp;

            RewardService::creditTaskReward($user, $keys, $points, 'daily_task', 'daily_task', $assign->task_id);

            return ['ok' => true, 'keys' => $keys, 'points' => $points];
        });
    }

    /** Reroll a pending task to a different type, one per day. */
    public function reroll(User $user, UserDailyTask $assignment, Carbon $now): array
    {
        return DB::transaction(function () use ($user, $assignment, $now): array {
            $assign = UserDailyTask::query()->whereKey($assignment->id)->lockForUpdate()->first();

            if ($assign === null || $assign->status === UserDailyTaskStatus::Claimed) {
                return ['ok' => false, 'reason' => 'claimed'];
            }

            $activity = UserDailyActivity::query()
                ->where('user_id', $user->id)
                ->whereDate('activity_date', $now->toDateString())
                ->lockForUpdate()
                ->first();

            if ($activity === null) {
                $activity = UserDailyActivity::create([
                    'user_id' => $user->id,
                    'activity_date' => $now->toDateString(),
                ]);
            }

            if ((int) $activity->task_rerolls >= self::REROLLS_PER_DAY) {
                return ['ok' => false, 'reason' => 'rerolls'];
            }

            $today = $this->today($user, $now);
            $assignedTaskIds = $today->pluck('daily_task_id')->all();
            $replacement = DailyTask::query()
                ->where('active', true)
                ->whereNotIn('id', array_diff($assignedTaskIds, [$assign->daily_task_id]))
                ->where('id', '!=', $assign->daily_task_id)
                ->orderBy('id')
                ->first();

            if ($replacement === null) {
                return ['ok' => false, 'reason' => 'none'];
            }

            $cost = (int) config('gamification.sinks.task_reroll');

            if (! RewardService::spend($user, $cost, 'task_reroll', 'daily_task', $assign->daily_task_id)) {
                return ['ok' => false, 'reason' => 'keys'];
            }

            $assign->daily_task_id = $replacement->id;
            $assign->progress = 0;
            $assign->status = UserDailyTaskStatus::Pending;
            $assign->save();

            $activity->increment('task_rerolls');

            return ['ok' => true];
        });
    }

    public function rerollsUsed(User $user, Carbon $now): int
    {
        return (int) (($this->activity($user, $now)?->task_rerolls) ?? 0);
    }

    /** Number of today's tasks that are complete and not yet claimed. */
    public function claimableCount(User $user, Carbon $now): int
    {
        return $this->ensureToday($user, $now)
            ->filter(function (UserDailyTask $assignment) use ($now) {
                if ($assignment->status === UserDailyTaskStatus::Claimed) {
                    return false;
                }

                return $this->progress($assignment, $now) >= (int) $assignment->task->target_value;
            })
            ->count();
    }

    /**
     * Live task-state summary for notifications (Phase E). Servers never
     * expose a "completed" flag the student can claim; they expose symmetric
     * counters the UI derives its dots/badges/banners from:
     *
     *   claimable    tasks past target, not yet claimed  → rose nav badge
     *   in_progress  tasks with 0 < progress < target    → amber dot
     *   tasks        full per-task shape (for the TASKS tab / hub)
     */
    public function state(User $user, Carbon $now): array
    {
        $assignments = $this->ensureToday($user, $now);

        $tasks = $assignments->map(function (UserDailyTask $assignment) use ($now) {
            $progress = $this->progress($assignment, $now);
            $target = (int) $assignment->task->target_value;
            $claimed = $assignment->status === UserDailyTaskStatus::Claimed;

            return [
                'id' => $assignment->id,
                'completed' => $progress >= $target,
                'in_progress' => ! $claimed && $progress > 0 && $progress < $target,
                'claimable' => ! $claimed && $progress >= $target,
                'progress' => $progress,
                'target' => $target,
            ];
        });

        return [
            'claimable' => $tasks->where('claimable', true)->count(),
            'in_progress' => $tasks->where('in_progress', true)->count(),
            'tasks' => $tasks->values(),
        ];
    }

    /**
     * Which of today's tasks crossed into claimable since a caller last saw
     * them. `$lastSeen` is a lightweight array of `task_id => claimable`
     * gathered on the previous interaction (heartbeat / activity submit).
     * Compare happens against a fresh snapshot so the "completed just now"
     * banner fires exactly once per transition, even across page loads.
     */
    public function completedNow(User $user, Carbon $now, ?array $lastSeen): array
    {
        $state = $this->state($user, $now);

        if (! is_array($lastSeen)) {
            return $state['tasks']->where('claimable', true)->pluck('id')->all();
        }

        return $state['tasks']
            ->where('claimable', true)
            ->reject(fn (array $task) => ($lastSeen[$task['id']] ?? false) === true)
            ->pluck('id')
            ->all();
    }

    /** Project today's tasks as `task_id => claimable` for delta tracking. */
    public function seenShape(User $user, Carbon $now): array
    {
        return $this->ensureToday($user, $now)
            ->mapWithKeys(fn (UserDailyTask $a) => [$a->id => $this->progress($a, $now) >= (int) $a->task->target_value])
            ->all();
    }

    /**
     * Single-pass snapshot of today's task state. Fetching today's assignment
     * rows once and computing each task's progress once, then deriving every
     * counter/shape from that in-memory snapshot, is the whole point of the
     * syncSeen optimisation: instead of re-running ensureToday()/progress()/
     * state() three times per activity beat (15+ tiny COUNT queries), the
     * activity endpoints get their deltas from one immutable snapshot (4
     * queries: assignments + eager task, plus one COUNT per task type).
     *
     * `progress` and `claimable`/`in_progress` semantics mirror progress() and
     * state() exactly — this is a structural dedupe, not a behaviour change.
     *
     * @return array{
     *   assignments: \Illuminate\Support\Collection<int, UserDailyTask>,
     *   progress: array<int,int>,          id => progress
     *   claimed: array<int,bool>,           id => status is Claimed
     *   claimable: array<int,bool>,         id => ready to claim (not claimed)
     *   in_progress: array<int,bool>,       id => 0 < progress < target
     *   shape: array<int,bool>,             id => progress >= target (mirrors seenShape)
     * }
     */
    private function snapshot(User $user, Carbon $now): array
    {
        $assignments = $this->ensureToday($user, $now);

        $progress = [];
        $claimed = [];
        $claimable = [];
        $in_progress = [];
        $shape = [];

        foreach ($assignments as $assignment) {
            $p = $this->progress($assignment, $now);
            $target = (int) $assignment->task->target_value;
            $isClaimed = $assignment->status === UserDailyTaskStatus::Claimed;

            $progress[$assignment->id] = $p;
            $claimed[$assignment->id] = $isClaimed;
            $claimable[$assignment->id] = ! $isClaimed && $p >= $target;
            $in_progress[$assignment->id] = ! $isClaimed && $p > 0 && $p < $target;
            $shape[$assignment->id] = $p >= $target;
        }

        return [
            'assignments' => $assignments,
            'progress' => $progress,
            'claimed' => $claimed,
            'claimable' => $claimable,
            'in_progress' => $in_progress,
            'shape' => $shape,
        ];
    }

    /**
     * One-call live state for an activity endpoint (Phase E): diffs today's
     * task state against what the last interaction saw (yielding
     * `completed_now` — the tasks that just became claimable), refreshes the
     * session `task_seen` guard, and returns the symmetric counters the UI
     * uses to raise banners and amber dots. Call after the activity lands so
     * the diff observes the fresh progress.
     *
     * Single-pass: builds one snapshot() and derives everything from it, so a
     * heartbeat costs 4 queries instead of the previous ~15.
     *
     * @return array{completed_now: int[], tasks: array{claimable: int, in_progress: int}}
     */
    public function syncSeen(User $user, Carbon $now, Request $request): array
    {
        $snap = $this->snapshot($user, $now);

        $lastSeen = $request->session()->get('task_seen');

        // First interaction: report every currently-claimable task and seed the
        // guard to all-false (mirrors completedNow(..., null)).
        if (! is_array($lastSeen)) {
            $lastSeen = [];
            foreach ($snap['claimable'] as $id => $ready) {
                $lastSeen[$id] = false;
            }
        }

        // Delta = tasks claimable now that were not already seen as claimable.
        $completedNow = [];
        foreach ($snap['claimable'] as $id => $ready) {
            if ($ready && ($lastSeen[$id] ?? false) !== true) {
                $completedNow[] = $id;
            }
        }

        // Merge the fresh shape over the old so tasks that progress but remain
        // incomplete keep their "not yet claimable" marker, while a task that
        // flips to claimable is captured by completedNow above.
        $request->session()->put('task_seen', array_replace($lastSeen, $snap['shape']));

        return [
            'completed_now' => array_values($completedNow),
            'tasks' => [
                'claimable' => count(array_filter($snap['claimable'])),
                'in_progress' => count(array_filter($snap['in_progress'])),
            ],
        ];
    }

    private function activityCount(int $userId, string $date, string $column): int
    {
        return (int) (UserDailyActivity::query()
            ->where('user_id', $userId)
            ->whereDate('activity_date', $date)
            ->value($column) ?? 0);
    }

    private function activity(User $user, Carbon $now, bool $create = false): ?UserDailyActivity
    {
        $date = $now->toDateString();

        $activity = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->whereDate('activity_date', $date)
            ->first();

        if ($activity === null && $create) {
            $activity = UserDailyActivity::create([
                'user_id' => $user->id,
                'activity_date' => $date,
            ]);
        }

        return $activity;
    }
}
