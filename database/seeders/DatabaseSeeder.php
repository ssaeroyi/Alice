<?php

namespace Database\Seeders;

use App\Services\StoryVocabService;
use App\Support\AliceLibrary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlacementSeeder::class);
        $this->call(GenreSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(DailyTaskSeeder::class);
        $this->call(BadgeSeeder::class);

        $summary = AliceLibrary::importAll();

        $this->command?->info('Alice Library: '.$summary['created'].' created, '.$summary['updated'].' updated, '.$summary['skipped'].' skipped, '.count($summary['failed']).' failed.');

        if (DB::table('dictionary_entries')->count() === 0) {
            $exit = Artisan::call('alice:dictionary:import');
            $this->command?->info($exit === 0 ? 'Dictionary imported.' : 'Dictionary import failed.');
        }

        $vocab = StoryVocabService::extractAll();
        $this->command?->info('Story vocabulary: '.$vocab.' entries.');
    }
}
