<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Placement scoring thresholds
    |--------------------------------------------------------------------------
    |
    | Accuracy (%) bands that map the placement score to a reading level.
    |
    | The Independent / Instructional / Frustration labels nod to the
    | informal-reading-inventory tradition (Betts, 1946), but the cutoffs
    | are deliberately more forgiving than the clinical ones because this
    | is a short mixed-format check, not a normed instrument:
    |
    | - With few items, normal variance (nerves, one tricky word, a guess)
    |   swings the percentage hard; tight bands would demote students for
    |   noise. A single mistake must never cost a band.
    | - Vocab/cloze items are multiple choice with a ~25% blind-guess
    |   floor, so low scores are still above pure chance; "Critical"
    |   therefore sits at 50%, well clear of guessing alone.
    |
    | Bands as landed (2026-08-26 recalibration): Independent >= 85%,
    | Instructional 70-84%, Frustration 50-69%, Critical below 50%.
    | Review against real cohort data after ~50-100 placements and
    | tighten only if the distribution clusters unhelpfully.
    |
    */

    'independent_threshold' => 85,

    'instructional_threshold' => 70,

    'frustration_threshold' => 50,

    /*
    |--------------------------------------------------------------------------
    | Reading-level derivation (grade-equivalent)
    |--------------------------------------------------------------------------
    |
    | The starting reading_level is the self-reported grade (step 1) nudged
    | down by the assessed comprehension band. Offsets below are subtracted
    | from the grade; the result is floored at grade 7 — 2026-08-30 the
    | library re-banded around a 7-10 serve floor (grades 7-10), so a
    | student whose grade-level math or comprehension says a lower shelf
    | still starts on the easiest grade-7 shelf ("diagnosed below the
    | floor" is labelled as such on the result and educator report). Grade 7
    | is where the entry shelf begins, not a strict pedagogic floor.
    |
    */

    'reading_level_offsets' => [
        'independent' => 0,
        'instructional' => 1,
        'frustration' => 2,
        'critical' => 3,
    ],

    'reading_level_floor' => 7,

    /*
    |--------------------------------------------------------------------------
    | School-year anchor (derived grade)
    |--------------------------------------------------------------------------
    |
    | Month (1-12) when the Philippine school year starts. The grade a student
    | reported during placement (step 1) is anchored to that school year via
    | users.grade_anchor_year, and the "effective grade" shown in the app
    | advances by one each June, capped at grade 10 (Junior High).
    |
    */

    'school_year_start_month' => 6,

    /*
    |--------------------------------------------------------------------------
    | Starter reward (claimable, inbox)
    |--------------------------------------------------------------------------
    |
    | One-time onboarding reward for new accounts, granted as keys only when
    | the student taps CLAIM on the inbox notification created at placement
    | completion (see NotificationService::welcomeKitReward). Keys are ledgered
    | (reason=welcome_kit) so the claim is replay-safe; points are retired —
    | this material start is now a key reward with no points attached.
    |
    */

    'welcome_kit' => [
        'keys' => 20,
        'points' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Motivational phrases (result screen)
    |--------------------------------------------------------------------------
    |
    | Simple, punctuation-light phrases Alice may say alongside the result.
    | One is picked deterministically per attempt (stable across revisits).
    |
    */

    'motivational_phrases' => [
        'independent' => [
            "You're on the right path. Keep it up!",
            "You've reached the top. Keep going!",
        ],
        'instructional' => [
            "You're doing great. Keep climbing!",
            "You're on a roll. Keep it up!",
        ],
        'frustration' => [
            "You're on a good path. Keep practicing!",
            "Little by little, you're getting stronger.",
        ],
        'critical' => [
            'Every reader starts here. You are on a good path!',
            'One story at a time. You will get there!',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preferred name validation
    |--------------------------------------------------------------------------
    |
    | Words a student's preferred name (nickname) must not match
    | (case-insensitive). Keep this list short and editable; it is not a
    | substitute for moderation.
    |
    */

    'banned_names' => [
        'admin',
        'administrator',
        'moderator',
        'alice',
        'fuck',
        'shit',
        'bitch',
        'asshole',
        'bastard',
        'dumbass',
        'porn',
        'nigger',
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily reading goal (minutes)
    |--------------------------------------------------------------------------
    |
    | Placement step 1 offers a range slider in this range (reading_goal_min
    | through reading_goal_max, step 1). Stored in users.daily_reading_goal
    | (nullable unsigned tinyint); null means the student skipped the goal
    | (goal tracker disabled).
    |
    */

    'reading_goal_min' => 3,
    'reading_goal_max' => 60,
    'reading_goal_step' => 1,
    'reading_goal_default' => 10,

    // Legacy preset list — still read by PlacementService::goal_minutes for
    // the result-screen recommendation (defaults to the first entry, 3 min).
    'reading_goal_options' => [3, 5, 10, 15, 20, 25, 30, 45, 60],

    /*
    |--------------------------------------------------------------------------
    | Live reading-progress engine (post-placement growth)
    |--------------------------------------------------------------------------
    |
    | Beyond the one-time placement snapshot, a student's comprehension is
    | maintained as a living, evidence-based estimate (ReadingProgressService)
    | that drives:
    |   - the student's positive "Reading level" growth bar + recommendation band
    |   - the teacher's trajectory ("moved / slipped / no data since placement")
    |
    | Key rules (see SYSTEM_DESIGN.md "reading level that moves"):
    |   - Best-attempt per story (a re-quiz can't grind the level up).
    |   - Difficulty-weighted: a pass on a higher-grade story outweighs the same
    |     score on an easier story.
    |   - Recency-weighted so the level moves "little by little" with practice.
    |   - Secondary (capped) credit for completion volume + reading time so
    |     reading habits genuinely nudge the bar — but quiz evidence sets the
    |     ceiling (you can't reach a high band without demonstrating it).
    |   - Band hysteresis stops an 84.9 <-> 85.1 half-point from ping-ponging
    |     the served grade / teacher pill back and forth.
    |   - A low band only upgrades decisively with evidence across >= 2 distinct
    |     story grades (no single easy-grade farming).
    |   - Decay only on TOTAL inactivity (no reads, quizzes or drills), never on
    |     "not quizzing while still reading" — and never shrinks the student bar.
    |   - "Slipping" is teacher-facing only: an Instructional/Independent student
    |     failing a recent pattern, surfaced alongside the existing reward
    |     decline; Critical/Frustration students are always protected.
    */
    'reading_progress' => [
        // Minimum number of distinct quizzed stories before a live score
        // replaces the placement snapshot (else "from placement").
        'min_sample' => 3,

        // Story grades needed across evidence before a decisive upgrade.
        'min_distinct_grades' => 2,

        // Weight (0-1) given to secondary reading credit vs. the quiz evidence.
        'secondary_weight' => 0.25,

        // Exponential recency half-life in days for evidence weighting.
        'recency_half_life_days' => 30,

        // Total-inactivity window (days) before decay toward a conservative band.
        'decay_inactive_days' => 35,

        // Points a fresh band must hold past the threshold before it sticks.
        'band_hysteresis_points' => 3,

        // "Slipping": last N quizzes with >= this many below pass, no uptrend.
        'slipping_recent_quizzes' => 5,
        'slipping_min_fails' => 3,
    ],

];
