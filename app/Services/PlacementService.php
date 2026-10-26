<?php

namespace App\Services;

use App\Enums\ComprehensionLevel;
use App\Enums\PlacementAttemptStatus;
use App\Enums\PlacementStep;
use App\Enums\StoryStatus;
use App\Models\PlacementAttempt;
use App\Models\PlacementSet;
use App\Models\Story;
use App\Support\AliceLibrary;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class PlacementService
{
    /**
     * Score a completed attempt from its saved responses.
     *
     * @return array{vocab: array{correct: int, total: int}, cloze: array{correct: int, total: int}, comprehension: array{correct: int, total: int}, correct: int, total: int, percentage: int, level: ComprehensionLevel}
     */
    public function score(PlacementAttempt $attempt): array
    {
        $content = $attempt->set->content;

        $vocab = $this->scoreItems($content['vocab'], $this->payloadFor($attempt, PlacementStep::Vocab));
        $cloze = $this->scoreItems($content['cloze']['blanks'], $this->payloadFor($attempt, PlacementStep::Cloze));
        $comprehension = $this->scoreItems($content['comprehension']['questions'], $this->payloadFor($attempt, PlacementStep::Comprehension));

        $correct = $vocab['correct'] + $cloze['correct'] + $comprehension['correct'];
        $total = $vocab['total'] + $cloze['total'] + $comprehension['total'];

        $percentage = $total > 0 ? (int) round($correct / $total * 100) : 0;

        return [
            'vocab' => $vocab,
            'cloze' => $cloze,
            'comprehension' => $comprehension,
            'correct' => $correct,
            'total' => $total,
            'percentage' => $percentage,
            'level' => $this->levelFor($percentage),
        ];
    }

    /**
     * Return the score for a completed attempt: the completion-time snapshot
     * when present, otherwise a fresh recompute (pre-snapshot attempts).
     */
    public function scoreOf(PlacementAttempt $attempt): array
    {
        $snapshot = $attempt->score;

        if ($snapshot === null) {
            return $this->score($attempt);
        }

        $snapshot['level'] = ComprehensionLevel::from($snapshot['level']);

        return $snapshot;
    }

    /**
     * Score and finalize the attempt: mark it complete, freeze the score
     * snapshot, write the results to the user (level, bar, setup-complete),
     * and on the very first completion queue the claimable starter reward
     * notification (keys only, paid when the student taps CLAIM) — all in a
     * single transaction.
     *
     * The notification (config placement.welcome_kit -> NotificationService::
     * welcomeKitReward) is only dispatched on the actual first completion —
     * the guards elsewhere in the wizard keep `complete` from ever firing
     * twice, and this false->true flip adds a defensive idempotency check on
     * top.
     */
    public function complete(PlacementAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $result = $this->score($attempt);

            $attempt->update([
                'status' => PlacementAttemptStatus::Completed,
                'completed_at' => now(),
                'score' => $result,
            ]);

            $user = $attempt->user;
            $grade = (int) ($user->grade_level?->value ?? 0);
            $offset = (int) config('placement.reading_level_offsets.'.$result['level']->value);
            $readingLevel = max((int) config('placement.reading_level_floor'), $grade - $offset);

            $wasSetupComplete = (bool) $user->is_setup_complete;

            $user->update([
                'comprehension_level' => $result['level'],
                'comprehension_bar' => $result['percentage'],
                'reading_level' => $readingLevel,
                'is_setup_complete' => true,
            ]);

            if (! $wasSetupComplete) {
                NotificationService::welcomeKitReward($user);
            }
        });
    }

    private function scoreItems(array $items, array $payload): array
    {
        $correct = 0;

        foreach ($items as $i => $item) {
            if (($payload[$i + 1] ?? null) === $item['answer']) {
                $correct++;
            }
        }

        return [
            'correct' => $correct,
            'total' => count($items),
        ];
    }

    private function payloadFor(PlacementAttempt $attempt, PlacementStep $step): array
    {
        $response = $attempt->responses()->where('step', $step->value)->first();

        return $response?->payload ?? [];
    }

    private function levelFor(int $percentage): ComprehensionLevel
    {
        if ($percentage >= config('placement.independent_threshold')) {
            return ComprehensionLevel::Independent;
        }

        if ($percentage >= config('placement.instructional_threshold')) {
            return ComprehensionLevel::Instructional;
        }

        if ($percentage >= config('placement.frustration_threshold')) {
            return ComprehensionLevel::Frustration;
        }

        return ComprehensionLevel::Critical;
    }

    /**
     * Pure placement preview for the admin dev tool: score a set against an
     * idealized answer pattern and derive what the student result screen would
     * have shown — WITHOUT touching the database (zero attempts, users, ledger
     * rows, or reward notifications). This is the same scoring, offsets, floor
     * and recommendation query the real flow uses, but pointed at fabricated
     * payloads.
     *
     * Scenarios: perfect 100% -> Independent, strong 90% -> Independent,
     * steady 76% -> Instructional, shaky 60% -> Frustration, rough 40% ->
     * Critical. Every section gets the same target fraction; the overall
     * percentage is the real weighted mean of the set's composition.
     *
     * @return array{
     *   set: array{id: int, title: string},
     *   scenario: string,
     *   grade: int,
     *   result: array{vocab: array{correct:int,total:int}, cloze: array{correct:int,total:int}, comprehension: array{correct:int,total:int}, correct:int,total:int,percentage:int,level:ComprehensionLevel},
     *   review: array,
     *   reading_level: int,
     *   served_grade: int,
     *   diagnosed_below_floor: bool,
     *   offset: int,
     *   goal_minutes: int,
     *   band_label: string,
     *   recommendations: iterable<Story>,
     *   motivation: string,
     * }
     */
    public function preview(PlacementSet $set, string $scenario, int $grade): array
    {
        $fractions = [
            'perfect' => 1.0,
            'strong' => 0.9,
            'steady' => 0.76,
            'shaky' => 0.6,
            'rough' => 0.4,
        ];

        $fraction = $fractions[$scenario] ?? 0.76;
        $content = $set->content;

        $payloads = [
            'vocab' => $this->simulateAnswers($content['vocab'], $fraction),
            'cloze' => $this->simulateAnswers($content['cloze']['blanks'], $fraction),
            'comprehension' => $this->simulateAnswers($content['comprehension']['questions'], $fraction),
        ];

        $vocab = $this->scoreItems($content['vocab'], $payloads['vocab']);
        $cloze = $this->scoreItems($content['cloze']['blanks'], $payloads['cloze']);
        $comprehension = $this->scoreItems($content['comprehension']['questions'], $payloads['comprehension']);

        $correct = $vocab['correct'] + $cloze['correct'] + $comprehension['correct'];
        $total = $vocab['total'] + $cloze['total'] + $comprehension['total'];
        $percentage = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $level = $this->levelFor($percentage);

        $offset = (int) config('placement.reading_level_offsets.'.$level->value);
        $diagnosed = $grade - $offset;
        $readingLevel = max((int) config('placement.reading_level_floor'), $diagnosed);
        $floor = (int) config('placement.reading_level_floor');
        $serveGrade = min(10, max($floor, $readingLevel));

        return [
            'set' => ['id' => $set->id, 'title' => $set->title],
            'scenario' => $scenario,
            'grade' => $grade,
            'result' => [
                'vocab' => $vocab,
                'cloze' => $cloze,
                'comprehension' => $comprehension,
                'correct' => $correct,
                'total' => $total,
                'percentage' => $percentage,
                'level' => $level,
            ],
            'review' => $this->reviewOf($content, $payloads),
            'reading_level' => $readingLevel,
            'served_grade' => $serveGrade,
            'diagnosed_below_floor' => $grade > 0 && $grade < $floor,
            'offset' => $offset,
            'goal_minutes' => (int) config('placement.reading_goal_options.0'),
            'band_label' => AliceLibrary::difficultyBand($serveGrade),
            'recommendations' => Story::query()
                ->where('status', StoryStatus::Published->value)
                ->where('reading_level', $serveGrade)
                ->orderBy('word_count')
                ->limit(3)
                ->get(['id', 'slug', 'title', 'word_count']),
            'motivation' => $level->motivation('preview-'.$set->id.'-'.$scenario),
        ];
    }

    /**
     * Fabricate a payload where the first round(fraction × count) items are
     * answered correctly and the rest are deliberately wrong — the "what if a
     * student got N% in this section" simulation.
     */
    private function simulateAnswers(array $items, float $fraction): array
    {
        $correctCount = (int) round(count($items) * $fraction);
        $payload = [];

        foreach ($items as $i => $item) {
            $payload[$i + 1] = $i < $correctCount
                ? $item['answer']
                : $this->wrongOption($item);
        }

        return $payload;
    }

    private function wrongOption(array $item): string
    {
        foreach ($item['options'] as $option) {
            if ($option !== $item['answer']) {
                return $option;
            }
        }

        return $item['answer'];
    }

    /**
     * Shape the per-item review exactly like the placement controller does for
     * the result screen (authored order, text comparisons).
     */
    private function reviewOf(array $content, array $payloads): array
    {
        $sections = [
            'vocab' => $content['vocab'],
            'cloze' => $content['cloze']['blanks'],
            'comprehension' => $content['comprehension']['questions'],
        ];
        $review = [];

        foreach ($sections as $key => $items) {
            $payload = $payloads[$key] ?? [];

            foreach ($items as $i => $item) {
                $answer = $payload[$i + 1] ?? null;

                $review[$key][] = [
                    'question' => match ($key) {
                        'vocab' => $item['word'],
                        'cloze' => 'Blank '.$item['key'],
                        default => $item['prompt'],
                    },
                    'answer' => $answer,
                    'expected' => $item['answer'],
                    'correct' => $answer === $item['answer'],
                ];
            }
        }

        return $review;
    }
}
