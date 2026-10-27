<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Badges (gamification S8)
    |--------------------------------------------------------------------------
    |
    | The badge catalog is the single authoritative source for the whole badge
    | set (63 badges across eight families: titles / stories / reading / quiz /
    | words / games / stamps & streaks / focus). BadgeSeeder seeds `badges` from here
    | (idempotent by `key`); BadgeService evaluates eligibility against these
    | same keys; the BADGES page and hub BADGES tab render them. Rarity tints
    | come from the `rarity` palette below (Common / Rare / Epic pixel frames).
    |
    | Every entry must stay in lock-step with: one `eligible()`/`progress()`
    | arm in BadgeService (keyed by `key`), one seeded row (BadgeSeeder), and
    | one vendored pictorial ui tile (`icon`). The `category` groups tiles
    | on the shelf; `icon` names a vendored ui tile (game-icons.net
    | CC BY 3.0 / Nieobie CC0 — see ATTRIBUTION.md).
    |--------------------------------------------------------------------------
    */

    // Rarity-tinted pixel-frame palette. Keys match App\Enums\Rarity values.
    // Consumers: student/badges.blade.php frames + the unlock toast.
    'rarity' => [
        'common' => ['text' => 'text-stone-700',   'bg' => 'bg-stone-100',   'border' => 'border-stone-400'],
        'rare' => ['text' => 'text-blue-700',       'bg' => 'bg-blue-100',    'border' => 'border-blue-500'],
        'epic' => ['text' => 'text-violet-700',     'bg' => 'bg-violet-100',  'border' => 'border-violet-500'],
    ],

    // Canonical catalog ordering + grouping. `category` labels the shelf
    // section header; badges render in the order declared below.
    'categories' => [
        'titles' => 'Titles',
        'stories' => 'Stories',
        'reading' => 'Reading',
        'quiz' => 'Quizzes',
        'words' => 'Words',
        'games' => 'Mini-Games',
        'battles' => 'Field Journal',
        'stamps' => 'Stamps & Streaks',
        'focus' => 'Focus',
    ],

    'badges' => [

        // ---- Field Journal battles (win ladders / performance / variety) --
        ['key' => 'battle_first', 'name' => 'First Blood', 'description' => 'Win your first Field Journal battle.', 'category' => 'battles', 'rarity' => 'common', 'icon' => 'flame'],
        ['key' => 'battle_5', 'name' => 'Boss Slayer', 'description' => 'Win 5 Field Journal battles.', 'category' => 'battles', 'rarity' => 'common', 'icon' => 'spell-book'],
        ['key' => 'battle_10', 'name' => 'Boss Nightmare', 'description' => 'Win 10 Field Journal battles.', 'category' => 'battles', 'rarity' => 'rare', 'icon' => 'book-storm'],
        ['key' => 'battle_25', 'name' => 'Boss Legend', 'description' => 'Win 25 Field Journal battles.', 'category' => 'battles', 'rarity' => 'epic', 'icon' => 'crown'],
        ['key' => 'battle_flawless', 'name' => 'Flawless Victory', 'description' => 'Win a battle without losing a single heart.', 'category' => 'battles', 'rarity' => 'rare', 'icon' => 'laurel-crown'],
        ['key' => 'battle_flawless_5', 'name' => 'Untouchable', 'description' => 'Win 5 battles without losing a single heart.', 'category' => 'battles', 'rarity' => 'epic', 'icon' => 'queen-crown'],
        ['key' => 'journal_25', 'name' => 'Journal Keeper', 'description' => 'Complete 25 Field Journal actions.', 'category' => 'battles', 'rarity' => 'common', 'icon' => 'scroll-quill'],
        ['key' => 'journal_100', 'name' => 'Chronicler', 'description' => 'Complete 100 Field Journal actions.', 'category' => 'battles', 'rarity' => 'epic', 'icon' => 'book-pile'],
        ['key' => 'journal_all_kinds', 'name' => 'Well-Rounded', 'description' => 'Complete every kind of Field Journal action.', 'category' => 'battles', 'rarity' => 'rare', 'icon' => 'compass'],
        ['key' => 'battle_days_3', 'name' => 'Regular', 'description' => 'Win Field Journal battles on 3 different days.', 'category' => 'battles', 'rarity' => 'rare', 'icon' => 'calendar'],
        ['key' => 'battle_variety_5', 'name' => 'Boss Collector', 'description' => 'Defeat bosses in 5 different stories.', 'category' => 'battles', 'rarity' => 'epic', 'icon' => 'map'],
        ['key' => 'battle_dawn', 'name' => 'Dawn Raider', 'description' => 'Win a Field Journal battle before 8 AM.', 'category' => 'battles', 'rarity' => 'rare', 'icon' => 'lantern'],
        ['key' => 'battle_owl', 'name' => 'Night Stalker', 'description' => 'Win a Field Journal battle after 9 PM.', 'category' => 'battles', 'rarity' => 'rare', 'icon' => 'lantern'],

        // ---- Titles (rank-ladder milestones, from PointsRank tiers) ------
        ['key' => 'title_bookworm', 'name' => 'Title: Bookworm', 'description' => 'Earn the Bookworm title.', 'category' => 'titles', 'rarity' => 'common', 'icon' => 'magnifying-glass'],
        ['key' => 'title_page_turner', 'name' => 'Title: Page Turner', 'description' => 'Earn the Page Turner title.', 'category' => 'titles', 'rarity' => 'common', 'icon' => 'notebook'],
        ['key' => 'title_story_explorer', 'name' => 'Title: Story Explorer', 'description' => 'Earn the Story Explorer title.', 'category' => 'titles', 'rarity' => 'rare', 'icon' => 'compass'],
        ['key' => 'title_chapter_master', 'name' => 'Title: Chapter Master', 'description' => 'Earn the Chapter Master title.', 'category' => 'titles', 'rarity' => 'rare', 'icon' => 'queen-crown'],
        ['key' => 'title_bibliophile', 'name' => 'Title: Bibliophile', 'description' => 'Earn the Bibliophile title.', 'category' => 'titles', 'rarity' => 'rare', 'icon' => 'book-storm'],
        ['key' => 'title_word_sage', 'name' => 'Title: Word Sage', 'description' => 'Earn the Word Sage title.', 'category' => 'titles', 'rarity' => 'epic', 'icon' => 'scroll-quill'],
        ['key' => 'title_literary_ace', 'name' => 'Title: Literary Ace', 'description' => 'Earn the Literary Ace title.', 'category' => 'titles', 'rarity' => 'epic', 'icon' => 'book-aura'],
        ['key' => 'title_tale_weaver', 'name' => 'Title: Tale Weaver', 'description' => 'Earn the Tale Weaver title.', 'category' => 'titles', 'rarity' => 'epic', 'icon' => 'yarn'],
        ['key' => 'title_benevolent', 'name' => 'Title: Benevolent', 'description' => 'Earn the Benevolent title.', 'category' => 'titles', 'rarity' => 'epic', 'icon' => 'lantern'],

        // ---- Stories ---------------------------------------------------
        ['key' => 'first_story', 'name' => 'First Story', 'description' => 'Finish your very first story: read it, then pass its quiz.', 'category' => 'stories', 'rarity' => 'common', 'icon' => 'open-book'],
        ['key' => 'stories_5', 'name' => 'Bookworm', 'description' => 'Read 5 stories and pass their quizzes.', 'category' => 'stories', 'rarity' => 'common', 'icon' => 'bookshelf'],
        ['key' => 'stories_10', 'name' => 'Page Turner', 'description' => 'Read 10 stories and pass their quizzes.', 'category' => 'stories', 'rarity' => 'rare', 'icon' => 'book-pile'],
        ['key' => 'stories_25', 'name' => 'Story Scholar', 'description' => 'Read 25 stories and pass their quizzes.', 'category' => 'stories', 'rarity' => 'epic', 'icon' => 'book-cover'],
        ['key' => 'stories_50', 'name' => 'Legendary Reader', 'description' => 'Read 50 stories and pass their quizzes.', 'category' => 'stories', 'rarity' => 'epic', 'icon' => 'bookshelf'],
        ['key' => 'genre_explorer', 'name' => 'Genre Explorer', 'description' => 'Read stories from 5 different genres.', 'category' => 'stories', 'rarity' => 'rare', 'icon' => 'map'],
        ['key' => 'favorite_story', 'name' => 'Book Lover', 'description' => 'Mark a story as a favorite.', 'category' => 'stories', 'rarity' => 'common', 'icon' => 'book-cover'],

        // ---- Reading ----------------------------------------------------
        ['key' => 'first_read', 'name' => 'First Chapter', 'description' => 'Start your first reading session.', 'category' => 'reading', 'rarity' => 'common', 'icon' => 'open-book'],
        ['key' => 'reading_60', 'name' => 'Cozy Reader', 'description' => 'Read 60 minutes in total.', 'category' => 'reading', 'rarity' => 'common', 'icon' => 'open-book'],
        ['key' => 'reading_300', 'name' => 'Nightstand Pro', 'description' => 'Read 5 hours in total.', 'category' => 'reading', 'rarity' => 'rare', 'icon' => 'book-pile'],
        ['key' => 'reading_1200', 'name' => 'Bookworm Legend', 'description' => 'Read 20 hours in total.', 'category' => 'reading', 'rarity' => 'rare', 'icon' => 'bookshelf'],
        ['key' => 'reading_3000', 'name' => 'Literary Giant', 'description' => 'Read 50 hours in total.', 'category' => 'reading', 'rarity' => 'epic', 'icon' => 'book-cover'],
        ['key' => 'reading_10000', 'name' => 'Infinite Pages', 'description' => 'Read 167 hours in total.', 'category' => 'reading', 'rarity' => 'epic', 'icon' => 'spell-book'],

        // ---- Quiz -------------------------------------------------------
        ['key' => 'quiz_first', 'name' => 'Quiz Rookie', 'description' => 'Take your first quiz.', 'category' => 'quiz', 'rarity' => 'common', 'icon' => 'trophy'],
        ['key' => 'quiz_10', 'name' => 'Quiz Apprentice', 'description' => 'Take 10 quizzes.', 'category' => 'quiz', 'rarity' => 'common', 'icon' => 'trophy'],
        ['key' => 'quiz_50', 'name' => 'Quiz Master', 'description' => 'Take 50 quizzes.', 'category' => 'quiz', 'rarity' => 'rare', 'icon' => 'trophy'],
        ['key' => 'quiz_100', 'name' => 'Trivia Champion', 'description' => 'Take 100 quizzes.', 'category' => 'quiz', 'rarity' => 'epic', 'icon' => 'trophy'],
        ['key' => 'quiz_250', 'name' => 'Quiz Legend', 'description' => 'Take 250 quizzes.', 'category' => 'quiz', 'rarity' => 'epic', 'icon' => 'laurel-crown'],
        ['key' => 'perfect_quiz', 'name' => 'Perfect Quiz', 'description' => 'Score 100% on a story quiz.', 'category' => 'quiz', 'rarity' => 'rare', 'icon' => 'trophy'],
        ['key' => 'perfect_quiz_3', 'name' => 'Hat Trick', 'description' => 'Score 100% on 3 quizzes.', 'category' => 'quiz', 'rarity' => 'rare', 'icon' => 'laurel-crown'],
        ['key' => 'perfect_quiz_10', 'name' => 'Flawless', 'description' => 'Score 100% on 10 quizzes.', 'category' => 'quiz', 'rarity' => 'epic', 'icon' => 'spell-book'],

        // ---- Words ------------------------------------------------------
        ['key' => 'first_word', 'name' => 'Word Starter', 'description' => 'Save your first word to your Word Gym.', 'category' => 'words', 'rarity' => 'common', 'icon' => 'key'],
        ['key' => 'word_collector_25', 'name' => 'Word Collector', 'description' => 'Save 25 words to your Word Gym.', 'category' => 'words', 'rarity' => 'rare', 'icon' => 'cards'],
        ['key' => 'word_collector_100', 'name' => 'Word Wizard', 'description' => 'Save 100 words to your Word Gym.', 'category' => 'words', 'rarity' => 'epic', 'icon' => 'spell-book'],
        ['key' => 'word_collector_500', 'name' => 'Vocabulary Virtuoso', 'description' => 'Save 500 words to your Word Gym.', 'category' => 'words', 'rarity' => 'epic', 'icon' => 'spell-book'],
        ['key' => 'word_collector_1000', 'name' => 'Lexicon Legend', 'description' => 'Save 1,000 words to your Word Gym.', 'category' => 'words', 'rarity' => 'epic', 'icon' => 'book-pile'],

        // ---- Mini-Games ---------------------------------------------------
        ['key' => 'game_first', 'name' => 'Game On', 'description' => 'Play your first mini-game.', 'category' => 'games', 'rarity' => 'common', 'icon' => 'maze'],
        ['key' => 'game_10', 'name' => 'Player One', 'description' => 'Play 10 mini-games.', 'category' => 'games', 'rarity' => 'common', 'icon' => 'cards'],
        ['key' => 'game_50', 'name' => 'Game Master', 'description' => 'Play 50 mini-games.', 'category' => 'games', 'rarity' => 'rare', 'icon' => 'cog'],
        ['key' => 'game_100', 'name' => 'High Score Hero', 'description' => 'Play 100 mini-games.', 'category' => 'games', 'rarity' => 'epic', 'icon' => 'trophy'],
        ['key' => 'ws_5', 'name' => 'Word Search Whiz', 'description' => 'Finish 5 Word Search rounds.', 'category' => 'games', 'rarity' => 'rare', 'icon' => 'map'],
        ['key' => 'match_5', 'name' => 'Match Maker', 'description' => 'Finish 5 Matching rounds.', 'category' => 'games', 'rarity' => 'rare', 'icon' => 'cards'],
        ['key' => 'sentence_5', 'name' => 'Sentence Smasher', 'description' => 'Finish 5 Sentence Builder rounds.', 'category' => 'games', 'rarity' => 'rare', 'icon' => 'book-pile'],
        ['key' => 'game_all_types', 'name' => 'All-Rounder', 'description' => 'Play every mini-game type.', 'category' => 'games', 'rarity' => 'epic', 'icon' => 'maze'],

        // ---- Stamps & Streaks -------------------------------------------
        ['key' => 'first_stamp', 'name' => 'Daily Explorer', 'description' => 'Earn your first daily stamp.', 'category' => 'stamps', 'rarity' => 'common', 'icon' => 'calendar'],
        ['key' => 'stamp_30', 'name' => 'Habit Builder', 'description' => 'Earn 30 daily stamps.', 'category' => 'stamps', 'rarity' => 'rare', 'icon' => 'calendar'],
        ['key' => 'stamp_100', 'name' => 'Century Club', 'description' => 'Earn 100 daily stamps.', 'category' => 'stamps', 'rarity' => 'epic', 'icon' => 'calendar'],
        ['key' => 'stamp_365', 'name' => 'A Year With Alice', 'description' => 'Earn 365 daily stamps.', 'category' => 'stamps', 'rarity' => 'epic', 'icon' => 'crown'],
        ['key' => 'streak_3', 'name' => 'Getting Started', 'description' => 'Reach a 3-day streak.', 'category' => 'stamps', 'rarity' => 'common', 'icon' => 'flame'],
        ['key' => 'streak_7', 'name' => 'On a Roll', 'description' => 'Reach a 7-day streak.', 'category' => 'stamps', 'rarity' => 'rare', 'icon' => 'flame'],
        ['key' => 'streak_14', 'name' => 'In the Zone', 'description' => 'Reach a 14-day streak.', 'category' => 'stamps', 'rarity' => 'rare', 'icon' => 'flame'],
        ['key' => 'streak_30', 'name' => 'Unstoppable', 'description' => 'Reach a 30-day streak.', 'category' => 'stamps', 'rarity' => 'epic', 'icon' => 'laurel-crown'],
        ['key' => 'streak_100', 'name' => 'Immortal', 'description' => 'Reach a 100-day streak.', 'category' => 'stamps', 'rarity' => 'epic', 'icon' => 'laurel-crown'],
        ['key' => 'perfect_week', 'name' => 'Perfect Week', 'description' => 'Earn a stamp on every day of the current week.', 'category' => 'stamps', 'rarity' => 'rare', 'icon' => 'calendar'],
        ['key' => 'perfect_month', 'name' => 'Perfect Month', 'description' => 'Stamp every day of a calendar month.', 'category' => 'stamps', 'rarity' => 'epic', 'icon' => 'calendar'],
        ['key' => 'freeze_first', 'name' => 'Shielded', 'description' => 'Bring your streak back with a Streak Restore for the first time.', 'category' => 'stamps', 'rarity' => 'common', 'icon' => 'snowflake'],
        ['key' => 'freeze_10', 'name' => 'Weatherproof', 'description' => 'Use 10 Streak Restores.', 'category' => 'stamps', 'rarity' => 'rare', 'icon' => 'snowflake'],
        ['key' => 'freeze_25', 'name' => 'Indestructible', 'description' => 'Use 25 Streak Restores.', 'category' => 'stamps', 'rarity' => 'epic', 'icon' => 'snowflake'],
        ['key' => 'freeze_buy_1', 'name' => 'Prepared', 'description' => 'Buy a Streak Restore with keys.', 'category' => 'stamps', 'rarity' => 'common', 'icon' => 'snowflake'],

        // ---- Focus -------------------------------------------------------
        ['key' => 'drill_10', 'name' => 'Drill Sergeant', 'description' => 'Complete 10 study drills.', 'category' => 'focus', 'rarity' => 'common', 'icon' => 'cog'],
        ['key' => 'drill_50', 'name' => 'Focus Master', 'description' => 'Complete 50 study drills.', 'category' => 'focus', 'rarity' => 'rare', 'icon' => 'cog'],
        ['key' => 'drill_200', 'name' => 'Zen Focus', 'description' => 'Complete 200 study drills.', 'category' => 'focus', 'rarity' => 'epic', 'icon' => 'light-bulb'],
        ['key' => 'early_bird', 'name' => 'Early Bird', 'description' => 'Earn a daily stamp before 7am.', 'category' => 'focus', 'rarity' => 'common', 'icon' => 'hourglass'],
        ['key' => 'night_owl', 'name' => 'Night Owl', 'description' => 'Earn a daily stamp after 9pm.', 'category' => 'focus', 'rarity' => 'common', 'icon' => 'crown'],
    ],

    // Thresholds consumed by BadgeService.
    'thresholds' => [
        'stories' => [5, 10, 25, 50],
        'reading_minutes' => [60, 300, 1200, 3000, 10000],
        'quizzes' => [10, 50, 100, 250],
        'perfect_quizzes' => [3, 10],
        'words' => [25, 100, 500, 1000],
        'games' => [10, 50, 100],
        'game_type' => 5,
        'stamps' => [30, 100, 365],
        'streaks' => [3, 7, 14, 30, 100],
        'freezes' => [10, 25],
        'drills' => [10, 50, 200],
    ],
];
