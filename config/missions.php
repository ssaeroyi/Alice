<?php

/*
|--------------------------------------------------------------------------
| Field Journal battle tuning (guidelines/JOURNAL_BATTLES.md)
|--------------------------------------------------------------------------
| Timers are generous on purpose: hearts are lost to timeout/quit only,
| never to wrong answers. Static pools below are the literal workbook
| exercises (Spelling Made Easy reference photos, 2026-10-20) — mechanics
| stay literal; only story-derived generators adapt to the story.
*/

return [
    'hearts' => 3,
    'boss_hp' => 3,
    'actions_per_battle' => 3,

    // Teacher-authored pool: up to N moves stored per story, each battle
    // draws a random actions_per_battle from them (padded with auto moves
    // when the pool is smaller or a rule no longer fits the text).
    'authored_max' => 10,

    // Which generator families emit actions. Find is the backbone; affix
    // (word-part fill, dictionary-checked stems) and alphabetize (ABC order
    // choice) are the story-derived families turned on. Ladder, doubles and
    // riddle stay off until their pass.
    // TEMPORARILY HIDDEN: 'alphabetize' (ABC Order) is removed from the pool,
    // so battles never draw it. Add it back here to restore it.
    'generators' => ['find', 'affix', 'arrange'],

    // Initial-letter hunt: two-letter starts offered beside the 26 single
    // letters (teacher-picked, live-counted). Matching is by whole prefix.
    'initial_patterns' => ['th', 'sh', 'ch', 'wh', 'br', 'cl', 'fl', 'gr', 'pl', 'sp', 'st', 'tr'],

    // Word-part fill: affixes the auto pool may blank (AffixGenerator).
    // Every affix the story supports also ships its own teacher tile in the
    // Journal add-action gallery (authored value pins the affix).
    'affixes' => [
        'prefix' => ['ex', 'en', 'in', 'un', 'dis'],
        'suffix' => ['ty', 'ful', 'ness'],
    ],

    'timers' => [
        'find' => 30,
        'fill' => 75,
        'choice' => 45,
        'arrange' => 75,
    ],

    // Workbook K: supply the double letter. ['shown' => ..., 'answer' => 'cc']
    'doubles_classic' => [
        ['shown' => 'so__er', 'answer' => 'cc', 'word' => 'soccer'],
        ['shown' => 'bu__on', 'answer' => 'ff', 'word' => 'button'],
        ['shown' => 'bu__er', 'answer' => 'tt', 'word' => 'butter'],
        ['shown' => 'ma__er', 'answer' => 'nn', 'word' => 'manner'],
        ['shown' => 'ru__ian', 'answer' => 'ss', 'word' => 'russian'],
        ['shown' => 'da__er', 'answer' => 'gg', 'word' => 'dagger'],
        ['shown' => 'di__er', 'answer' => 'nn', 'word' => 'dinner'],
        ['shown' => 'sle__p', 'answer' => 'ee', 'word' => 'sleep'],
        ['shown' => 'vi__ain', 'answer' => 'll', 'word' => 'villain'],
        ['shown' => 'a__ident', 'answer' => 'cc', 'word' => 'accident'],
        ['shown' => 'fr__dom', 'answer' => 'ee', 'word' => 'freedom'],
        ['shown' => 'la__le', 'answer' => 'dd', 'word' => 'ladle'],
    ],

    // Workbook B: add a letter, guided by the meaning hint.
    'ladders' => [
        ['base' => 'mat', 'hint' => 'sail support', 'answer' => 'mast'],
        ['base' => 'rite', 'hint' => 'treat as stale', 'answer' => 'trite'],
        ['base' => 'art', 'hint' => 'a map of the sea', 'answer' => 'chart'],
        ['base' => 'sage', 'hint' => 'a platform for actors', 'answer' => 'stage'],
        ['base' => 'road', 'hint' => 'wide', 'answer' => 'broad'],
        ['base' => 'sin', 'hint' => 'a mark', 'answer' => 'sign'],
        ['base' => 'pane', 'hint' => 'a discussion group', 'answer' => 'panel'],
        ['base' => 'night', 'hint' => 'a man in shining armor', 'answer' => 'knight'],
        ['base' => 'gain', 'hint' => 'a fiber arrangement', 'answer' => 'grain'],
        ['base' => 'annul', 'hint' => 'yearly', 'answer' => 'annual'],
        ['base' => 'wear', 'hint' => 'a solemn vow', 'answer' => 'swear'],
        ['base' => 'write', 'hint' => 'to twist in pain', 'answer' => 'writhe'],
    ],

    // Workbook A: letter-fact riddles (static knowledge, always fit).
    'riddles' => [
        ['q' => 'A body part spelled with only three letters', 'choices' => ['ear', 'eye', 'arm'], 'answer' => 'ear'],
        ['q' => 'Sounds like a busy insect', 'choices' => ['bee', 'ant', 'fly'], 'answer' => 'bee'],
        ['q' => 'A body of water that sounds like the letter C', 'choices' => ['sea', 'lake', 'pond'], 'answer' => 'sea'],
        ['q' => 'A bird that sounds like the letter J', 'choices' => ['jay', 'owl', 'crow'], 'answer' => 'jay'],
        ['q' => 'The pronoun that sounds like the letter U', 'choices' => ['you', 'we', 'he'], 'answer' => 'you'],
        ['q' => 'A beverage that sounds like the letter T', 'choices' => ['tea', 'milk', 'juice'], 'answer' => 'tea'],
        ['q' => 'How far do you count before using the letter A in a number?', 'choices' => ['one thousand', 'one hundred', 'one million'], 'answer' => 'one thousand'],
        ['q' => 'A hint, spelled like the letter Q', 'choices' => ['cue', 'clue', 'key'], 'answer' => 'cue'],
    ],
];
