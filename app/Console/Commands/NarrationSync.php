<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Support\Narration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Audit narration coverage against the live library (TTS_AND_DICTIONARY.md
 * §3.4/§3.6): compares each story's current page hashes with its manifest,
 * reports covered/stale/missing sections, and - with --prune - deletes MP3s
 * the manifest no longer references. Narration files of stories that no
 * longer exist are an on-disk archive and survive --prune; --purge-audio
 * (with --prune) is the explicit hard erase for a dead story's audio. Never
 * deletes anything without --prune.
 */
class NarrationSync extends Command
{
    protected $signature = 'alice:narration:sync
                            {--prune : Delete orphan MP3s (keeps archived narration for dead stories)}
                            {--purge-audio : With --prune, also erase the narration archive of dead stories}';

    protected $description = 'Report narration coverage per story and optionally prune orphans';

    public function handle(): int
    {
        $local = Storage::disk('local');
        $public = Storage::disk('public');

        $stories = Story::query()
            ->where('status', StoryStatus::Published->value)
            ->orderBy('id')
            ->get();

        $liveSlugs = $stories->pluck('slug')->all();
        $totals = ['covered' => 0, 'stale' => 0, 'missing' => 0];

        foreach ($stories as $story) {
            $coverage = Narration::coverage($story, fresh: true);

            if (! $coverage['manifest']) {
                $totals['missing'] += $coverage['total'];
                $this->line(sprintf('  %-40s no manifest — %d section(s) unnarrated', $story->slug, $coverage['total']));

                continue;
            }

            $totals['covered'] += $coverage['covered'];
            $totals['stale'] += $coverage['stale'];
            $totals['missing'] += $coverage['missing'];

            $this->line(sprintf(
                '  %-40s %d/%d fresh (%d stale, %d missing)',
                $story->slug,
                $coverage['covered'],
                $coverage['total'],
                $coverage['stale'],
                $coverage['missing'],
            ));

            // Never prune a story the generator is actively writing: its
            // per-section manifest may not yet reference MP3s it just laid
            // down, and pruning those would delete fresh audio (TTS_AND_DICTIONARY §3.6).
            if ($this->option('prune') && ! Narration::hasActiveRun($story->slug)) {
                $this->pruneStory($public, $story->slug, $coverage['referenced']);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Coverage: %d fresh / %d stale / %d missing across %d published stories.',
            $totals['covered'],
            $totals['stale'],
            $totals['missing'],
            $stories->count(),
        ));

        if ($this->option('prune')) {
            $archived = 0;
            $purged = 0;

            // Dead stories keep their narration on disk so deleting and
            // re-importing the same source stays instantly narrated. --prune
            // only reports them; adding --purge-audio hard-erases them.
            foreach ($local->files('narration/manifests') ?: [] as $file) {
                $slug = preg_replace('/\.json$/', '', basename($file)) ?? '';

                if (in_array($slug, $liveSlugs, true)) {
                    continue;
                }

                if ($this->option('purge-audio') && ! Narration::hasActiveRun($slug)) {
                    Narration::purge($slug);
                    $purged++;
                    $this->line(sprintf('  purged narration of dead story: %s', $slug));
                } else {
                    $archived++;
                    $this->line(sprintf('  archived narration preserved: %s', $slug));
                }
            }

            // Wanted entries for stories that are no longer published have
            // nowhere to go — drop them so the queue never fills with ghosts.
            $prunedWanted = Narration::pruneWantedAgainst($liveSlugs);

            if ($prunedWanted > 0) {
                $this->line(sprintf('  pruned %d stale wanted entr%s', $prunedWanted, $prunedWanted === 1 ? 'y' : 'ies'));
            }

            if ($purged > 0 || $prunedWanted > 0 || $archived > 0 || $this->output->isVerbose()) {
                $this->info(sprintf('Archived %d dead manifest(s) preserved.', $archived));
            }
        }

        return self::SUCCESS;
    }

    /** Delete MP3s under a story's audio dir that the fresh manifest set does not reference. */
    private function pruneStory($public, string $slug, array $referenced): void
    {
        $dir = "audio/stories/{$slug}";

        if (! $public->exists($dir)) {
            return;
        }

        $removed = 0;

        foreach ($public->files($dir) ?: [] as $file) {
            $name = basename($file);

            if (str_ends_with($name, '.mp3') && ! in_array($name, $referenced, true)) {
                $public->delete("{$dir}/{$name}");
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line(sprintf('    pruned %d orphan mp3(s) from %s', $removed, $slug));
        }
    }
}
