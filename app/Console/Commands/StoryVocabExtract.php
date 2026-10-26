<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Services\StoryVocabService;
use Illuminate\Console\Command;

class StoryVocabExtract extends Command
{
    protected $signature = 'story-vocab:extract {--story-id= : Extract for a single story only}';

    protected $description = 'Extract vocabulary from published stories and classify by dictionary frequency';

    public function handle(): int
    {
        $storyId = $this->option('story-id');

        if ($storyId) {
            $story = Story::find($storyId);

            if (! $story) {
                $this->error("Story #{$storyId} not found.");

                return 1;
            }

            $count = StoryVocabService::extractForStory($story);
            $this->info("Extracted {$count} vocab words from \"{$story->title}\".");

            return 0;
        }

        $this->info('Extracting vocabulary from all stories...');
        $total = StoryVocabService::extractAll();
        $this->info("Done. Extracted {$total} total vocabulary entries.");

        return 0;
    }
}
