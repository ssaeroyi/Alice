<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Support\StoryPagination;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Stage narration work for the offline generator (TTS_AND_DICTIONARY.md §3.4):
 * writes storage/app/narration/export.json - a plain JSON handshake holding
 * every published story's reader pages, so Python never needs DB credentials.
 * tools/narration/generate_narration.py consumes this file and produces the
 * MP3s + manifests; alice:narration:sync audits the result afterwards.
 */
class NarrationExport extends Command
{
    protected $signature = 'alice:narration:export
                            {--story= : Export only this story slug}
                            {--force : Rewrite export.json even when nothing changed}';

    protected $description = 'Export published story pages as JSON for the offline TTS generator';

    public function handle(): int
    {
        $query = Story::query()
            ->where('status', StoryStatus::Published->value)
            ->whereRaw('TRIM(content) <> ? COLLATE utf8mb4_unicode_ci', [''])
            ->orderBy('id');

        if ($slug = $this->option('story')) {
            $query->where('slug', $slug);
        }

        $storiesPayload = [];
        $sectionCount = 0;

        foreach ($query->get() as $story) {
            $pages = StoryPagination::pages($story->content)['pages'];
            $sections = [];

            foreach (array_values($pages) as $index => $pageText) {
                $sections[] = [
                    'n' => $index + 1,
                    'text' => $pageText,
                ];
            }

            $sectionCount += count($sections);

            $storiesPayload[] = [
                'slug' => $story->slug,
                'title' => $story->title,
                'updated_at' => $story->updated_at?->toIso8601String(),
                'sections' => $sections,
            ];

            $this->line(sprintf('  %s — %d section(s)', $story->slug, count($sections)));
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'voice' => config('stories.narration.voice', 'af_heart'),
            'model' => config('stories.narration.model', 'kokoro-v1.0'),
            'stories' => $storiesPayload,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            $this->error('Could not encode export payload.');

            return self::FAILURE;
        }

        Storage::disk('local')->put('narration/export.json', $encoded."\n");

        $this->info(sprintf(
            'Exported %d story/stories (%d sections) to %s',
            count($storiesPayload),
            $sectionCount,
            Storage::disk('local')->path('narration/export.json'),
        ));

        return self::SUCCESS;
    }
}
