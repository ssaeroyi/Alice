<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * The genre shelf students will browse. Names and descriptions are
     * re-authored for v2 from the old system's domain list (reference
     * only — nothing copied).
     */
    public function run(): void
    {
        $genres = [
            ['name' => 'Fantasy', 'description' => 'Magic, mythical creatures, and imaginary worlds.'],
            ['name' => 'Science Fiction', 'description' => 'Futuristic technology, space, and alien worlds.'],
            ['name' => 'Mystery', 'description' => 'Puzzles, clues, and secrets waiting to be solved.'],
            ['name' => 'Adventure', 'description' => 'Exciting journeys into the unknown.'],
            ['name' => 'Horror', 'description' => 'Spooky stories that send chills down your spine.'],
            ['name' => 'Romance', 'description' => 'Heartfelt stories about friendship, love, and connection.'],
            ['name' => 'Historical Fiction', 'description' => 'The past, mixing real events with fictional characters.'],
            ['name' => 'Realistic Fiction', 'description' => 'Stories that could happen in real life.'],
            ['name' => 'Biography', 'description' => 'True stories about real people.'],
            ['name' => 'Science/Nature', 'description' => 'Facts and stories about animals, plants, space, and how the world works.'],
        ];

        foreach ($genres as $genre) {
            Genre::updateOrCreate(
                ['name' => $genre['name']],
                ['description' => $genre['description']],
            );
        }
    }
}
