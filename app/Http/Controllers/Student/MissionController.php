<?php

namespace App\Http\Controllers\Student;

use App\Games\Journal\BattleService;
use App\Http\Controllers\Student\Concerns\ReadsStories;
use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Field Journal battle API (guidelines/JOURNAL_BATTLES.md, item 43-46).
 * Demo scope: only stories with missions_enabled. Answers are graded
 * server-side; the state payload never leaks them.
 */
class MissionController extends Controller
{
    use ReadsStories;

    public function __construct(private BattleService $battles)
    {
    }

    public function state(Request $request, Story $story): JsonResponse
    {
        $this->assertEnabled($story);
        $battle = $this->battles->stateFor($request->user(), $story);

        return response()->json(BattleService::payload($battle));
    }

    public function start(Request $request, Story $story): JsonResponse
    {
        $this->assertEnabled($story);
        $data = $request->validate(['action_id' => ['required', 'integer']]);
        $result = $this->battles->startAction($request->user(), $story, (int) $data['action_id']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function complete(Request $request, Story $story): JsonResponse
    {
        $this->assertEnabled($story);
        $data = $request->validate([
            'action_id' => ['required', 'integer'],
            'answers' => ['nullable', 'array'],
            'answers.words' => ['nullable', 'array', 'max:40'],
            'answers.words.*' => ['string', 'max:40'],
            'answers.items' => ['nullable', 'array', 'max:12'],
            'answers.items.*' => ['nullable', 'string', 'max:40'],
        ]);
        $result = $this->battles->completeAction(
            $request->user(),
            $story,
            (int) $data['action_id'],
            (array) ($data['answers'] ?? []),
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function fail(Request $request, Story $story): JsonResponse
    {
        $this->assertEnabled($story);
        $data = $request->validate(['action_id' => ['required', 'integer']]);
        $result = $this->battles->failAction($request->user(), $story, (int) $data['action_id']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** REPLAY: wipe the current battle and stand up a fresh one. */
    public function reset(Request $request, Story $story): JsonResponse
    {
        $this->assertEnabled($story);
        $battle = \App\Models\StoryBattle::query()
            ->where('user_id', $request->user()->id)
            ->where('story_id', $story->id)
            ->first();
        $battle?->delete();
        $fresh = $this->battles->stateFor($request->user(), $story);

        return response()->json(BattleService::payload($fresh));
    }

    private function assertEnabled(Story $story): void
    {
        abort_unless((bool) $story->missions_enabled, 404);
        $this->assertReadable($story);
    }
}
