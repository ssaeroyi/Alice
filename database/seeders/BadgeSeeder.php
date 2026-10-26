<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Seed the badge catalog (S8) from config/badges.php. Idempotent: seeded
     * definitions are updated in place by `key`, so re-running never duplicates.
     */
    public function run(): void
    {
        foreach (config('badges.badges', []) as $def) {
            Badge::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'rarity' => $def['rarity'],
                    'asset_path' => 'ui/'.$def['icon'],
                    'active' => true,
                ],
            );
        }
    }
}
