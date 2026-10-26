<?php

namespace App\Support;

use App\Enums\StoryProgressStatus;
use App\Models\GameRound;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates the real, per-student data that feeds the student Hub
 * (TODAY / TASKS / STATS / BADGES tabs). Everything here reads existing
 * tables — no synthetic numbers. Charts, the stamp calendar and the stat
 * cards all come from these helpers, so the Hub can never show fake data.
 *
 * The layout independently computes $gamer (title/points/streak/keys) in
 * authenticated.blade.php; reuse PointsRank here for the ladder bar.
 */
class HubStats
{
    private const TICK_KEYS = ['wordsearch', 'match', 'sentence'];

    public const GAME_LABELS = [
        'wordsearch' => 'Word Search',
        'match' => 'Matching',
        'sentence' => 'Sentence Builder',
    ];

    private function __construct() {}

    /**
     * The reader's most recent reads for the hub's "Recently Read" card: the
     * five most recently updated rows with real scroll progress, any status.
     * Completed stories are kept so a fast finisher never sees the card empty —
     * the blade labels them "Read again" instead of "Resume reading". Attaches
     * the aliased attributes LibraryPresentation::percentFromRow() expects.
     *
     * @return Collection<int, array{story: Story, status: StoryProgressStatus, percent: ?int}>
     */
    public static function continueReading(User $user): Collection
    {
        $rows = StoryProgress::query()
            ->where('user_id', $user->id)
            ->where('current_page', '>', 0)
            ->with(['story.genres'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        $continue = collect();

        foreach ($rows as $row) {
            $story = $row->story;

            if ($story === null) {
                continue;
            }

            // percentFromRow reads "current_page"/"progress_completed_at" off
            // the story's raw attributes — mirror the library's aliasing.
            $story->setAttribute('current_page', (int) $row->current_page);
            $story->setAttribute('progress_completed_at', $row->completed_at);

            $continue->push([
                'story' => $story,
                'status' => $row->status,
                'percent' => LibraryPresentation::percentFromRow($story),
            ]);
        }

        return $continue;
    }

    /**
     * Quick snapshot for the TODAY greeting row: economy + today's stamp.
     */
    public static function snapshot(User $user, Carbon $now): array
    {
        $todayRow = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->whereDate('activity_date', $now->toDateString())
            ->first();

        return [
            'saved_words' => SavedWord::query()->where('user_id', $user->id)->count(),
            'stamped_today' => $todayRow?->stamped_at !== null,
            'reading_seconds_today' => (int) ($todayRow?->reading_seconds_today ?? 0),
            'daily_reading_goal' => (int) ($user->daily_reading_goal ?? 0),
            'daily_study_goal' => (int) ($user->daily_study_goal ?? 20),
            'study_reviewed_today' => self::studyReviewedToday($user, $now),
            'reading_goal_rewarded' => $todayRow?->reading_goal_claimed_at !== null,
            'study_goal_rewarded' => $todayRow?->study_goal_claimed_at !== null,
        ];
    }

    /**
     * How many distinct words the student has reviewed (drilled) today —
     * mirrors StudyService::reviewedTodayCount so the hub study goal always
     * matches the Study app's own "reviewed today" counter.
     */
    private static function studyReviewedToday(User $user, Carbon $now): int
    {
        return SavedWord::query()
            ->where('user_id', $user->id)
            ->whereBetween('last_drilled_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->count();
    }

    /**
     * Activity series for the STATS bar chart. Keys an array by date so days
     * without any activity still render as empty bars.
     *
     * `$mode` is a calendar-period token:
     *   - `today`    -> 1 bar (today)
     *   - `thisweek` -> Monday-start of the current calendar week through
     *                   today (variable length: 1 on Monday, 7 on Sunday).
     *                   No ghost "future" bars are emitted.
     *   - `lastweek` -> the full previous Monday-Sunday calendar week (7 bars).
     * Any unknown token falls back to `thisweek`.
     *
     * @return Collection<int, array{date: string, label: string, reading_seconds: int, quizzes: int, games: int, drills: int, stamped: bool}>
     */
    public static function activitySeries(User $user, Carbon $now, string $mode = 'thisweek'): Collection
    {
        $today = $now->copy()->startOfDay();

        [$start, $end] = match ($mode) {
            'today' => [$today->copy(), $today->copy()],
            'lastweek' => [
                $now->copy()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay(),
                $now->copy()->startOfWeek(Carbon::MONDAY)->subDay()->endOfDay(),
            ],
            default => [
                $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $today->copy()->endOfDay(),
            ],
        };

        $rows = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->where('activity_date', '>=', $start->toDateString())
            ->where('activity_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (UserDailyActivity $a) => $a->activity_date->toDateString());

        $series = collect();

        $cursor = $start->copy()->startOfDay();
        while (! $cursor->gt($end->copy()->startOfDay())) {
            $dayKey = $cursor->toDateString();
            $row = $rows->get($dayKey);

            $series->push([
                'date' => $dayKey,
                'label' => $cursor->format('D'),
                'reading_seconds' => (int) ($row?->reading_seconds_today ?? 0),
                'quizzes' => (int) ($row?->quiz_submissions ?? 0),
                'games' => (int) ($row?->game_rounds ?? 0),
                'drills' => (int) ($row?->drills_completed ?? 0),
                'stamped' => $row?->stamped_at !== null,
            ]);

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Reading totals across the user's story progress rows.
     *
     * "finished" mirrors the library's Finished shelf, which shows every story
     * the student read to the end — both scrolled-through (status Read, quiz
     * not passed) and quiz-passed (status Completed).
     */
    public static function readingTotals(User $user): array
    {
        $rows = StoryProgress::query()
            ->where('user_id', $user->id)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS total, SUM(time_spent_seconds) AS seconds, SUM(is_favorite) AS favorites')
            ->get()
            ->keyBy('status');

        $finishedStatuses = [StoryProgressStatus::Read->value, StoryProgressStatus::Completed->value];

        $finished = 0;
        foreach ($finishedStatuses as $status) {
            $finished += (int) ($rows->get($status)?->total ?? 0);
        }

        return [
            'started' => (int) $rows->sum('total'),
            'finished' => $finished,
            'completed' => (int) ($rows->get(StoryProgressStatus::Completed->value)?->total ?? 0),
            'read' => (int) ($rows->get(StoryProgressStatus::Read->value)?->total ?? 0),
            'in_progress' => (int) ($rows->get(StoryProgressStatus::Reading->value)?->total ?? 0),
            'favorites' => (int) $rows->sum('favorites'),
            'total_seconds' => (int) $rows->sum('seconds'),
        ];
    }

    /**
     * Overall quiz accuracy from real attempts (correct vs answered) plus the
     * best score achieved on each distinct story.
     */
    public static function quizAccuracy(User $user): array
    {
        $summary = TestAttempt::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) AS attempts, SUM(passed) AS passed, COALESCE(SUM(correct_count), 0) AS correct, COALESCE(SUM(wrong_count), 0) AS wrong')
            ->first();

        $attempts = (int) $summary->attempts;
        $correct = (int) $summary->correct;
        $wrong = (int) $summary->wrong;
        $answered = $correct + $wrong;

        $bestScores = TestAttempt::query()
            ->where('user_id', $user->id)
            ->groupBy('story_id')
            ->selectRaw('story_id, MAX(score_pct) AS best')
            ->get()
            ->pluck('best', 'story_id')
            ->filter(fn ($v) => $v !== null);

        return [
            'attempts' => $attempts,
            'passed' => (int) $summary->passed,
            'correct' => $correct,
            'wrong' => $wrong,
            'accuracy' => $answered > 0 ? (int) round($correct / $answered * 100) : null,
            'best_scores' => $bestScores,
        ];
    }

    /**
     * Average quiz score across the student's scored attempts. Reads the same
     * `test_attempts` source as quizAccuracy() so the metrics panel stays
     * consistent with the existing Quiz Accuracy card. Attempts without a
     * stored score are ignored (MySQL AVG skips NULL) and a student with no
     * scored attempt yields `null` — never a fabricated 0%.
     */
    public static function avgQuizScore(User $user): ?int
    {
        $avg = TestAttempt::query()
            ->where('user_id', $user->id)
            ->whereNotNull('score_pct')
            ->avg('score_pct');

        return $avg === null ? null : (int) round((float) $avg);
    }

    /**
     * Best score per mini-game type (reuses the mini-game hub's MAX query).
     */
    public static function gameBests(User $user): array
    {
        return GameRound::query()
            ->where('user_id', $user->id)
            ->whereIn('game_type', self::TICK_KEYS)
            ->selectRaw('game_type, MAX(score) as best')
            ->groupBy('game_type')
            ->pluck('best', 'game_type')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Lifetime accomplishments shown alongside reading totals: how many stamps
     * the student has earned and their longest streak ever recorded.
     */
    public static function lifetime(User $user): array
    {
        return [
            'stamps' => (int) UserDailyActivity::query()
                ->where('user_id', $user->id)
                ->whereNotNull('stamped_at')
                ->count(),
            'best_streak' => (int) ($user->longest_streak ?? 0),
        ];
    }

    /**
     * Monthly activity series for the long-run line graph: one point per month
     * (oldest first, through the current month) with the per-activity totals
     * (reading minutes, quizzes, games, drills) and a combined `overall`
     * volume. Days/months without any row still render as a zero data point,
     * so the timeline is continuous from first activity to now.
     *
     * `overall` reuses the established hub "activity" metric
     * `round(reading_minutes) + quizzes + games + drills` (same units the old
     * single-activity bar used), so its Y-axis meaning is consistent, and the
     * hover tooltip can break each month into its per-category parts.
     *
     * @return array<int, array{month_key: string, month: string, reading_minutes: int, quizzes: int, games: int, drills: int, overall: int, stamps: int}>
     */
    public static function monthlySeries(User $user, Carbon $now): array
    {
        $earliest = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->min('activity_date');

        $cursor = $earliest !== null
            ? Carbon::parse($earliest)->startOfMonth()
            : $now->copy()->startOfMonth();

        $end = $now->copy()->startOfMonth();

        $rows = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->whereNotNull('activity_date')
            ->groupBy('month_key')
            ->selectRaw('DATE_FORMAT(activity_date, "%Y-%m") AS month_key, SUM(reading_seconds_today) AS reading_seconds, SUM(quiz_submissions) AS quizzes, SUM(game_rounds) AS games, SUM(drills_completed) AS drills, COUNT(stamped_at) AS stamps')
            ->get()
            ->keyBy('month_key');

        $series = [];

        while (! $cursor->gt($end)) {
            $key = $cursor->format('Y-m');
            $monthRow = $rows->get($key);

            $readingMinutes = (int) round((int) ($monthRow?->reading_seconds ?? 0) / 60);
            $quizzes = (int) ($monthRow?->quizzes ?? 0);
            $games = (int) ($monthRow?->games ?? 0);
            $drills = (int) ($monthRow?->drills ?? 0);
            $stamps = (int) ($monthRow?->stamps ?? 0);

            $series[] = [
                'month_key' => $key,
                'month' => $cursor->format('M Y'),
                'reading_minutes' => $readingMinutes,
                'quizzes' => $quizzes,
                'games' => $games,
                'drills' => $drills,
                'overall' => $readingMinutes + $quizzes + $games + $drills,
                'stamps' => $stamps,
            ];

            $cursor->addMonth();
        }

        return $series;
    }

    /**
     * The current PointsRank (title ladder position) for the STATS ladder bar.
     */
    public static function rank(User $user): array
    {
        return PointsRank::for($user);
    }

    /**
     * A retro-DS stamp calendar grid for a given month: which cells are in the
     * past/present/future and whether the student earned a stamp on each day.
     * Days with activity but no stamp are shown as an outline mark; genuinely
     * future days are empty. Stamped days carry a green intensity tier (busier
     * days are darker) plus a per-day activity detail payload so the grid can
     * show what the student actually did when a cell is tapped.
     *
     * @return array{year: int, month: int, month_name: string, weeks: array<int, array<int, array{day: int, state: string, detail: ?array}>>, stamped_count: int, stamped_days_in_month: int}
     */
    public static function calendar(User $user, Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $daysInMonth = $month->daysInMonth;
        $firstDow = (int) $month->dayOfWeek; // 0 = Sunday

        $rows = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->where('activity_date', '>=', $month->toDateString())
            ->where('activity_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->get()
            ->keyBy(fn (UserDailyActivity $row) => $row->activity_date->toDateString());

        $today = Carbon::today()->toDateString();

        $weeks = [];
        $week = [];
        $stampedCount = 0;

        for ($i = 0; $i < $firstDow; $i++) {
            $week[] = ['day' => 0, 'state' => 'pad'];
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $month->copy()->day($day)->toDateString();
            $state = 'future';
            $detail = null;

            if ($date <= $today) {
                $row = $rows->get($date);

                if ($row !== null) {
                    $detail = self::calendarDayDetail($row);
                }

                if ($row?->stamped_at !== null) {
                    $state = 'stamped';
                    $stampedCount++;
                } elseif ($row !== null) {
                    $state = 'active';
                } else {
                    $state = $date === $today ? 'today' : 'past';
                }
            }

            $week[] = ['day' => $day, 'state' => $state, 'detail' => $detail];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if ($week !== []) {
            while (count($week) < 7) {
                $week[] = ['day' => 0, 'state' => 'pad'];
            }
            $weeks[] = $week;
        }

        return [
            'year' => (int) $month->year,
            'month' => (int) $month->month,
            'month_name' => $month->format('F'),
            'weeks' => $weeks,
            'stamped_count' => $stampedCount,
            'stamped_days_in_month' => $daysInMonth,
        ];
    }

    /**
     * Per-day activity breakdown for one calendar cell, plus the green
     * intensity tier (1 = lightest .. 4 = darkest) derived from total actions.
     */
    private static function calendarDayDetail(UserDailyActivity $row): array
    {
        $score = $row->totalActions();

        return [
            'reads' => (int) $row->read_sessions,
            'quizzes' => (int) $row->quiz_submissions,
            'drills' => (int) $row->drills_completed,
            'games' => (int) $row->game_rounds,
            'minutes' => (int) round($row->reading_seconds_today / 60),
            'stamped_at' => $row->stamped_at?->format('g:i A'),
            'score' => $score,
            'tier' => match (true) {
                $score >= 7 => 4,
                $score >= 4 => 3,
                $score >= 2 => 2,
                default => 1,
            },
        ];
    }
}
