<?php

namespace App\Enums;

enum DailyTaskType: string
{
    case ReadStory = 'read_story';
    case ScoreQuiz = 'score_quiz';
    case LearnWords = 'learn_words';
    case GymDrill = 'gym_drill';
    case PlayGame = 'play_game';
    case BattleAction = 'battle_action';
    case WinBattle = 'win_battle';

    public function label(): string
    {
        return match ($this) {
            self::ReadStory => 'Read a story',
            self::ScoreQuiz => 'Score on a quiz',
            self::LearnWords => 'Learn new words',
            self::GymDrill => 'Complete a drill',
            self::PlayGame => 'Play a mini-game',
            self::BattleAction => 'Do a journal action',
            self::WinBattle => 'Defeat a boss',
        };
    }

    /** Tintable vendored UI glyph (S2) for the task card. */
    public function icon(): string
    {
        return match ($this) {
            self::ReadStory => 'open-book',
            self::ScoreQuiz => 'trophy',
            self::LearnWords => 'spell-book',
            self::GymDrill => 'cards',
            self::PlayGame => 'maze',
            self::BattleAction => 'scroll-quill',
            self::WinBattle => 'flame',
        };
    }

    /** Where the task's "Go" link drops the student to make progress on it. */
    public function routeName(): string
    {
        return match ($this) {
            self::ReadStory, self::ScoreQuiz, self::BattleAction, self::WinBattle => 'library',
            self::LearnWords => 'study',
            self::GymDrill => 'study.session',
            self::PlayGame => 'mini-game',
        };
    }
}
