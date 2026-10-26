<?php

/**
 * Student avatar catalog (Phase D).
 *
 * ALICE lets each student pick one of a fixed set of vendored, self-contained
 * pixel-art avatars. The files live under public/image/avatar/ (static PNG,
 * no runtime CDN). Storing the raw filename lets the header render it via
 * asset('image/avatar/'.$user->avatar) with no extra lookup.
 *
 * Art is composited from the free **Mana Seed Character Base Demo 2.0** by
 * Seliel the Shaper — body tone × outfit (farmer/forester) × hairstyle layers
 * merged into one 384px portrait PNG per avatar (vendored 2026-10-05, demo
 * licensed for commercial and non-commercial use). The previous DiceBear
 * "Pixel Art" SVGs and their backup folder are archived under
 * storage/asset-packs/_originals/avatars/. The keys are stable (existing
 * students keep their avatar); only the filename extension moved .svg → .png,
 * which a one-time migration rewrites in users + leaderboard snapshots.
 * See public/image/game/ATTRIBUTION.md.
 *
 * This catalog (rather than the filesystem) is the single source of truth for
 * what students may choose — the settings picker renders it and the update
 * endpoint validates against it, so users can never inject an arbitrary path.
 */
return [
    'adventurer' => ['file' => 'adventurer.png', 'name' => 'The Adventurer'],
    'sprout' => ['file' => 'sprout.png', 'name' => 'Sprout'],
    'fox' => ['file' => 'fox.png', 'name' => 'Foxy'],
    'owl' => ['file' => 'owl.png', 'name' => 'Wise One'],
    'star' => ['file' => 'star.png', 'name' => 'Starlight'],
    'bot' => ['file' => 'bot.png', 'name' => 'Bits'],
    'wizard' => ['file' => 'wizard.png', 'name' => 'Spellcaster'],
    'rocket' => ['file' => 'rocket.png', 'name' => 'Rocketeer'],
    'cherry' => ['file' => 'cherry.png', 'name' => 'Cherry'],
    'ocean' => ['file' => 'ocean.png', 'name' => 'Mariner'],
    'ember' => ['file' => 'ember.png', 'name' => 'Ember'],
    'leaf' => ['file' => 'leaf.png', 'name' => 'Fern'],
    'moon' => ['file' => 'moon.png', 'name' => 'Luna'],
    'sun' => ['file' => 'sun.png', 'name' => 'Sunny'],
    'comet' => ['file' => 'comet.png', 'name' => 'Comet'],
    'pixel' => ['file' => 'pixel.png', 'name' => 'Pixel'],
];
