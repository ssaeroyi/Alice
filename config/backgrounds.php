<?php

/*
|--------------------------------------------------------------------------
| Field Journal battle backgrounds (guidelines/SKINS_PIPELINE.md)
|--------------------------------------------------------------------------
|
| price = null means free to own/equip (SkinsController::owned()). Setting
| a price flips that background to the locked/buy flow automatically.
| default = key rendered when prefs point at a removed background.
| file paths are app-root relative; blade asset() and journal.js assetUrl()
| both resolve them against the install base.
|
*/

return [

    'default' => 'forest',

    'list' => [
        'forest' => ['name' => 'Forest', 'file' => '/assets/arena/battle-bg.png', 'thumb' => '/assets/arena/battle-bg.png', 'price' => null],
        'ruins' => ['name' => 'Ancient Ruins', 'file' => '/assets/arena/backgrounds/ruins.png', 'thumb' => '/assets/arena/backgrounds/ruins_thumb.png', 'price' => 50],
        'castle' => ['name' => 'Castle Hall', 'file' => '/assets/arena/backgrounds/castle.png', 'thumb' => '/assets/arena/backgrounds/castle_thumb.png', 'price' => 50],
        'jungle' => ['name' => 'Jungle', 'file' => '/assets/arena/backgrounds/jungle.png', 'thumb' => '/assets/arena/backgrounds/jungle_thumb.png', 'price' => 50],
        'graveyard' => ['name' => 'Graveyard', 'file' => '/assets/arena/backgrounds/graveyard.png', 'thumb' => '/assets/arena/backgrounds/graveyard_thumb.png', 'price' => 50],
        'war1' => ['name' => 'War Village', 'file' => '/assets/arena/backgrounds/war1.png', 'thumb' => '/assets/arena/backgrounds/war1_thumb.png', 'price' => 50],
        'war2' => ['name' => 'War Street', 'file' => '/assets/arena/backgrounds/war2.png', 'thumb' => '/assets/arena/backgrounds/war2_thumb.png', 'price' => 50],
        'war3' => ['name' => 'War Town', 'file' => '/assets/arena/backgrounds/war3.png', 'thumb' => '/assets/arena/backgrounds/war3_thumb.png', 'price' => 50],
        'war4' => ['name' => 'War Night', 'file' => '/assets/arena/backgrounds/war4.png', 'thumb' => '/assets/arena/backgrounds/war4_thumb.png', 'price' => 50],
        'apoc1' => ['name' => 'Ruin Road', 'file' => '/assets/arena/backgrounds/apoc1.png', 'thumb' => '/assets/arena/backgrounds/apoc1_thumb.png', 'price' => 50],
        'apoc2' => ['name' => 'Dead Forest', 'file' => '/assets/arena/backgrounds/apoc2.png', 'thumb' => '/assets/arena/backgrounds/apoc2_thumb.png', 'price' => 50],
        'apoc3' => ['name' => 'Dust Desert', 'file' => '/assets/arena/backgrounds/apoc3.png', 'thumb' => '/assets/arena/backgrounds/apoc3_thumb.png', 'price' => 50],
        'apoc4' => ['name' => 'Ghost Station', 'file' => '/assets/arena/backgrounds/apoc4.png', 'thumb' => '/assets/arena/backgrounds/apoc4_thumb.png', 'price' => 50],
        'badlands1' => ['name' => 'Badlands 1', 'file' => '/assets/arena/backgrounds/badlands1.png', 'thumb' => '/assets/arena/backgrounds/badlands1_thumb.png', 'price' => 50],
        'badlands2' => ['name' => 'Badlands 2', 'file' => '/assets/arena/backgrounds/badlands2.png', 'thumb' => '/assets/arena/backgrounds/badlands2_thumb.png', 'price' => 50],
        'badlands3' => ['name' => 'Badlands 3', 'file' => '/assets/arena/backgrounds/badlands3.png', 'thumb' => '/assets/arena/backgrounds/badlands3_thumb.png', 'price' => 50],
        'badlands4' => ['name' => 'Badlands 4', 'file' => '/assets/arena/backgrounds/badlands4.png', 'thumb' => '/assets/arena/backgrounds/badlands4_thumb.png', 'price' => 50],
    ],

];
