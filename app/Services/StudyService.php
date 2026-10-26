<?php

namespace App\Services;

use App\Models\SavedWord;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Anki-style review scheduler for saved words (STUDY_PAGES_PLAN §4.6).
 *
 * mastery_level drives both the display tier and the interval ladder index:
 * 0 = Not Studied, 1-2 = Learning, 3-4 = Strong (config('study.learning_level')).
 * Grading is currency-free — flashcard self-grading never touches
 * RewardService (established B4 rule).
 */
class StudyService
{
    /** Grades the flashcard UI may send. */
    public const GRADES = ['again', 'hard', 'good', 'easy'];

    /** Grade name => column index in the interval matrix. */
    private const GRADE_INDEX = [
        'again' => 0,
        'hard' => 1,
        'good' => 2,
        'easy' => 3,
    ];

    public function tierFor(int $masteryLevel): string
    {
        return match (true) {
            $masteryLevel <= 0 => 'not-studied',
            $masteryLevel < (int) config('study.learning_level') => 'learning',
            default => 'strong',
        };
    }

    /** Status chip text for a word row / entry header. */
    public function statusFor(SavedWord $word): string
    {
        if ($word->last_drilled_at === null) {
            return 'New';
        }

        if ($this->isDue($word)) {
            return 'Due now';
        }

        return match ($this->tierFor($word->mastery_level)) {
            'strong' => 'Strong',
            'learning' => 'Learning',
            default => 'Not studied',
        };
    }

    public function isDue(SavedWord $word): bool
    {
        return $word->last_drilled_at !== null
            && ($word->due_at === null || $word->due_at->lte(now()));
    }

    /**
     * Today's review queue: every word that is new or due, weakest first,
     * then oldest-due first, capped at the effective daily batch size.
     */
    public function queueFor(User $user): Collection
    {
        return SavedWord::query()
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereNull('last_drilled_at')
                ->orWhere(fn ($due) => $due
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', now())))
            ->orderByRaw('CASE WHEN last_drilled_at IS NULL THEN 0 ELSE mastery_level END ASC')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit($this->batchSizeFor($user))
            ->get();
    }

    public function dueCount(User $user): int
    {
        return SavedWord::query()
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereNull('last_drilled_at')
                ->orWhere(fn ($due) => $due
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', now())))
            ->count();
    }

    public function reviewedTodayCount(User $user): int
    {
        return SavedWord::query()
            ->where('user_id', $user->id)
            ->whereBetween('last_drilled_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    /** Tier => count map plus total, for the dashboard donut. */
    public function statsFor(User $user): array
    {
        $counts = SavedWord::query()
            ->where('user_id', $user->id)
            ->selectRaw('mastery_level, COUNT(*) AS total')
            ->groupBy('mastery_level')
            ->pluck('total', 'mastery_level');

        $strong = 0;
        $learning = 0;
        $new = 0;

        foreach ($counts as $level => $total) {
            match ($this->tierFor((int) $level)) {
                'strong' => $strong += (int) $total,
                'learning' => $learning += (int) $total,
                default => $new += (int) $total,
            };
        }

        return [
            'strong' => $strong,
            'learning' => $learning,
            'not_studied' => $new,
            'total' => $strong + $learning + $new,
        ];
    }

    /** Words failed at least once that have not reached Strong yet. */
    public function forgottenQuery(User $user)
    {
        return SavedWord::query()
            ->where('user_id', $user->id)
            ->where('times_wrong', '>', 0)
            ->where('mastery_level', '<', (int) config('study.learning_level'));
    }

    public function forgottenCount(User $user): int
    {
        return (int) $this->forgottenQuery($user)->count();
    }

    /**
     * Apply an Anki-style flashcard grade.
     *
     * Again => reset to 0, due now.
     * Hard  => stay or advance +1, short interval.
     * Good  => advance +1, normal interval.
     * Easy  => advance +2, long interval.
     *
     * @return SavedWord The refreshed word.
     */
    public function applyGrade(SavedWord $word, string $grade): SavedWord
    {
        $currentLevel = $word->mastery_level;
        $index = self::GRADE_INDEX[$grade];

        $masteryResult = (array) config('study.mastery_result');
        $next = $masteryResult[$currentLevel][$index];

        $intervals = (array) config('study.intervals');
        $interval = (int) $intervals[$currentLevel][$index];

        $word->fill([
            'mastery_level' => $next,
            'times_correct' => $grade === 'again' ? $word->times_correct : $word->times_correct + 1,
            'times_wrong' => $grade === 'again' ? $word->times_wrong + 1 : $word->times_wrong,
            'last_drilled_at' => now(),
            'due_at' => $interval === 0 ? now() : now()->addDays($interval),
        ])->save();

        return $word;
    }

    /**
     * Effective queue size: the daily goal, or one bonus batch when the
     * goal has already been met today.
     */
    public function batchSizeFor(User $user): int
    {
        $goal = $this->goalFor($user);
        $reviewed = $this->reviewedTodayCount($user);

        if ($reviewed >= $goal) {
            return $goal; // bonus round
        }

        return max(0, $goal - $reviewed);
    }

    public function goalFor(User $user): int
    {
        return max(
            (int) config('study.min_goal'),
            min((int) config('study.max_goal'), (int) ($user->daily_study_goal ?? config('study.default_goal'))),
        );
    }

    /** Deterministic encouragement line per user per day. */
    public function encouragementFor(User $user): string
    {
        $lines = config('study.encouragements');
        $seed = crc32($user->id.'|'.now()->format('Y-m-d'));

        return $lines[$seed % count($lines)];
    }

    /** Session-end tallies from a graded batch. */
    public function summarize(Collection $graded): array
    {
        return [
            'good' => $graded->filter(fn ($g) => in_array($g['grade'], ['good', 'easy']))->count(),
            'again' => $graded->filter(fn ($g) => $g['grade'] === 'again')->count(),
        ];
    }
}
