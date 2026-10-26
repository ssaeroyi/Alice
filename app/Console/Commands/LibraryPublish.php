<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Support\AliceLibrary;
use Illuminate\Console\Command;

/**
 * Publish every curated Alice Library book that is still a draft, without
 * rescanning content. The manifest crosswalk (gutenberg_id + lower(title))
 * is the same one AliceLibrary::resolveStory uses, so only manifest entries
 * are ever touched - teacher-authored stories stay untouched. Idempotent:
 * already-published books and deliberately archived ones are skipped.
 */
class LibraryPublish extends Command
{
    protected $signature = 'alice:library:publish';

    protected $description = 'Publish curated Alice Library books that are still drafts (idempotent; archived books are respected)';

    public function handle(): int
    {
        $published = 0;
        $already = 0;
        $skipped = 0;

        foreach (AliceLibrary::manifest() as $entry) {
            $story = Story::query()
                ->withTrashed()
                ->where('gutenberg_id', $entry['gutenberg_id'])
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($entry['title'])])
                ->first();

            if ($story === null) {
                $skipped++;

                continue;
            }

            if ($story->status === StoryStatus::Published) {
                $already++;

                continue;
            }

            if (AliceLibrary::ensureCuratedPublished($story)) {
                $published++;
            } else {
                $skipped++;
            }
        }

        $this->line(sprintf(
            'Curated Alice Library: %s published, %s already published, %s skipped.',
            $published,
            $already,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
