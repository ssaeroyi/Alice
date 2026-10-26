<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Study (flashcard review) tuning
    |--------------------------------------------------------------------------
    |
    | Anki-style 4-button grading: Again / Hard / Good / Easy.
    | mastery_level drives both the display tier and the next-review interval.
    | 0 = Not Studied, 1-2 = Learning, 3-4 = Strong.
    |
    */

    // Grade order: again=0, hard=1, good=2, easy=3.
    // intervals[current_level][grade_index] = days until next review.
    'intervals' => [
        0 => [0, 1, 3, 7],
        1 => [0, 2, 3, 7],
        2 => [0, 3, 7, 14],
        3 => [0, 7, 14, 30],
        4 => [0, 14, 30, 60],
    ],

    // mastery_result[current_level][grade_index] = new mastery level.
    'mastery_result' => [
        0 => [0, 1, 2, 3],
        1 => [0, 1, 2, 3],
        2 => [0, 2, 3, 4],
        3 => [0, 3, 4, 4],
        4 => [0, 4, 4, 4],
    ],

    // Levels at or above this are "Strong" (green). Below are "Learning" (blue).
    'learning_level' => 3,

    /*
    |--------------------------------------------------------------------------
    | Daily goal
    |--------------------------------------------------------------------------
    */

    'default_goal' => 20,
    'min_goal' => 5,
    'max_goal' => 100,
    'goal_step' => 5,

    /*
    |--------------------------------------------------------------------------
    | Encouragement lines (dashboard)
    |--------------------------------------------------------------------------
    */

    'encouragements' => [
        'You can do it!',
        'A little every day goes a long way.',
        'Your words are waiting for you.',
        'Small steps, big vocabulary.',
        'Keep that streak feeling going!',
        'Every card makes you a stronger reader.',
    ],
];
