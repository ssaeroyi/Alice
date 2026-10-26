<?php

namespace Database\Seeders;

use App\Enums\DailyTaskType;
use App\Models\DailyTask;
use Illuminate\Database\Seeder;

class DailyTaskSeeder extends Seeder
{
    /**
     * Curated daily-task pool (S7). One task per DailyTaskType; 3 are assigned
     * to a student each day. Rewards stay inside the config gamification.task
     * [min_keys,max_keys] / [min_xp,max_xp] bounds (5-10 keys + 10-20 points).
     */
    public function run(): void
    {
        $minKeys = (int) config('gamification.task.min_keys');
        $maxKeys = (int) config('gamification.task.max_keys');
        $minXp = (int) config('gamification.task.min_xp');
        $maxXp = (int) config('gamification.task.max_xp');

        $pool = [
            DailyTaskType::ReadStory->value => [
                'title' => 'Read a story',
                'description' => 'Open a story and read to the end.',
                'target' => 1,
                'keys' => 6,
                'xp' => 12,
            ],
            DailyTaskType::ScoreQuiz->value => [
                'title' => 'Score on a quiz',
                'description' => 'Get 70% or better on a story quiz.',
                'target' => 1,
                'keys' => 5,
                'xp' => 10,
            ],
            DailyTaskType::LearnWords->value => [
                'title' => 'Learn new words',
                'description' => 'Save 5 new words while you read.',
                'target' => 5,
                'keys' => 7,
                'xp' => 15,
            ],
            DailyTaskType::GymDrill->value => [
                'title' => 'Review a flashcard',
                'description' => 'Grade one flashcard to the end in Study.',
                'target' => 1,
                'keys' => 5,
                'xp' => 10,
            ],
            DailyTaskType::PlayGame->value => [
                'title' => 'Play a mini-game',
                'description' => 'Finish one mini-game round.',
                'target' => 1,
                'keys' => 5,
                'xp' => 10,
            ],
        ];

        foreach ($pool as $type => $def) {
            DailyTask::updateOrCreate(
                ['key' => $type],
                [
                    'type' => $type,
                    'title' => $def['title'],
                    'description' => $def['description'],
                    'target_value' => $def['target'],
                    'reward_keys' => min($maxKeys, max($minKeys, $def['keys'])),
                    'reward_xp' => min($maxXp, max($minXp, $def['xp'])),
                    'active' => true,
                ],
            );
        }
    }
}
