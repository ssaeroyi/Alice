<?php

namespace App\Games\Journal;

use App\Games\Journal\Generators\FindGenerator;
use App\Models\Story;
use App\Models\StoryBattle;
use App\Models\StoryBattleAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Read/mutate a battle: state payload (answers never leak), start an action
 * with a server deadline, grade completions, lose hearts on timeout/quit,
 * regenerate the full action set at 0 hearts (never a soft-lock).
 */
class BattleService
{
    public function __construct(private BattleFactory $factory)
    {
    }

    public function stateFor(User $user, Story $story): StoryBattle
    {
        $battle = StoryBattle::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->with('actions')
            ->first();

        if (!$battle) {
            return $this->factory->create($user, $story);
        }

        // Returning to the story: an action that ran out while the student
        // was away settles exactly like a client timeout (heart down, and a
        // full regeneration at zero) so the journal can never wedge shut.
        if (!$battle->isWon()) {
            $stale = $battle->actions->first(
                fn (StoryBattleAction $a) => $a->status === 'active'
                    && $a->deadline_at !== null
                    && now()->greaterThan($a->deadline_at)
            );
            if ($stale) {
                $this->loseHeart($battle, $stale);

                return StoryBattle::where('user_id', $user->id)
                    ->where('story_id', $story->id)
                    ->with('actions')
                    ->first() ?? $this->factory->create($user, $story);
            }
        }

        return $battle;
    }

    /** UI-safe JSON: no answers, no deadlines except the live active row. */
    public static function payload(StoryBattle $battle): array
    {
        $actions = $battle->actions->map(fn (StoryBattleAction $a) => array_merge([
            'id' => $a->id,
            'kind' => $a->kind,
            'flavor' => $a->flavor,
            'prompt' => $a->prompt,
            'status' => $a->status,
            'timer_seconds' => $a->timer_seconds,
            // Only the live row needs its absolute deadline (resume countdown,
            // watchdog); pending/done rows never leak one.
            'deadline_at' => $a->status === 'active'
                ? optional($a->deadline_at)->toIso8601String()
                : null,
        ], $a->public_payload ?? []))->values();

        return [
            'hearts' => $battle->hearts,
            'boss_hp' => $battle->boss_hp,
            'max_hearts' => (int) config('missions.hearts', 3),
            'max_hp' => (int) config('missions.boss_hp', 3),
            'status' => $battle->status,
            'done' => $battle->actions->where('status', 'done')->count(),
            'total' => $battle->actions->count(),
            'actions' => $actions,
        ];
    }

    /** @return array{ok:bool,error?:string,action?:array} */
    public function startAction(User $user, Story $story, int $actionId): array
    {
        return DB::transaction(function () use ($user, $story, $actionId) {
            $battle = $this->lockedBattle($user, $story);
            if (!$battle || $battle->isWon()) {
                return ['ok' => false, 'error' => 'No battle.'];
            }

            $action = $battle->actions->firstWhere('id', $actionId);
            if (!$action) {
                return ['ok' => false, 'error' => 'Unknown action.'];
            }

            // Idempotent restart of the same active row (double tap / reconnect).
            if ($action->status === 'active') {
                return ['ok' => true, 'action' => $this->startPayload($action)];
            }

            if ($action->status !== 'pending') {
                return ['ok' => false, 'error' => 'Action already completed.'];
            }

            // Self-heal: a stuck active action (quit raced the timer, a dropped
            // request) must never wedge the journal. Reset it and continue.
            $busy = $battle->actions->firstWhere('status', 'active');
            if ($busy) {
                $busy->status = 'pending';
                $busy->deadline_at = null;
                $busy->save();
            }

            $action->status = 'active';
            $action->deadline_at = now()->addSeconds($action->timer_seconds);
            $action->save();

            return ['ok' => true, 'action' => $this->startPayload($action)];
        });
    }

    /**
     * Grade a submitted answer set. Wrong answers never cost a heart; they
     * just shake. Passing the server deadline degrades to failAction().
     *
     * @return array{ok:bool,event?:string,error?:string,correct?:bool,battle?:array}
     */
    public function completeAction(User $user, Story $story, int $actionId, array $answers): array
    {
        return DB::transaction(function () use ($user, $story, $actionId, $answers) {
            $battle = $this->lockedBattle($user, $story);
            $action = $battle?->actions->firstWhere('id', $actionId);

            if (!$battle || !$action || $action->status !== 'active') {
                return ['ok' => false, 'error' => 'Not running.'];
            }

            if ($action->deadline_at && now()->greaterThan($action->deadline_at->addSeconds(3))) {
                return $this->loseHeart($battle, $action);
            }

            if (!$this->grade($action, $answers)) {
                return ['ok' => true, 'correct' => false, 'error' => 'Not quite. Try again.'];
            }

            $action->status = 'done';
            $action->deadline_at = null;
            $action->save();

            $battle->boss_hp = max(0, $battle->boss_hp - 1);
            $won = $battle->actions->where('status', 'done')->count() === $battle->actions->count();
            $battle->status = $won ? 'won' : 'active';
            $battle->save();

            // Battle badges (Journal Keeper tiers + win tiers + variety/time
            // flavors). Idempotent: award() skips already-owned badges.
            \App\Services\BadgeService::award($user, ['battle'], now());

            return [
                'ok' => true,
                'correct' => true,
                'event' => $won ? 'won' : 'damaged',
                'battle' => self::payload($battle->fresh('actions')),
            ];
        });
    }

    /** Quit or client timeout. Hearts fall; full regeneration at 0. Idempotent. */
    public function failAction(User $user, Story $story, int $actionId): array
    {
        return DB::transaction(function () use ($user, $story, $actionId) {
            $battle = $this->lockedBattle($user, $story);
            $action = $battle?->actions->firstWhere('id', $actionId);
            if (!$battle) {
                return ['ok' => false, 'error' => 'No battle.'];
            }
            if (!$action || $action->status !== 'active') {
                // Already settled (timer raced the quit, double tap): no-op.
                return ['ok' => true, 'event' => 'noop', 'battle' => self::payload($battle)];
            }
            return $this->loseHeart($battle, $action);
        });
    }

    /** @return array{ok:bool,event:string,battle:array} */
    private function loseHeart(StoryBattle $battle, StoryBattleAction $action): array
    {
        $action->status = 'pending';
        $action->deadline_at = null;
        $action->save();

        $battle->hearts = max(0, $battle->hearts - 1);

        if ($battle->hearts <= 0) {
            $story = $battle->story;
            $user = $battle->user;
            $battle->delete();
            $fresh = $this->factory->create($user, $story);
            return [
                'ok' => true,
                'event' => 'regenerated',
                'battle' => self::payload($fresh),
            ];
        }

        $battle->save();
        return [
            'ok' => true,
            'event' => 'heart-lost',
            'battle' => self::payload($battle),
        ];
    }

    private function lockedBattle(User $user, Story $story): ?StoryBattle
    {
        return StoryBattle::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->lockForUpdate()
            ->first();
    }

    /** Server-side answer payload for an active action. */
    private function startPayload(StoryBattleAction $action): array
    {
        return [
            'id' => $action->id,
            'kind' => $action->kind,
            'flavor' => $action->flavor,
            'prompt' => $action->prompt,
            'timer_seconds' => $action->timer_seconds,
            'deadline_at' => optional($action->deadline_at)->toIso8601String(),
            'public_payload' => $action->public_payload,
        ];
    }

    private function grade(StoryBattleAction $action, array $answers): bool
    {
        $payload = $action->payload ?? [];

        if ($action->kind === 'find') {
            $words = array_values(array_filter(array_map(
                fn ($w) => FindGenerator::normalizeWord((string) $w),
                (array) ($answers['words'] ?? [])
            )));
            $words = array_unique($words);
            if (count($words) < (int) ($payload['need'] ?? 5)) {
                return false;
            }
            $rule = $payload['rule'] ?? [];
            foreach ($words as $w) {
                if (!FindGenerator::wordMatches($rule, $w)) {
                    return false;
                }
            }
            return true;
        }

        // fill: typed words; arrange: tiles rebuilt in order. Both submit one
        // word per item and grade by exact match.
        if (in_array($action->kind, ['fill', 'arrange'], true)) {
            $given = array_values((array) ($answers['items'] ?? []));
            $items = $payload['items'] ?? [];
            if (count($given) !== count($items) || count($items) === 0) {
                return false;
            }
            foreach ($items as $i => $item) {
                $a = FindGenerator::normalizeWord((string) ($given[$i] ?? ''));
                $e = FindGenerator::normalizeWord((string) ($item['answer'] ?? ''));
                if ($a === '' || $a !== $e) {
                    return false;
                }
            }
            return true;
        }

        if ($action->kind === 'choice') {
            $given = array_values((array) ($answers['items'] ?? []));
            $items = $payload['items'] ?? [];
            if (count($given) !== count($items) || count($items) === 0) {
                return false;
            }
            foreach ($items as $i => $item) {
                $a = FindGenerator::normalizeWord((string) ($given[$i] ?? ''));
                $e = FindGenerator::normalizeWord((string) ($item['answer'] ?? ''));
                if ($a === '' || $a !== $e) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
