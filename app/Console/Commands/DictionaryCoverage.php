<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Support\Dictionary;
use Illuminate\Console\Command;

/**
 * Defense-proof coverage audit (TTS_AND_DICTIONARY.md §5): tokenizes every
 * published story, resolves each word through the full Dictionary ladder,
 * and reports the resolvable percentage with misses split into proper
 * nouns (expected gaps) versus real dictionary holes.
 */
class DictionaryCoverage extends Command
{
    protected $signature = 'alice:dictionary:coverage {--story= : Audit a single story slug}';

    protected $description = 'Audit how much of the published library the offline dictionary can define';

    public function handle(): int
    {
        $stories = Story::query()
            ->where('status', StoryStatus::Published->value)
            ->when($this->option('story'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'content']);

        if ($stories->isEmpty()) {
            $this->warn('No published stories found.');

            return self::SUCCESS;
        }

        $tokens = 0;
        $resolved = 0;
        $properNouns = 0;
        $misses = [];
        $perStory = [];

        // Audit-local memo: the same words repeat across every story, and
        // each miss costs several indexed queries. One process-wide
        // "word => [found, looks_like_name]" map collapses that to one
        // ladder walk per distinct word for the whole run.
        $memo = [];

        foreach ($stories as $story) {
            $storyTokens = 0;
            $storyResolved = 0;
            $storyProperNouns = 0;

            foreach ($this->tokenize((string) $story->content) as [$word, $surface]) {
                $storyTokens++;

                // Memo key is the normalized word, but the ladder gets the
                // original surface form: its proper-noun heuristic reads
                // capitalization.
                if (! isset($memo[$word])) {
                    $result = Dictionary::lookup($surface);
                    $memo[$word] = [$result['found'], $result['looks_like_name'], $result['word']];
                }

                [$found, $looksLikeName] = $memo[$word];

                if ($found) {
                    $storyResolved++;

                    continue;
                }

                if ($looksLikeName) {
                    // Proper nouns are expected dictionary gaps; counting
                    // them as resolved keeps the headline honest about
                    // real holes instead.
                    $storyProperNouns++;
                    $storyResolved++;

                    continue;
                }

                $misses[$memo[$word][2]] = ($misses[$memo[$word][2]] ?? 0) + 1;
            }

            $perStory[] = [$story->slug, number_format($storyTokens), $storyTokens > 0
                ? round(100 * $storyResolved / $storyTokens, 1).'%'
                : '-'];

            $tokens += $storyTokens;
            $resolved += $storyResolved;
            $properNouns += $storyProperNouns;
        }

        $uniqueMisses = count($misses);
        $realMisses = array_sum($misses);
        $coverage = $tokens > 0 ? round(100 * ($tokens - $realMisses) / $tokens, 2) : 100.0;
        $uniqueTotal = $tokens - $realMisses + $uniqueMisses;
        $uniqueCoverage = $uniqueTotal > 0 ? round(100 * ($uniqueTotal - $uniqueMisses) / $uniqueTotal, 2) : 100.0;

        $this->table(['story', 'words', 'coverage'], $perStory);

        $this->info(sprintf(
            'Library coverage: %.2f%% of %s tokens (%s proper-noun guesses counted as resolved).',
            $coverage, number_format($tokens), number_format($properNouns),
        ));
        $this->info(sprintf(
            'Unique-word coverage: %.2f%% — %s unique misses (%s raw occurrences):',
            $uniqueCoverage, number_format($uniqueMisses), number_format($realMisses),
        ));

        foreach (collect($misses)->sortDesc()->take(30) as $word => $count) {
            $this->line(sprintf('  %-28s x%s', $word, number_format($count)));
        }

        return self::SUCCESS;
    }

    /** @return \Generator<array{0: string, 1: string}> [normalized word, surface form] */
    private function tokenize(string $content): \Generator
    {
        foreach (preg_split('/\s+/u', trim($content)) ?: [] as $surface) {
            $word = Dictionary::normalize($surface);

            if ($word !== '') {
                yield [$word, $surface];
            }
        }
    }
}
