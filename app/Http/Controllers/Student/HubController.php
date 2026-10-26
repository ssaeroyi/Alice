<?php

namespace App\Http\Controllers\Student;

use App\Enums\UserDailyTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use App\Models\UserDailyTask;
use App\Services\BadgeService;
use App\Services\DailyTaskService;
use App\Services\ReadingProgressService;
use App\Services\RewardService;
use App\Support\HubStats;
use App\Support\StudentClasswork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HubController extends Controller
{
    public function __construct(private readonly DailyTaskService $taskService) {}

    /**
     * The student command center: TODAY / TASKS / STATS / BADGES tabs, every
     * number fed by real data via HubStats + the daily task service.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = Carbon::now();

        // First hub visit after finishing placement (new account): surface a
        // one-time greeting game-banner. Pulled here so it survives the
        // multi-step placement flow and only ever fires once.
        if ($request->session()->pull('pending_welcome')) {
            $request->session()->now('welcome', [
                'eyebrow' => 'Welcome to ALICE',
                'title' => 'Your reading adventure starts now!',
                'sub' => '',
            ]);
        }

        $continue = HubStats::continueReading($user);
        $snapshot = HubStats::snapshot($user, $now);
        $rank = HubStats::rank($user);
        $mode = $this->resolveMode($request->string('days')->toString());
        $dayMode = $mode === 'all' ? 'thisweek' : $mode;
        $series = HubStats::activitySeries($user, $now, $dayMode);
        $reading = HubStats::readingTotals($user);
        $quiz = HubStats::quizAccuracy($user);
        $gameBests = HubStats::gameBests($user);
        $lifetime = HubStats::lifetime($user);
        $currentMonth = HubStats::calendar($user, $now);
        $monthlySeries = HubStats::monthlySeries($user, $now);
        $avgQuizScore = HubStats::avgQuizScore($user);

        // Teacher assignments visible to this student, with a one-time
        // "N new assignments" notice driven by last_viewed_assignment_at.
        $newAssignments = $user->newAssignmentsCount();

        // "Class & Assignments" panel data: visible assignments plus each class
        // the student has joined (with classmates + active class assignments).
        $classwork = StudentClasswork::data($user);
        $assignments = $classwork['assignments'];
        $finished = $classwork['finished'];
        $joinedClasses = $classwork['joinedClasses'];

        // Completion flags for every row the classwork partial renders, so the
        // "Done / In progress" pill is a single lookup instead of a
        // per-assignment read/quiz/mark exists() chain.
        $assignmentCompletion = Assignment::completedStatusForStudent(
            $user,
            collect()
                ->merge($assignments)
                ->merge($finished)
                ->merge($joinedClasses->flatMap(fn ($group) => collect($group->active_assignments)->merge($group->finished_assignments))),
        );

        if ($newAssignments > 0 && $assignments->isNotEmpty()) {
            $request->session()->now('toast', [
                'type' => 'info',
                'message' => $newAssignments.' new assignment'.($newAssignments === 1 ? '' : 's').' from your teacher.',
            ]);
        }

        $user->markAssignmentsViewed();

        // Live reading level for the stats panel. Reads the persisted
        // reading_progress row (recomputed on quiz/reader save points + the
        // nightly sync), never recomputed per hub view under a class burst.
        $liveReading = $user->readingProgress;
        $readingLevelView = [
            'grade' => ReadingProgressService::serveGrade($user),
            'pct' => (int) ($liveReading?->live_score ?? $user->comprehension_bar ?? 0),
            'live' => $liveReading !== null && $liveReading->live_score !== null,
        ];

        // All-time reading minutes from the heartbeat truth (the same
        // instrument that drives the goals + progress bars). Intentionally NOT
        // mixed with story_progress.time_spent_seconds (a different measure)
        // so the total can never double-count the same reading session.
        $totalReadingSeconds = (int) UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->sum('reading_seconds_today');
        $initialStats = [
            'mode' => $mode,
            'series' => $series->values(),
            'seriesMax' => $this->seriesMax($series),
            'calendar' => $currentMonth,
            'monthlySeries' => $monthlySeries,
        ];

        // The hub section reads the layout's $gamer (points/streak/keys/tier),
        // but a variable defined in the layout @php block is NOT visible inside
        // the child @section scope, so those tiles silently fell back to zeros.
        // Recompute the same values here and pass them down explicitly.
        $taskState = $this->taskService->state($user, $now);
        $gamer = [
            'rank' => $rank,
            'tier_icon' => config('gamification.points.titles.'.$rank['tier'].'.icon', 'book-cover'),
            'keys' => (int) ($user->total_keys ?? 0),
            'streak' => (int) ($user->current_streak ?? 0),
            'longest_streak' => (int) ($user->longest_streak ?? 0),
            'freezes' => (int) ($user->streak_freezes ?? 0),
            'freeze_cost' => (int) config('gamification.sinks.streak_freeze'),
            'freeze_cap' => (int) config('gamification.freeze.hold_cap'),
            'claimable_tasks' => $taskState['claimable'] ?? 0,
            'in_progress_tasks' => $taskState['in_progress'] ?? 0,
        ];

        return view('student.hub', [
            'gamer' => $gamer,
            // TODAY
            'continue' => $continue,
            'snapshot' => $snapshot,
            'rank' => $rank,
            // ASSIGNMENTS
            'assignments' => $assignments,
            'finished' => $finished,
            'newAssignments' => $newAssignments,
            'assignmentCompletion' => $assignmentCompletion,
            // CLASSES
            'joinedClasses' => $joinedClasses,
            // TASKS
            'tasks' => $this->tasks($user, $now),
            'rerollsRemaining' => $this->rerollsRemaining($user, $now),
            // STATS
            'mode' => $mode,
            'series' => $series,
            'initialStats' => $initialStats,
            'seriesMax' => $this->seriesMax($series),
            'reading' => $reading,
            'quiz' => $quiz,
            'avgQuizScore' => $avgQuizScore,
            'readingLevelView' => $readingLevelView,
            'totalReadingSeconds' => $totalReadingSeconds,
            'gameBests' => $gameBests,
            'lifetime' => $lifetime,
            'currentMonth' => $currentMonth,
            'monthlySeries' => $monthlySeries,
            // BADGES
            'badgeRows' => $this->badgeRows($user),
            'progressValues' => BadgeService::progressValues($user, $now),
            'unlockStats' => BadgeService::unlockStats(),
            // INBOX
            'unreadNotifications' => $user->notifications()->unread()->count(),
            'inboxNotifications' => $user->notifications()->get(),
        ]);
    }

    /**
     * JSON feed powering the STATS tab's chart-mode toggle and the interactive
     * stamp calendar (hub-stats.js re-fetches + re-renders client-side, so the
     * student never loses their place on the tab). The mode is allow-listed
     * (today|thisweek|lastweek|all) and the month is clamped to the last month
     * with activity. `all` returns the monthly series (the frontend renders the
     * monthly line for it); day modes return the per-day series.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        $mode = $this->resolveMode($request->string('days')->toString());

        $month = $this->monthFromRequest($request->string('month')->toString(), $now);
        $monthlySeries = HubStats::monthlySeries($user, $now);

        if ($mode === 'all') {
            return response()->json([
                'mode' => $mode,
                'monthlySeries' => $monthlySeries,
                'calendar' => HubStats::calendar($user, $month),
            ]);
        }

        $series = HubStats::activitySeries($user, $now, $mode);

        return response()->json([
            'mode' => $mode,
            'series' => $series,
            'seriesMax' => $this->seriesMax($series),
            'calendar' => HubStats::calendar($user, $month),
        ]);
    }

    /**
     * Daily reading goal (D, user-approved). Moved here from Settings —
     * Settings no longer owns the picker, so this is the sole editor for the
     * reading goal (the hub TODAY tab + Reader goal key-claims read it).
     * The hub editor is a range slider over config('gamification.reading_goal_*')
     * (0..60 step 1); 0 means "no goal set" (stored as null) and its 0 floor is
     * the one plus over placement's config('placement.reading_goal_min') floor.
     */
    public function updateReadingGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal' => ['required', 'integer', 'min:'.config('gamification.reading_goal_min'), 'max:'.config('gamification.reading_goal_max')],
        ]);

        $request->user()->update(['daily_reading_goal' => $validated['goal'] === 0 ? null : $validated['goal']]);

        return response()->json([
            'goal' => $request->user()->fresh()->daily_reading_goal,
        ]);
    }

    /**
     * Claim a met daily goal's key reward (the CLAIM button on the hub's
     * TODAY tab). Met is re-checked server-side with the exact formulas the
     * goal cards render, so the button and this endpoint can never disagree;
     * creditGoalReward is idempotent per day, so a racing double-tap comes
     * back as `already` instead of double-paying.
     */
    public function claimGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal' => ['required', Rule::in(['reading', 'study'])],
        ]);

        $user = $request->user();
        $now = Carbon::now();
        $snapshot = HubStats::snapshot($user, $now);

        if ($validated['goal'] === 'reading') {
            $goal = (int) $snapshot['daily_reading_goal'];
            $progress = round($snapshot['reading_seconds_today'] / 60, 1);
            $met = $goal > 0 && $progress >= $goal;
        } else {
            $goal = (int) $snapshot['daily_study_goal'];
            $met = $goal > 0 && (int) $snapshot['study_reviewed_today'] >= $goal;
        }

        if (! $met) {
            return response()->json([
                'ok' => false,
                'message' => 'Goal not met yet.',
            ], 422);
        }

        $keys = RewardService::creditGoalReward($user, $validated['goal'], $now);

        return response()->json([
            'ok' => true,
            'already' => $keys === 0,
            'keys' => $keys,
            'total_keys' => (int) $user->fresh()->total_keys,
        ]);
    }

    /**
     * Resolve the STATS activity-chart mode. Allow-list is
     * today | thisweek | lastweek | all; anything else (including the legacy
     * 7 / 30 day tokens) falls back to `thisweek`.
     */
    private function resolveMode(string $raw): string
    {
        return in_array($raw, ['today', 'thisweek', 'lastweek', 'all'], true) ? $raw : 'thisweek';
    }

    /**
     * Resolve a YYYY-MM month for the calendar. Past months browse freely but
     * are bounded to a fixed 24-month lookback, and navigation never moves
     * forward past the current month.
     */
    private function monthFromRequest(string $raw, Carbon $now): Carbon
    {
        $lowest = $now->copy()->startOfMonth()->subMonths(23);

        $parsed = strlen($raw) === 7 && preg_match('/^\d{4}-\d{2}$/', $raw)
            ? Carbon::createFromFormat('!Y-m', $raw)
            : null;

        if ($parsed === null || $parsed->lt($lowest) || $parsed->gt($now)) {
            return $now->copy()->startOfMonth();
        }

        return $parsed->copy()->startOfMonth();
    }

    /**
     * Today's three daily tasks in the same shape the /tasks page renders.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function tasks(User $user, Carbon $now): Collection
    {
        return $this->taskService->ensureToday($user, $now)->map(function (UserDailyTask $assignment) use ($now) {
            $progress = $this->taskService->progress($assignment, $now);
            $target = (int) $assignment->task->target_value;
            $status = $assignment->status;

            return [
                'id' => $assignment->id,
                'title' => $assignment->task->title,
                'description' => $assignment->task->description,
                'type' => $assignment->task->type,
                'target' => $target,
                'progress' => $progress,
                'reward_keys' => (int) $assignment->task->reward_keys,
                'reward_xp' => (int) $assignment->task->reward_xp,
                'completed' => $progress >= $target,
                'in_progress' => $status !== UserDailyTaskStatus::Claimed && $progress > 0 && $progress < $target,
                'claimed' => $status === UserDailyTaskStatus::Claimed,
                'can_claim' => $progress >= $target && $status !== UserDailyTaskStatus::Claimed,
                'can_reroll' => $status !== UserDailyTaskStatus::Claimed,
            ];
        });
    }

    private function rerollsRemaining(User $user, Carbon $now): int
    {
        $used = $this->taskService->rerollsUsed($user, $now);

        return max(0, DailyTaskService::REROLLS_PER_DAY - $used);
    }

    /**
     * Badge shelf rows matching the /badges page (S8) so the BADGES tab can
     * reuse the same partial.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function badgeRows(User $user): Collection
    {
        $defs = config('badges.badges', []);

        $badges = Badge::query()
            ->whereIn('key', array_column($defs, 'key'))
            ->get()
            ->keyBy('key');

        $ownedByBadge = UserBadge::query()
            ->where('user_id', $user->id)
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

        return $rows;
    }

    /**
     * Highest single day "activity" across the series, so chart bars are
     * sized against a real maximum (never zero-divide). Reading is converted
     * to minutes so it stays comparable to quiz/game/drill counts — this is
     * the same combined metric the chart bars are drawn against.
     */
    private function seriesMax(Collection $series): int
    {
        $max = $series->max(fn ($d) => (int) round(max($d['reading_seconds'], 0) / 60) + $d['quizzes'] + $d['games'] + $d['drills']);

        return $max > 0 ? $max : 1;
    }
}
