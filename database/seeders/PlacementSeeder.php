<?php

namespace Database\Seeders;

use App\Models\PlacementSet;
use Illuminate\Database\Seeder;

class PlacementSeeder extends Seeder
{
    /**
     * Seed the placement test sets from the authored data file.
     */
    public function run(): void
    {
        $data = require database_path('seeders/data/placement_test_sets.php');

        foreach ($data['sets'] as $set) {
            PlacementSet::updateOrCreate(
                ['slug' => $set['slug']],
                [
                    'title' => $set['title'],
                    'author' => $set['author'],
                    'source_url' => $set['source_url'],
                    'content' => [
                        'vocab' => $set['vocab'],
                        'cloze' => $set['cloze'],
                        'comprehension' => $set['comprehension'],
                    ],
                ],
            );
        }
    }
}
