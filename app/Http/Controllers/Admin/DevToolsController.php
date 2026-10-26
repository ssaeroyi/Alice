<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\DevTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Dev Tools — QA/cheat console for the student economy and games.
 * Reachable by a real admin directly, or through the scratch-only dev-tools
 * middleware during a "view as" preview. Every action resolves the disposable
 * scratch student (DevTools::target), never a real learner, and is audited.
 */
class DevToolsController extends Controller
{
    /**
     * Action whitelist -> per-action validation rules. Rejects unknown actions
     * (404) before any dispatch.
     */
    private const ACTIONS = [
        'add_keys' => ['amount' => ['required', 'integer', 'min:1', 'max:1000']],
        'add_points' => ['amount' => ['required', 'integer', 'min:1', 'max:10000']],
        'set_points' => ['points' => ['required', 'integer', 'min:0', 'max:20000']],
        'spend_keys' => ['amount' => ['required', 'integer', 'min:1', 'max:1000']],
        'reading_minutes' => ['minutes' => ['required', 'integer', 'min:1', 'max:120']],
        'stamp_today' => [],
        'set_streak' => ['streak' => ['required', 'integer', 'min:0', 'max:9999']],
        'set_last_stamp_today' => [],
        'add_freeze' => [],
        'set_freezes' => ['freezes' => ['required', 'integer', 'min:0', 'max:5']],
        'buy_freeze' => [],
        'set_farmable' => ['count' => ['required', 'integer', 'min:0', 'max:20']],
        'scenario_quiz' => ['kind' => ['required', 'in:perfect,poor,improvement']],
        'scenario_game' => [
            'game' => ['required', 'in:wordsearch,match,sentence'],
            'score' => ['required', 'in:excellent,good,poor'],
        ],
        'scenario_drill' => [],
        'scenario_task' => [],
        'reset' => [],
    ];

    public function state(): JsonResponse
    {
        $target = DevTools::target();

        if ($target === null) {
            abort(403);
        }

        return response()->json(['ok' => true] + DevTools::state($target));
    }

    public function run(Request $request): JsonResponse|RedirectResponse
    {
        $target = DevTools::target();

        if ($target === null) {
            abort(403);
        }

        $action = (string) $request->input('action', '');
        $rules = self::ACTIONS[$action] ?? null;

        if ($rules === null) {
            abort(404);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $error = $validator->errors()->first();

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $error], 422);
            }

            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast', ['type' => 'error', 'message' => $error]);
        }

        $result = $this->dispatch($action, $target, $validator->validated());

        Audit::log('dev_tools.'.$action, $target, $result['ok'] ? [] : ['failed' => $result['message']]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => $result['ok'], 'message' => $result['message'], 'state' => DevTools::state($target)]);
        }

        return back()->with('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    /** @return array{ok:bool,message:string} */
    private function dispatch(string $action, User $target, array $data): array
    {
        return match ($action) {
            'add_keys' => DevTools::addKeys($target, (int) $data['amount']),
            'add_points' => DevTools::addPoints($target, (int) $data['amount']),
            'set_points' => DevTools::setPoints($target, (int) $data['points']),
            'spend_keys' => DevTools::spendKeys($target, (int) $data['amount']),
            'reading_minutes' => DevTools::addReadingSeconds($target, (int) $data['minutes'] * 60),
            'stamp_today' => DevTools::stampToday($target),
            'set_streak' => DevTools::setStreak($target, (int) $data['streak']),
            'set_last_stamp_today' => DevTools::setLastStampToday($target),
            'add_freeze' => DevTools::addFreeze($target),
            'set_freezes' => DevTools::setFreezes($target, (int) $data['freezes']),
            'buy_freeze' => DevTools::buyFreeze($target),
            'set_farmable' => DevTools::setFarmableToday($target, (int) $data['count']),
            'scenario_quiz' => DevTools::quizScenario($target, (string) $data['kind']),
            'scenario_game' => DevTools::gameScenario($target, (string) $data['game'], (string) $data['score']),
            'scenario_drill' => DevTools::drillScenario($target),
            'scenario_task' => DevTools::taskClaimScenario($target),
            'reset' => DevTools::reset($target),
        };
    }
}
