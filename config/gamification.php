<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Growth Loop economy constants (STUDENT_PAGES_PLAN.md §2.1)
    |
    | RewardService is the ONLY consumer of these values. No ad-hoc key or
    | XP math anywhere else (SYSTEM_DESIGN §11.3 mandate).
    |--------------------------------------------------------------------------
    */

    // Score at or above this percentage passes a story quiz.
    'pass_threshold_pct' => 70,

    // First-clear base rewards by difficulty band (stories.difficulty_label).
    // The library serves grades 7-10 (scales below 7 removed 2026-08-30), so
    // the four live bands below are the only ones new content can land on;
    // grades map 7->Easy, 8->Intermediate, 9->Hard, 10->Very Hard.
    'bands' => [
        'Easy' => ['keys' => 8, 'xp' => 15],
        'Intermediate' => ['keys' => 12, 'xp' => 25],
        'Hard' => ['keys' => 16, 'xp' => 40],
        'Very Hard' => ['keys' => 20, 'xp' => 60],
    ],

    // Band used when a story has no difficulty_label (below analyzer floor).
    'fallback_band' => 'Easy',

    // Accuracy tier multipliers applied to both keys and XP.
    // >=100% pays the perfect bonus tier (B1: perfect-on-first-attempt bonus).
    'accuracy_tiers' => [
        'under_50' => 0.5,
        'under_70' => 0.75,
        'under_100' => 1.0,
        'perfect' => 1.25,
    ],

    // Retakes pay only this share of the improved XP delta; never keys (B1).
    'retake_delta_share' => 0.25,

    // Same-day diminishing curve for distinct rewarding activities (B3):
    // 1st activity ×1.00, 2nd ×0.75, 3rd ×0.50, 4th ×0.30, then floor (B3).
    'same_day_multipliers' => [1.0, 0.75, 0.5, 0.3],
    'same_day_floor' => 0.15,

    // Word Gym scored drills: first completed drill per deck per day.
    'drill' => [
        'first_daily_keys' => 4,
        'first_daily_xp' => 8,
    ],

    // Mini-game scoring tiers (score is 0-100). Games award POINTS only —
    // keys stay reserved for quiz first-clears and daily tasks (S1). The
    // *_keys values are kept at 0 so nothing can drip keys from game grind.
    'game' => [
        'excellent_keys' => 0,
        'excellent_xp' => 6,
        'good_keys' => 0,
        'good_xp' => 4,
        'poor_keys' => 0,
        'poor_xp' => 2,
        'excellent_threshold' => 80,
        'good_threshold' => 50,
    ],

    // Time multipliers by difficulty (applied to base time limits).
    'game_time_multiplier' => [
        'easy' => 1.5,
        'medium' => 1.0,
        'hard' => 0.7,
    ],

    // Base time limits (seconds) for each mini-game. Actual limit =
    // base_time * game_time_multiplier. Rule: WordSearch 12s/word (10 words),
    // Matching 20s/pair (10 pairs / 20 cards), Sentence per round (5 sentences) — one
    // continuous countdown shared across the whole round, not per sentence.
    'game_base_time' => [
        'wordsearch' => 120,
        'match' => 100,
        'sentence' => 150,
    ],

    // Memory game memorize phase: every card is shown face-up for this many
    // seconds while a top badge counts down (so cards stay visible). The grace
    // period is followed by a quick 3-2-1 overlay, then START flips the cards
    // and the game timer begins. Same for every difficulty — harder difficulty
    // means less time to match, not less to memorize.
    'match_memorize_seconds' => 10,

    // Reader themes unlockable with game points.
    // (Removed — economy under redesign.)

    // =====================================================================
    // POINTS — the single progression foundation (S1, gamification model).
    // Every literacy act feeds points through RewardService; the PointsRank
    // title ladder is derived from the user's running points total. Reading
    // earns points per minute, daily-capped (so it cannot exceed the print
    // time a child realistically has); quizzes and games contribute via the
    // existing band/game tier "xp" amounts, which now reward points.
    // =====================================================================
    'points' => [
        // Points per full minute of reading (heartbeat-accumulated). The
        // fractional rate pays whole-point quarters of a minute: 1 full minute
        // = 1 point, 2 minutes = 3 points. Raised from 1.0 (2026-08-30) so
        // reading feels a little more rewarding.
        'reading_per_minute' => 1.5,
        // Daily cap on reading-earned points (= cap / rate = 60 minutes).
        'reading_daily_cap' => 90,
        // Reading-themed title ladder, ascending by min_points. PointsRank
        // returns the highest tier whose min_points the user has reached.
        // Roughly doubling spacing so higher tiers feel aspirational but stay
        // reachable through regular reading/quizzing/games/tasks (M-style).
        'titles' => [
            ['name' => 'New Reader', 'icon' => 'open-book', 'min_points' => 0],
            ['name' => 'Bookworm', 'icon' => 'magnifying-glass', 'min_points' => 50],
            ['name' => 'Page Turner', 'icon' => 'notebook', 'min_points' => 150],
            ['name' => 'Story Explorer', 'icon' => 'compass', 'min_points' => 300],
            ['name' => 'Chapter Master', 'icon' => 'queen-crown', 'min_points' => 600],
            ['name' => 'Bibliophile', 'icon' => 'book-storm', 'min_points' => 1200],
            ['name' => 'Word Sage', 'icon' => 'scroll-quill', 'min_points' => 2200],
            ['name' => 'Literary Ace', 'icon' => 'book-aura', 'min_points' => 3600],
            ['name' => 'Tale Weaver', 'icon' => 'yarn', 'min_points' => 5500],
            ['name' => 'Benevolent', 'icon' => 'lantern', 'min_points' => 8000],
        ],
    ],

    // One-time title-promotion reward, paid once per freshly reached tier
    // (T-title ceremony). Indexed to match points.titles (0 = New Reader,
    // which never awards). Amounts escalate gently with the point gap so late
    // milestones feel rewarding but stay a modest token — the badge, not the
    // keys, is the real trophy. Detective: dev-tool point edits never reach
    // RewardService, so they can never farm these keys.
    'rank' => [
        'promotion_keys' => [
            0 => 0,   // New Reader — no payout (free baseline)
            1 => 5,   // Bookworm
            2 => 7,   // Page Turner
            3 => 9,   // Story Explorer
            4 => 11,  // Chapter Master
            5 => 14,  // Bibliophile
            6 => 17,  // Word Sage
            7 => 20,  // Literary Ace
            8 => 23,  // Tale Weaver
            9 => 26,  // Benevolent
        ],
    ],

    // Phrases Alice says on game summary based on performance.
    'game_phrases' => [
        'excellent' => [
            'Amazing work! You are getting really good at this!',
            'Perfect score! You really know your vocabulary!',
            'Incredible! Keep this momentum going!',
            'You nailed it! Every word is sticking!',
            'Outstanding! Your vocabulary is growing fast!',
        ],
        'good' => [
            'Nice work! Every game makes you stronger.',
            'Good job! You are making real progress.',
            'Solid effort! Keep practicing and you will get even better.',
            'Well done! Your hard work is paying off.',
            'Great job! You are building strong word skills.',
        ],
        'poor' => [
            'Do not worry! Practice makes perfect.',
            'Keep trying! Every attempt teaches you something new.',
            'You will get better with each game. Keep going!',
            'Do not give up! Learning takes time and you are doing great.',
            'Every word you play with is a word you are learning.',
        ],
    ],

    // Daily task claim rewards (seeded per task; these bound the seeders).
    'task' => [
        'min_keys' => 5,
        'max_keys' => 10,
        'min_xp' => 10,
        'max_xp' => 20,
    ],

    // Daily goal completion rewards (issues 1 & 3): auto-credited keys when a
    // student meets their reading or study-word goal for the day, once per day.
    'goal' => [
        'reading_keys' => 5,
        'study_keys' => 3,
    ],

    // Key sinks (launch prices). Hint tokens, streak freezes and the daily-
    // task reroll are the only remaining sinks — theme/stamp-skin sinks were
    // removed with the cosmetics tables (2026-08-27).
    'sinks' => [
        'hint_token' => 5,
        'streak_freeze' => 15,
        'task_reroll' => 2,
    ],
    'hint_tokens_per_quiz' => 2,

    // =====================================================================
    // SEMANTIC COLOR MAP (S2) — one authoritative Tailwind/hex source for the
    // economy + rank vocabulary so every badge, chip and reward popup shares
    // the same colour language (key=amber, streak=rose, points=emerald,
    // title=violet, tasks=emerald, freeze=sky, minutes=ink, high score=amber).
    // Consumers: S4 nav chips + MY STATS panel, quiz/reader reward popups,
    // and the streak/title ceremonies. `text`/`bg` are Tailwind utilities the
    // blades look up directly; a JS-safe hex twin lives alongside for inline
    // SVG/graph work. Icons name vendored pictorial SVGs under
    // public/image/game/icons/ui/ (game-icons.net CC BY 3.0 / Nieobie
    // CC0 — see ATTRIBUTION.md).
    // =====================================================================
    'colors' => [
        'keys' => ['text' => 'text-amber-600',    'bg' => 'bg-amber-500',    'hex' => '#D97706', 'icon' => 'key'],
        'streak' => ['text' => 'text-rose-600',     'bg' => 'bg-rose-500',     'hex' => '#E11D48', 'icon' => 'flame'],
        'points' => ['text' => 'text-emerald-700',  'bg' => 'bg-emerald-500',  'hex' => '#047857', 'icon' => 'hourglass'],
        'title' => ['text' => 'text-violet-700',   'bg' => 'bg-violet-500',   'hex' => '#6D28D9', 'icon' => 'crown'],
        'tasks' => ['text' => 'text-emerald-700',  'bg' => 'bg-emerald-500',  'hex' => '#047857', 'icon' => 'calendar'],
        'freeze' => ['text' => 'text-sky-600',      'bg' => 'bg-sky-500',      'hex' => '#0284C7', 'icon' => 'snowflake'],
        'minutes' => ['text' => 'text-[#2D231E]',    'bg' => 'bg-[#2D231E]',    'hex' => '#2D231E', 'icon' => 'trophy'],
        'highscore' => ['text' => 'text-amber-600',    'bg' => 'bg-amber-500',    'hex' => '#D97706', 'icon' => 'trophy'],
    ],

    // Streak freezes: auto-grant on the first stamped day of a month when
    // holding none; hard cap on held freezes.
    'freeze' => [
        'monthly_grant' => 1,
        'hold_cap' => 2,
    ],

    // First-time story-clear economy (C2): reading a book to the end for the
    // first time pays this flat keys sum plus a difficulty-scaled XP award once
    // per story (never on re-reads). Reaching the end (95% scroll / the finish
    // button) with any real reading time banked qualifies; the old 10s session
    // floor was dropped 2026-09-06. Keys stay flat so the key-sink economy never
    // inflates; XP scales by the story's difficulty band so finishing a harder
    // book carries more merit. Max per day caps how many distinct first-clears
    // can pay in a day.
    'story_clear' => [
        'keys' => 3,
        'xp' => 5,
        'max_per_day' => 3,
        // Difficulty-scaled XP for the first clear. Keys are always the flat
        // `keys` above; xp_by_band[band] ?? the flat `xp` fallback applies.
        'xp_by_band' => [
            'Easy' => 5,
            'Intermediate' => 8,
            'Hard' => 12,
            'Very Hard' => 18,
        ],
    ],

    // Daily reading goal (D): the hub's goal editor is a range slider over
    // [reading_goal_min, reading_goal_max]. 0 means "no goal set" (stored as
    // null) so a student can opt out later — placement's own floor is higher
    // (config('placement.reading_goal_min')), this range adds the 0 opt-out.
    'reading_goal_min' => 0,
    'reading_goal_max' => 60,
    'reading_goal_step' => 1,
];
