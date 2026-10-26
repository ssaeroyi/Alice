<?php

namespace App\Support;

use App\Enums\StoryStatus;
use App\Http\Controllers\Student\LibraryController;
use App\Models\Genre;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Services\StoryVocabService;
use Illuminate\Support\Facades\DB;

/**
 * Imports the curated Alice Library package (database/seeders/data/
 * library_manifest.php) into published stories - synchronously, fully local,
 * idempotent. One transaction per book; quizzes go through QuestionWriter
 * so what lands in the database is byte-for-byte what the builder writes.
 * Every curated book lands published (its publish gates are filled by
 * construction); a book a teacher deliberately archived stays archived.
 *
 * See the PART F CONTENT CHARTER in BATCH_11_PLAN.md before touching
 * content: school-standard bar, punctuation set, word fidelity, gates.
 */
class AliceLibrary
{
    /** Duration/stamina tiers by word count with their matrix points. */
    private const DURATION_TIERS = [
        'flash' => ['max_words' => 499, 'points' => 1],
        'quick' => ['max_words' => 999, 'points' => 1],
        'short' => ['max_words' => 1799, 'points' => 2],
        'standard' => ['max_words' => 2599, 'points' => 3],
        'long' => ['max_words' => PHP_INT_MAX, 'points' => 4],
    ];

    private const QUIZ_POINTS = [
        'easy' => 1,
        'medium' => 2,
        'hard' => 3,
    ];

    /** Same ceiling as the story form's synopsis textarea (maxlength). */
    private const SYNOPSIS_MAX_CHARS = 2000;

    /**
     * Hard floor for every curated story. LibraryController::MIN_PROMINENT_WORDS
     * only *deprioritizes* short texts on hero surfaces; this gate rejects them
     * outright at import so no story can ship as a sub-200-word single-paragraph
     * blurb that reads as cut off (school-standard bar, 2026-08-28).
     */
    private const MIN_WORDS = 200;

    /**
     * Import every manifest entry (or a given subset - tests pass
     * fixtures here). Returns created/updated/skipped counts plus any
     * failed entries with reasons.
     */
    public static function importAll(?array $entries = null): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => []];

        foreach ($entries ?? self::manifest() as $entry) {
            try {
                $summary[self::importEntry($entry)]++;
            } catch (\Throwable $e) {
                $summary['failed'][] = [
                    'slug' => $entry['slug'] ?? '(unknown)',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        // Self-heal covers: the import only writes cover paths, and the JPGs
        // on the public disk can go missing (delete + re-import cycles, fresh
        // checkouts). Fetch whatever is missing so every import ends with
        // intact covers.
        $covers = CoverFetcher::ensureMissing($entries);
        $summary['covers_fetched'] = $covers['fetched'];
        $summary['covers_failed'] = $covers['failed'];

        return $summary;
    }

    public static function manifest(): array
    {
        return require database_path('seeders/data/library_manifest.php');
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    public static function importEntry(array $entry): string
    {
        self::assertValid($entry);

        $analysis = ReadabilityAnalyzer::analyze($entry['text']);
        $story = self::resolveStory($entry);
        $signature = self::manifestQuizSignature($entry['questions']);
        $difficulty = self::difficultyFor(
            $analysis,
            self::durationTierFor((int) $analysis['word_count']),
            $entry['quiz_difficulty'],
        );

        // Derived fields belong in the change fingerprint: when the banding
        // math evolves, re-importing refreshes stored labels instead of
        // skipping rows whose text never changed. Arrays are compared
        // canonically because MariaDB re-orders JSON object keys on write.
        // A manifest without a `journal` key leaves the story's journal moves
        // to the teacher; with the key, the manifest owns them (like quiz).
        $journalSame = ! array_key_exists('journal', $entry)
            || ($story !== null && self::storedJournalSignature($story) === self::manifestJournalSignature($entry['journal']));

        if ($story !== null
            && $story->content === $entry['text']
            && $story->cover_image === ($entry['cover'] ?? null)
            && self::storedQuizSignature($story) === $signature
            && $journalSame
            && $story->difficulty_label === $difficulty['label']
            && self::canonicalize($story->difficulty_breakdown) === self::canonicalize($difficulty['breakdown'])) {
            // No content changed, but a stale draft still converges to
            // published so re-running the import is self-healing.
            self::ensureCuratedPublished($story);

            return 'skipped';
        }

        $wasCreated = $story === null;

        DB::transaction(function () use ($entry, $analysis, &$story) {
            $fields = self::storyFields($entry, $analysis);

            if ($story === null) {
                $story = Story::create([...$fields, 'status' => StoryStatus::Draft]);
            } else {
                $story->update($fields);
            }

            self::syncGenres($story, $entry['genres']);
            self::writeQuiz($story, $entry['questions']);

            if (array_key_exists('journal', $entry)) {
                self::writeJournal($story, $entry['journal']);
            }
        });

        self::ensureCuratedPublished($story);

        return $wasCreated ? 'created' : 'updated';
    }

    /**
     * Curated books are published on import: every manifest entry fills the
     * completeness gates (title, author, license, content) by construction,
     * so the library lands student-ready instead of sitting as drafts. The
     * side effects mirror StoryController::batchPublish. Deliberate states
     * are respected - an Archived book stays archived and a trashed row is
     * never resurrected.
     *
     * @return bool true when the story was flipped to published
     */
    public static function ensureCuratedPublished(Story $story): bool
    {
        if ($story->trashed()
            || $story->status === StoryStatus::Published
            || $story->status === StoryStatus::Archived
            || ! StoryCompleteness::evaluate($story)->publishable()) {
            return false;
        }

        $story->update(['status' => StoryStatus::Published]);
        StoryVocabService::extractForStory($story);
        LibraryController::forgetGenreCounts();
        Audit::log('story.published', $story);

        return true;
    }

    private function __construct() {}

    /**
     * Recursively key-sort an array so JSON round-trips through MariaDB
     * (which normalizes object-key order) compare equal to fresh builds.
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonicalize(...), $value);
        ksort($value);

        return $value;
    }

    private static function assertValid(array $entry): void
    {
        foreach (['slug', 'gutenberg_id', 'title', 'author', 'text', 'synopsis', 'quiz_difficulty'] as $key) {
            if (empty($entry[$key])) {
                throw new \InvalidArgumentException(static::class.': entry is missing "'.$key.'".');
            }
        }

        $words = ReadabilityAnalyzer::wordCount($entry['text']);
        $maxWords = (int) config('stories.content.max_words');
        $maxChars = (int) config('stories.content.max_chars');

        if ($words < self::MIN_WORDS) {
            throw new \InvalidArgumentException(sprintf(
                '%s: text is only %s words (the school-standard floor is %s).',
                $entry['slug'], number_format($words), number_format(self::MIN_WORDS),
            ));
        }

        if ($words > $maxWords || strlen($entry['text']) > $maxChars) {
            throw new \InvalidArgumentException(sprintf(
                '%s: text is %s words / %s chars (the window is %s words / %s chars).',
                $entry['slug'], number_format($words), number_format(strlen($entry['text'])),
                number_format($maxWords), number_format($maxChars),
            ));
        }

        if (mb_strlen($entry['synopsis']) > self::SYNOPSIS_MAX_CHARS) {
            throw new \InvalidArgumentException($entry['slug'].': synopsis exceeds '.self::SYNOPSIS_MAX_CHARS.' characters.');
        }

        $questionCount = count($entry['questions']);

        if ($questionCount < 5 || $questionCount > 10) {
            throw new \InvalidArgumentException($entry['slug'].": quiz has {$questionCount} questions (the charter window is 5-10).");
        }

        if (! isset(self::QUIZ_POINTS[$entry['quiz_difficulty']])) {
            throw new \InvalidArgumentException($entry['slug'].': quiz_difficulty must be easy, medium or hard.');
        }

        $known = Genre::query()->pluck('name')->map(fn ($name) => mb_strtolower($name));

        foreach ($entry['genres'] as $genre) {
            if (! $known->contains(mb_strtolower($genre))) {
                throw new \InvalidArgumentException($entry['slug'].": unknown genre \"{$genre}\".");
            }
        }
    }

    private static function resolveStory(array $entry): ?Story
    {
        return Story::withTrashed()
            ->where('gutenberg_id', $entry['gutenberg_id'])
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($entry['title'])])
            ->first();
    }

    private static function storyFields(array $entry, array $analysis): array
    {
        $duration = self::durationTierFor((int) $analysis['word_count']);
        $difficulty = self::difficultyFor($analysis, $duration, $entry['quiz_difficulty']);

        return [
            'gutenberg_id' => $entry['gutenberg_id'],
            'title' => $entry['title'],
            'author' => $entry['author'],
            'license' => $entry['license'] ?? 'Public domain',
            'source_url' => 'https://www.gutenberg.org/ebooks/'.$entry['gutenberg_id'],
            'reading_level' => self::gradeForReadingLevel((int) $analysis['reading_level']),
            'word_count' => (int) $analysis['word_count'],
            'synopsis' => $entry['synopsis'],
            'content' => $entry['text'],
            'cover_image' => $entry['cover'] ?? null,
            'duration_tier' => $duration['tier'],
            'quiz_difficulty' => $entry['quiz_difficulty'],
            'difficulty_label' => $difficulty['label'],
            'difficulty_breakdown' => $difficulty['breakdown'],
            'provenance' => self::buildProvenance($entry),
        ];
    }

    private static function durationTierFor(int $words): array
    {
        foreach (self::DURATION_TIERS as $tier => $meta) {
            if ($words <= $meta['max_words']) {
                return ['tier' => $tier, ...$meta];
            }
        }

        return ['tier' => 'long', ...self::DURATION_TIERS['long']];
    }

    /**
     * Recalibrate a raw Flesch-Kincaid grade (1-20) onto the app's 7-10
     * difficulty scale. The library only serves grades 7-10, so simple texts
     * that would score FK <= 6 are floored at grade 7 rather than printing a
     * confusing lower grade. Each raw FK range collapses onto a single
     * recalibrated grade; the honest raw score is still kept in the
     * teacher-facing difficulty_breakdown.
     */
    public static function gradeForReadingLevel(int $fleschKincaid): int
    {
        return match (true) {
            $fleschKincaid <= 6 => 7,
            $fleschKincaid <= 8 => 8,
            $fleschKincaid <= 11 => 9,
            default => 10,
        };
    }

    /**
     * Text-complexity band derived from the recalibrated 7-10 grade alone,
     * so a story's label can never contradict its printed grade. Assignment
     * workload - length and quiz demand - is reported separately and is
     * deliberately NOT folded into this label. Any grade at or below the
     * library floor (unanalyzed rows cast to 0, legacy pre-recalibration
     * rows) resolves to the bottom band 'Easy'.
     */
    public static function difficultyBand(int $grade): string
    {
        return match (true) {
            $grade <= 7 => 'Easy',
            $grade <= 8 => 'Intermediate',
            $grade <= 9 => 'Hard',
            default => 'Very Hard',
        };
    }

    /**
     * The inclusive grade bounds of the band a given grade sits in. On the
     * recalibrated 7-10 scale each grade is its own band, clamped to the
     * app's range so stale out-of-range values still resolve sanely.
     */
    public static function bandFloor(int $grade): int
    {
        return max(7, min(10, $grade));
    }

    public static function bandCeiling(int $grade): int
    {
        return max(7, min(10, $grade));
    }

    /**
     * The stored band for an analyzed text plus a breakdown that records
     * the honest ingredients: the prose measurement, the length facts and
     * the quiz demand. `total` carries the banding input (the recalibrated
     * 7-10 grade); `text.grade` keeps the raw Flesch-Kincaid measurement for
     * teacher diagnostics.
     */
    private static function difficultyFor(array $analysis, array $duration, string $quizDifficulty): array
    {
        $grade = self::gradeForReadingLevel((int) ($analysis['reading_level'] ?? 0));
        $label = self::difficultyBand($grade);

        return [
            'label' => $label,
            'breakdown' => [
                'text' => ['tier' => $analysis['tier'], 'grade' => (int) $analysis['reading_level']],
                'length' => [
                    'tier' => $duration['tier'],
                    'words' => (int) $analysis['word_count'],
                    'minutes' => (int) ceil($analysis['word_count'] / 200),
                    'points' => $duration['points'],
                ],
                'quiz' => ['label' => ucfirst($quizDifficulty)],
                'total' => $grade,
            ],
        ];
    }

    /**
     * The pill palette for a stored difficulty_label band, shared by the
     * shelf column, the details drawer and the edit form so every surface
     * tints the four bands identically. The legacy 'Very Easy' case keeps
     * its tint for pre-recalibration rows until they are re-labeled.
     */
    public static function difficultyClasses(string $label): string
    {
        return match ($label) {
            'Very Easy' => 'bg-emerald-100 text-emerald-700',
            'Easy' => 'bg-sky-100 text-sky-700',
            'Intermediate' => 'bg-amber-100 text-amber-700',
            'Hard' => 'bg-orange-100 text-orange-700',
            default => 'bg-rose-100 text-rose-700',
        };
    }

    private static function buildProvenance(array $entry): string
    {
        $base = sprintf(
            'From the public-domain original: %s by %s (Project Gutenberg #%s). Prepared for Alice with light edits for length, punctuation and readability.',
            $entry['title'], $entry['author'], $entry['gutenberg_id'],
        );

        return isset($entry['provenance_note']) && filled($entry['provenance_note'])
            ? $base.' '.$entry['provenance_note']
            : $base;
    }

    private static function syncGenres(Story $story, array $genres): void
    {
        $byLowercaseName = Genre::query()
            ->get()
            ->mapWithKeys(fn (Genre $genre) => [mb_strtolower($genre->name) => $genre->id]);

        $story->genres()->sync(
            collect($genres)
                ->map(fn ($genre) => $byLowercaseName[mb_strtolower($genre)] ?? null)
                ->filter()
                ->values()
                ->all(),
        );
    }

    private static function writeQuiz(Story $story, array $questions): void
    {
        $story->questions()->delete();
        $story->sections()->delete();

        $sections = [];

        foreach (collect($questions)->pluck('section')->unique()->values() as $index => $name) {
            $sections[$name] = QuestionSection::create([
                'story_id' => $story->id,
                'name' => $name,
                'position' => $index + 1,
            ])->id;
        }

        foreach ($questions as $question) {
            QuestionWriter::create($story, [
                ...$question,
                'section_id' => $sections[$question['section']],
            ]);
        }
    }

    /**
     * Journal moves from the manifest, written through MissionActionWriter
     * (so each move is validated against the live text, like the quiz
     * builder). Replaces the story's moves, mirroring writeQuiz. An infeasible
     * move throws, which fails the whole book with its reason.
     *
     * @param  list<array{rule_type: string, rule_value?: ?string, need?: ?int}>  $specs
     */
    private static function writeJournal(Story $story, array $specs): void
    {
        $story->missionActions()->delete();

        foreach (array_values($specs) as $i => $spec) {
            $normalized = self::normalizeJournalSpec($spec);
            $data = MissionActionWriter::validate($normalized, $story);

            // The source spec rides along in config so re-imports can compare
            // manifest and stored moves with one canonical form.
            $data['config']['spec'] = $normalized;

            $story->missionActions()->create([...$data, 'position' => $i + 1]);
        }

        if ($specs !== []) {
            $story->update(['missions_enabled' => true]);
        }
    }

    /** @return array{rule_type: string, rule_value: ?string, need: ?int} */
    private static function normalizeJournalSpec(array $spec): array
    {
        return [
            'rule_type' => (string) ($spec['rule_type'] ?? ''),
            'rule_value' => isset($spec['rule_value']) ? (string) $spec['rule_value'] : null,
            'need' => isset($spec['need']) ? (int) $spec['need'] : null,
        ];
    }

    /**
     * Both sides go through canonicalize(): MariaDB re-orders JSON keys on
     * write, so raw json_encode would never match a stored spec.
     *
     * @param  list<array>  $specs
     */
    private static function manifestJournalSignature(array $specs): string
    {
        return md5(json_encode(self::canonicalize(array_map(self::normalizeJournalSpec(...), array_values($specs)))));
    }

    private static function storedJournalSignature(Story $story): string
    {
        return md5(json_encode(self::canonicalize($story->missionActions()
            ->orderBy('position')
            ->get()
            ->map(fn ($action) => self::normalizeJournalSpec($action->config['spec'] ?? []))
            ->values()
            ->all())));
    }

    /**
     * Canonical quiz fingerprint on the manifest side: section name,
     * type, text and the type's own fields, in listed order.
     */
    private static function manifestQuizSignature(array $questions): string
    {
        return md5(json_encode(collect($questions)
            ->map(fn ($q) => self::fingerprintRow(
                $q['section'],
                $q['type'],
                $q['question_text'],
                match ($q['type']) {
                    'mcq' => [['option_a', $q['option_a']], ['option_b', $q['option_b']], ['option_c', $q['option_c']], ['option_d', $q['option_d']], ['correct_option', $q['correct_option']]],
                    'true_false' => [['correct_option', $q['correct_option']]],
                    'identification' => [['acceptable_answers', array_values($q['acceptable_answers'])]],
                    'matching' => [['match_pairs', array_values($q['match_pairs'])]],
                    'sequence' => [['sequence_items', array_values($q['sequence_items'])]],
                    default => [],
                },
            ))
            ->all()));
    }

    private static function fingerprintRow(string $section, string $type, string $text, array $payload): array
    {
        return [$section, $type, $text, $payload];
    }

    /**
     * The same fingerprint rebuilt from database rows, so an unchanged
     * book skips and a changed book updates.
     */
    private static function storedQuizSignature(Story $story): string
    {
        $sectionNames = $story->sections()->pluck('name', 'id');

        return md5(json_encode($story->questions()
            ->orderBy('position')
            ->get()
            ->map(fn ($q) => self::fingerprintRow(
                (string) ($sectionNames[$q->section_id] ?? 'Uncategorized'),
                $q->type instanceof \BackedEnum ? $q->type->value : (string) $q->type,
                $q->question_text,
                match ((string) $q->type->value) {
                    'mcq' => [['option_a', $q->option_a], ['option_b', $q->option_b], ['option_c', $q->option_c], ['option_d', $q->option_d], ['correct_option', $q->correct_option]],
                    'true_false' => [['correct_option', $q->correct_option]],
                    'identification' => [['acceptable_answers', array_values((array) $q->acceptable_answers)]],
                    'matching' => [['match_pairs', array_values((array) $q->match_pairs)]],
                    'sequence' => [['sequence_items', array_values((array) $q->sequence_items)]],
                    default => [],
                },
            ))
            ->all()));
    }
}
