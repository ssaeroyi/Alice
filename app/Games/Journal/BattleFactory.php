<?php

namespace App\Games\Journal;

use App\Games\Journal\Generators\AffixGenerator;
use App\Games\Journal\Generators\AlphabetizeGenerator;
use App\Games\Journal\Generators\ArrangeGenerator;
use App\Games\Journal\Generators\DoublesGenerator;
use App\Games\Journal\Generators\FindGenerator;
use App\Games\Journal\Generators\LadderGenerator;
use App\Games\Journal\Generators\RiddleGenerator;
use App\Models\Story;
use App\Models\StoryBattle;
use App\Models\StoryBattleAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds a battle's action set for a (user, story). Order of slots:
 *   1. authored pool (teacher moves, live-instantiated);
 *   2. one find move if none is in yet (the richest feasible rule);
 *   3. weighted pick without replacement among the other feasible families
 *      (affix weight 2, story doubles weight 3, static fillers weight 1);
 *   4. find pad for whatever slots are still empty.
 * guidelines/JOURNAL_BATTLES.md.
 */
class BattleFactory
{
    public function create(User $user, Story $story): StoryBattle
    {
        return DB::transaction(function () use ($user, $story) {
            $profile = new StoryProfile($story->content ?? '');
            $rows = $this->actionRows($profile, $story);

            $battle = StoryBattle::create([
                'user_id' => $user->id,
                'story_id' => $story->id,
                'hearts' => (int) config('missions.hearts', 5),
                'boss_hp' => (int) config('missions.boss_hp', 5),
                'status' => 'active',
            ]);

            foreach ($rows as $i => $row) {
                StoryBattleAction::create([
                    'story_battle_id' => $battle->id,
                    'action_key' => $row['key'],
                    'kind' => $row['kind'],
                    'flavor' => $row['flavor'],
                    'prompt' => $row['prompt'],
                    'payload' => $row['payload'],
                    'public_payload' => $row['public_payload'],
                    'status' => 'pending',
                    'timer_seconds' => (int) config("missions.timers.{$row['kind']}", 60),
                    'position' => $i,
                ]);
            }

            return $battle->load('actions');
        });
    }

    /** @return array<int, array> exactly actions_per_battle rows */
    private function actionRows(StoryProfile $profile, Story $story): array
    {
        $enabled = config('missions.generators', ['find']);
        $target = max(1, (int) config('missions.actions_per_battle', 3));
        $find = new FindGenerator();
        $rows = [];
        $used = [];

        // 1) Authored pool first: shuffle the story's enabled moves and
        // instantiate each against the LIVE text and vocab (a story edit
        // can only shrink `need`, never strand it; a config with no live
        // candidates is skipped and the slot falls back to auto-generation
        // below).
        $authored = $story->missionActions()->where('enabled', true)->get()->shuffle();
        foreach ($authored as $def) {
            if (count($rows) >= $target) {
                break;
            }

            $row = AuthoredActions::instantiate($def->config ?? [], $profile, $story);

            if ($row === null || isset($used[$row['key']])) {
                continue;
            }

            $used[$row['key']] = true;
            $rows[] = $row;
        }

        // Find-family fill: adds the richest feasible rules until the cap.
        $findPad = function (int $cap) use (&$rows, &$used, $find, $profile, $story, $target): void {
            foreach ($find->bestRules($profile, $target * 2) as [, $rule]) {
                if (count($rows) >= $cap) {
                    break;
                }

                $row = $find->generateWithRule($profile, $story, $rule);

                if (isset($used[$row['key']])) {
                    continue;
                }

                $used[$row['key']] = true;
                $rows[] = $row;
            }
        };

        // 2) Slot 1 is a find move whenever the story has one, so every
        // battle opens on a classic find even when the pool is busy.
        $hasFind = collect($rows)->contains(fn (array $row) => $row['kind'] === 'find');
        if (! $hasFind && in_array('find', $enabled, true)) {
            $findPad(count($rows) + 1);
        }

        // 3) Other families: weighted pick WITHOUT replacement so a generator
        // can never appear twice in one battle.
        $pool = [];
        if (count($rows) < $target && in_array('affix', $enabled, true)) {
            $pool[] = new AffixGenerator();
        }
        if (count($rows) < $target && in_array('alphabetize', $enabled, true)) {
            $pool[] = new AlphabetizeGenerator();
        }
        if (count($rows) < $target && in_array('arrange', $enabled, true)) {
            $pool[] = new ArrangeGenerator();
        }
        if (count($rows) < $target && in_array('doubles', $enabled, true)) {
            $pool[] = new DoublesGenerator('story');
            $pool[] = new DoublesGenerator('classic');
        }
        if (count($rows) < $target && in_array('ladder', $enabled, true)) {
            $pool[] = new LadderGenerator();
        }
        if (count($rows) < $target && in_array('riddle', $enabled, true)) {
            $pool[] = new RiddleGenerator();
        }

        $weighted = [];
        foreach ($pool as $gen) {
            if ($gen->feasibility($profile) <= 0) {
                continue;
            }
            $weight = match (true) {
                $gen instanceof AffixGenerator => 2,
                str_starts_with($gen->key(), 'doubles-story') => 3,
                default => 1,
            };
            for ($i = 0; $i < $weight; $i++) {
                $weighted[] = $gen;
            }
        }

        $remaining = $weighted;
        while (count($rows) < $target && $remaining) {
            $idx = random_int(0, count($remaining) - 1);
            $gen = $remaining[$idx];
            $remaining = array_values(array_filter($remaining, fn ($g) => $g !== $gen));
            $row = $gen->generate($profile, $story);

            if (isset($used[$row['key']])) {
                continue;
            }

            $used[$row['key']] = true;
            $rows[] = $row;
        }

        // 4) Find pad for any slots still empty.
        if (count($rows) < $target && in_array('find', $enabled, true)) {
            $findPad($target);
        }

        return array_slice($rows, 0, $target);
    }
}
