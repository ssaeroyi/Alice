<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\TestAttempt;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Background thinking-level analytics (Bloom's taxonomy, revised levels).
 *
 * Every quiz question counts as one cognitive level off its type — no
 * tagging, no migration, nothing for anyone to set up. Attempt answers
 * already store per-question earned/worth, so accuracy per level is a
 * join away. Evaluate and Create are not measured: they need
 * open-response questions, which quizzes do not have.
 */
class BloomLevels
{
    /** A student is "strong" at a level at or above this accuracy. */
    public const STRONG_THRESHOLD = 70;

    /** Levels in pyramid order, bottom (foundational) to top. */
    public const ORDER = ['remember', 'understand', 'apply', 'analyze'];

    public static function label(string $level): string
    {
        return match ($level) {
            'remember' => 'Remember',
            'understand' => 'Understand',
            'apply' => 'Apply',
            'analyze' => 'Analyze',
            default => $level,
        };
    }

    public static function description(string $level): string
    {
        return match ($level) {
            'remember' => 'Recall facts and basic concepts.',
            'understand' => 'Explain ideas or concepts.',
            'apply' => 'Use information in new situations.',
            'analyze' => 'Draw connections among ideas.',
            default => '',
        };
    }

    public static function forQuestionType(QuestionType $type): ?string
    {
        return match ($type) {
            QuestionType::Identification => 'remember',
            QuestionType::Mcq, QuestionType::TrueFalse => 'understand',
            QuestionType::Matching => 'apply',
            QuestionType::Sequence => 'analyze',
        };
    }

    /**
     * Class-wide share of students strong at each level inside the window.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<string, array{pct: int, strong: int, scored: int}>
     */
    public static function classDistribution(Collection $userIds, Carbon $from, Carbon $to): array
    {
        $byUser = self::earningsByUser($userIds, $from, $to);

        $result = [];

        foreach (self::ORDER as $level) {
            $scored = 0;
            $strong = 0;

            foreach ($byUser as $earnings) {
                $worth = $earnings[$level]['worth'] ?? 0.0;

                if ($worth <= 0) {
                    continue;
                }

                $scored++;

                if ($earnings[$level]['earned'] / $worth * 100 >= self::STRONG_THRESHOLD) {
                    $strong++;
                }
            }

            $result[$level] = [
                'scored' => $scored,
                'strong' => $strong,
                'pct' => $scored > 0 ? (int) round($strong / $scored * 100) : 0,
            ];
        }

        return $result;
    }

    /**
     * One student's accuracy per level. Only levels with real answers show.
     *
     * @return array<string, array{pct: float, earned: float, worth: float}>
     */
    public static function userAccuracy(int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $byUser = self::earningsByUser(
            collect([$userId]),
            $from ?? now()->subYears(3)->startOfDay(),
            $to ?? now()->endOfDay(),
        );

        $earnings = $byUser[$userId] ?? [];
        $result = [];

        foreach (self::ORDER as $level) {
            $worth = $earnings[$level]['worth'] ?? 0.0;

            if ($worth <= 0) {
                continue;
            }

            $result[$level] = [
                'earned' => $earnings[$level]['earned'],
                'worth' => $worth,
                'pct' => round($earnings[$level]['earned'] / $worth * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * Student ids strong at a level (band=strong) or still building it
     * (band=practice: real answers, under the threshold). Drives the
     * roster's thinking-level filter.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, int>
     */
    public static function userIdsAtLevel(string $level, string $band, Collection $userIds, Carbon $from, Carbon $to): Collection
    {
        $byUser = self::earningsByUser($userIds, $from, $to);
        $matches = collect();

        foreach ($byUser as $userId => $earnings) {
            $worth = $earnings[$level]['worth'] ?? 0.0;

            if ($worth <= 0) {
                continue;
            }

            $strong = $earnings[$level]['earned'] / $worth * 100 >= self::STRONG_THRESHOLD;

            if (($band === 'strong' && $strong) || ($band === 'practice' && ! $strong)) {
                $matches->push((int) $userId);
            }
        }

        return $matches;
    }

    /**
     * Per user, per level, summed earned/worth across every attempt in the
     * window. Answers live in answers_json, so the roll-up happens in PHP:
     * school rosters are small, and it keeps MariaDB version risk out.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, array<string, array{earned: float, worth: float}>>
     */
    private static function earningsByUser(Collection $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        $attempts = TestAttempt::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->get(['user_id', 'answers_json']);

        $questionIds = [];

        foreach ($attempts as $attempt) {
            foreach (($attempt->answers_json['items'] ?? []) as $item) {
                if (isset($item['question_id'])) {
                    $questionIds[] = (int) $item['question_id'];
                }
            }
        }

        $questionIds = array_values(array_unique($questionIds));

        if ($questionIds === []) {
            return [];
        }

        $levelByQuestion = Question::query()
            ->whereIn('id', $questionIds)
            ->get(['id', 'type'])
            ->mapWithKeys(fn (Question $question) => [
                $question->id => self::forQuestionType($question->type),
            ])
            ->all();

        $byUser = [];

        foreach ($attempts as $attempt) {
            $userId = (int) $attempt->user_id;

            foreach (($attempt->answers_json['items'] ?? []) as $item) {
                $level = $levelByQuestion[(int) ($item['question_id'] ?? 0)] ?? null;

                if ($level === null) {
                    continue;
                }

                $byUser[$userId][$level]['earned'] = ($byUser[$userId][$level]['earned'] ?? 0.0) + (float) ($item['earned'] ?? 0);
                $byUser[$userId][$level]['worth'] = ($byUser[$userId][$level]['worth'] ?? 0.0) + (float) ($item['worth'] ?? 0);
            }
        }

        return $byUser;
    }
}
