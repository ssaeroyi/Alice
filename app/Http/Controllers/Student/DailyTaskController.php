<?php

namespace App\Http\Controllers\Student;

use App\Enums\UserDailyTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\UserDailyTask;
use App\Services\DailyTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DailyTaskController extends Controller
{
    public function __construct(private readonly DailyTaskService $service) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $now = Carbon::now();

        $assignments = $this->service->ensureToday($user, $now);

        $tasks = $assignments->map(function (UserDailyTask $assignment) use ($now) {
            $progress = $this->service->progress($assignment, $now);
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

        $rerollsAllowed = DailyTaskService::REROLLS_PER_DAY;
        $rerollsUsed = $this->service->rerollsUsed($user, $now);

        return view('student.tasks', [
            'tasks' => $tasks,
            'rerollsAllowed' => $rerollsAllowed,
            'rerollsUsed' => $rerollsUsed,
            'rerollsRemaining' => max(0, $rerollsAllowed - $rerollsUsed),
        ]);
    }

    public function claim(Request $request): RedirectResponse|JsonResponse
    {
        $assignment = $this->ownToday($request, (int) $request->input('task'));

        if ($assignment === null) {
            return $this->claimFail($request, 'That task is no longer available.');
        }

        $result = $this->service->claim($request->user(), $assignment, Carbon::now());

        if (! $result['ok']) {
            return $this->claimFail($request, $result['reason'] === 'claimed'
                ? 'You already claimed this task.'
                : 'You have not finished this task yet.');
        }

        if ($request->expectsJson()) {
            $fresh = $request->user()->refresh();

            return response()->json([
                'ok' => true,
                'keys' => (int) $result['keys'],
                'points' => (int) $result['points'],
                'total_keys' => (int) $fresh->total_keys,
                'total_points' => (int) $fresh->points,
            ]);
        }

        return $this->tasksRedirect($request)->with('task_claim', [
            'keys' => (int) $result['keys'],
            'points' => (int) $result['points'],
        ]);
    }

    /**
     * Claim every currently-finished task in one tap (hub CLAIM ALL button).
     * Each payout still runs through the idempotent per-task claim, so a
     * racing mix of single and bulk claims can never double-pay.
     */
    public function claimAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();

        $claimable = $this->service->ensureToday($user, $now)
            ->filter(fn (UserDailyTask $assignment): bool => $assignment->status !== UserDailyTaskStatus::Claimed
                && $this->service->progress($assignment, $now) >= (int) $assignment->task->target_value)
            ->values();

        if ($claimable->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No tasks are ready to claim.',
            ], 422);
        }

        $keys = 0;
        $points = 0;
        $ids = [];

        foreach ($claimable as $assignment) {
            $result = $this->service->claim($user, $assignment, $now);

            if ($result['ok']) {
                $keys += (int) $result['keys'];
                $points += (int) $result['points'];
                $ids[] = (int) $assignment->id;
            }
        }

        $fresh = $user->refresh();

        return response()->json([
            'ok' => true,
            'claimed' => count($ids),
            'task_ids' => $ids,
            'keys' => $keys,
            'points' => $points,
            'total_keys' => (int) $fresh->total_keys,
            'total_points' => (int) $fresh->points,
        ]);
    }

    private function claimFail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return $this->tasksRedirect($request)->with('toast', ['type' => 'error', 'message' => $message]);
    }

    public function reroll(Request $request): RedirectResponse
    {
        $assignment = $this->ownToday($request, (int) $request->input('task'));

        if ($assignment === null) {
            return $this->tasksRedirect($request)->with('toast', ['type' => 'error', 'message' => 'That task is no longer available.']);
        }

        $result = $this->service->reroll($request->user(), $assignment, Carbon::now());

        $messages = [
            'claimed' => 'You cannot reroll a claimed task.',
            'rerolls' => 'You have used all your rerolls for today.',
            'none' => 'There is no other task to swap in.',
            'keys' => 'Rerolls cost '.((int) config('gamification.sinks.task_reroll')).' keys. Earn more by finishing quizzes.',
            'ok' => 'Task swapped! Good luck.',
        ];

        $type = $result['ok'] ? 'message' : 'error';

        return $this->tasksRedirect($request)->with('toast', [$type => $messages[$result['reason'] ?? 'ok'] ?? $messages['ok']]);
    }

    /** Land back on the Hub's Tasks tab when the request came from the Hub. */
    private function tasksRedirect(Request $request): RedirectResponse
    {
        if ($request->input('origin') === 'hub') {
            return redirect()->to(route('hub').'#tasks');
        }

        return back();
    }

    private function ownToday(Request $request, int $id): ?UserDailyTask
    {
        if ($id <= 0) {
            return null;
        }

        return UserDailyTask::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereDate('task_date', Carbon::now()->toDateString())
            ->with('task')
            ->first();
    }
}
