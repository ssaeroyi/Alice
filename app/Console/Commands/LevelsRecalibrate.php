<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Support\AliceLibrary;
use Illuminate\Console\Command;

/**
 * One-off recalibration of the library's reading-level scale (2026-08-30):
 * the served grades moved from 6-10 to 7-10, and the difficulty taxonomy from
 * five bands to four (grades 7->Easy, 8->Intermediate, 9->Hard, 10->Very Hard).
 *
 * Stories whose stored level labeled the removed grade-6 shelf are re-derive
 * from the raw Flesch-Kincaid measurement already kept in
 * difficulty_breakdown.text.grade — no re-analysis needed. A sweep pass then
 * re-asserts every row's stored difficulty_label against the current
 * difficultyBand(reading_level) mapping so no stale label survives.
 *
 * Idempotent: re-running after a successful apply reports zero changes.
 */
class LevelsRecalibrate extends Command
{
    protected $signature = 'alice:levels:recalibrate {--dry-run : Preview changes without writing}';

    protected $description = 'Recalibrate story levels/labels to the 7-10 scale (drop grade 6, four difficulty bands)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $rows = Story::query()
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'status', 'reading_level', 'difficulty_label', 'difficulty_breakdown']);

        if ($rows->isEmpty()) {
            $this->warn('No stories in the database.');

            return self::SUCCESS;
        }

        $changes = [];

        foreach ($rows as $story) {
            $breakdown = is_array($story->difficulty_breakdown) ? $story->difficulty_breakdown : [];
            $rawFk = (int) ($breakdown['text']['grade'] ?? 0);

            $newGrade = max(
                7,
                AliceLibrary::gradeForReadingLevel($rawFk > 0 ? $rawFk : (int) $story->reading_level),
            );
            $newLabel = AliceLibrary::difficultyBand($newGrade);
            $newTotal = (int) $newGrade;

            $gradeChanged = (int) $story->reading_level !== $newGrade;
            $labelChanged = (string) $story->difficulty_label !== $newLabel;
            $totalChanged = ($breakdown['total'] ?? null) !== $newTotal;

            if (! $gradeChanged && ! $labelChanged && ! $totalChanged) {
                continue;
            }

            $changes[] = [
                $story->slug,
                $story->status?->value ?? 'draft',
                (string) $story->reading_level,
                (string) $story->difficulty_label,
                (string) $newGrade,
                $newLabel,
            ];

            if (! $dry) {
                $breakdown['total'] = $newTotal;

                $story->update([
                    'reading_level' => $newGrade,
                    'difficulty_label' => $newLabel,
                    'difficulty_breakdown' => $breakdown,
                ]);
            }
        }

        if ($changes === []) {
            $this->info('No stories need recalibration — the library is already on the 7-10 scale.');

            return self::SUCCESS;
        }

        $this->table(
            ['slug', 'status', 'level (was)', 'label (was)', 'level', 'label'],
            $changes,
        );

        if ($dry) {
            $this->warn(sprintf(
                '[dry-run] %s row(s) would change. Re-run without --dry-run to apply.',
                count($changes),
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf('Recalibrated %s story row(s) to the 7-10 scale.', count($changes)));

        return self::SUCCESS;
    }
}
