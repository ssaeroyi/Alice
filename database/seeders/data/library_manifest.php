<?php

/**
 * Alice Library manifest loader - the single source of truth.
 *
 * Merges every database/seeders/data/library/batch_*.php file in name
 * order. Each batch file returns a flat list of book entries; see the
 * PART F CONTENT CHARTER in BATCH_11_PLAN.md before adding one.
 */
return collect(glob(__DIR__.'/library/batch_*.php') ?: [])
    ->sort()
    ->flatMap(fn ($file) => require $file)
    ->values()
    ->all();
