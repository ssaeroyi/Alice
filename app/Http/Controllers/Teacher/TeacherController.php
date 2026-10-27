<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\ReadingProgress;
use App\Models\SavedWord;
use App\Models\StoryBattle;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Support\BloomLevels;
use App\Support\DashboardRange;
use App\Support\IntegritySignals;
use App\Support\SchoolYear;
use App\Support\StudentActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherController extends Controller
{
    /**
     * Class-level snapshot for teachers: who the students are, where they
     * are level-wise, and who needs attention. Everything here is derived
     * from data students already produce — no admin powers involved.
     *
     * The page carries one shared date range (DashboardRange) that feeds the
     * explorer tiles and the skill-mastery panel; everything else is
     * lifetime or point-in-time counts.
     */
    public function index(Request $request): View
    {
        $range = DashboardRange::fromRequest($request);
        $students = static::studentQuery();

        $requestedDays = (int) $request->query('days', DashboardRange::DEFAULT_DAYS);
        $activePreset = $request->filled('from')
            ? null
            : (in_array($requestedDays, DashboardRange::PRESETS, true) ? $requestedDays : DashboardRange::DEFAULT_DAYS);

        $activeMetric = (string) $request->query('metric', 'quiz.avg_score');
        if (! array_key_exists($activeMetric, static::explorerMetrics())) {
            $activeMetric = 'quiz.avg_score';
        }

        $metricMeta = static::explorerMetrics()[$activeMetric];
        $studentIds = (clone $students)->pluck('id');

        $thinkingGrade = $request->string('thinking_grade')->toString();
        $thinking = static::thinkingDistribution($studentIds, $range, $thinkingGrade);

        $counts = [
            'students' => (clone $students)->count(),
            'setup_done_students' => (clone $students)->where('is_setup_complete', true)->count(),
            'active_week' => (clone $students)->where('last_login_at', '>=', now()->startOfWeek())->count(),
            'suspended_students' => (clone $students)->whereNotNull('suspended_at')->count(),
        ];

        $assignmentCounts = [
            'active' => static::activeAssignmentsQuery()->count(),
            'due_week' => (clone static::activeAssignmentsQuery())
                ->whereNotNull('due_at')
                ->whereBetween('due_at', [now(), now()->addDays(7)])
                ->count(),
            'overdue' => (clone static::activeAssignmentsQuery())
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];

        return view('teacher.overview', [
            'counts' => $counts,
            'range' => $range,
            'activePreset' => $activePreset,
            'activeMetric' => $activeMetric,
            'metricMeta' => $metricMeta,
            'metricSeries' => static::seriesFor($activeMetric, $range, $studentIds),
            'metrics' => collect(static::explorerMetrics())
                ->map(fn (array $meta, string $key) => $meta + [
                    'key' => $key,
                    'headline' => static::headlineFor($key, static::seriesFor($key, $range, $studentIds)),
                ])
                ->all(),
            'tileBase' => $request->query(),
            'thinking' => $thinking,
            'thinkingGrade' => $thinkingGrade,
            'gradeBreakdown' => static::gradeBreakdown(),
            'comprehensionLevels' => static::comprehensionDistribution(),
            'placementOnlyCount' => static::placementOnlyCount(),
            'growthTrends' => static::growthTrends($studentIds),
            'textDifficulty' => static::textDifficulty($studentIds),
            'struggling' => static::struggling(),
            'activeAssignments' => static::activeAssignmentsQuery()
                ->with('group')
                ->whereNotNull('group_id')
                ->latest()
                ->limit(5)
                ->get(),
            'assignmentCounts' => $assignmentCounts,
            'classes' => Group::query()
                ->where('teacher_id', auth()->id())
                ->withCount('members')
                ->withCount(['assignments as active_assignments_count' => fn ($q) => $q->where('status', 'active')])
                ->latest()
                ->get(),
            'recentActive' => (clone $students)->with('readingProgress')->whereNotNull('last_login_at')->latest('last_login_at')->limit(6)->get(),
            'recentJoins' => (clone $students)->with('readingProgress')->latest('created_at')->limit(6)->get(),
        ]);
    }

    /**
     * One explorer metric as JSON for the dashboard's tile clicks. Reads the
     * same shared date range as the page, so the chart a tile draws always
     * matches the window the teacher picked.
     */
    public function series(Request $request): JsonResponse
    {
        $metric = (string) $request->query('metric', '');
        $metrics = static::explorerMetrics();

        if (! array_key_exists($metric, $metrics)) {
            return response()->json(['message' => 'Unknown metric.'], 422);
        }

        $range = DashboardRange::fromRequest($request);
        $meta = $metrics[$metric];
        $studentIds = static::studentQuery()->pluck('id');
        $series = static::seriesFor($metric, $range, $studentIds);

        return response()->json([
            'metric' => $metric,
            'label' => $meta['label'],
            'unit' => $meta['unit'],
            'hint' => $meta['hint'],
            'precision' => $meta['precision'],
            'zero' => $meta['kind'] !== 'avg',
            'chart' => $meta['kind'] === 'avg' ? 'line' : 'bar',
            'headline' => static::headlineFor($metric, $series),
            'subtitle' => $meta['label'].' per '.$range->bucketNoun().', '.$range->humanWindow().'.',
            'detail_url' => $meta['detail_url'],
            'detail_label' => $meta['detail_label'],
            'points' => $series,
        ]);
    }

    /**
     * The teacher's own active assignments — the base query used by the
     * overview counts and the active-assignment list.
     *
     * @return Builder<Assignment>
     */
    private static function activeAssignmentsQuery(): Builder
    {
        return Assignment::query()->where('teacher_id', auth()->id())->where('status', 'active');
    }

    /**
     * The explorer tile map. Keys double as URL whitelist for the series
     * endpoint, so tile links and JSON can never drift apart.
     *
     * @return array<string, array{label: string, unit: string, hint: string, icon: string, kind: string, precision: int, detail_url: ?string, detail_label: ?string}>
     */
    private static function explorerMetrics(): array
    {
        return [
            'quiz.avg_score' => [
                'label' => 'Avg quiz score',
                'unit' => '%',
                'hint' => 'This is the average score on every quiz attempt taken that day. It does not count placement checks, only story quizzes.',
                'icon' => 'chart-line',
                'kind' => 'avg',
                'precision' => 1,
                'detail_url' => null,
                'detail_label' => null,
            ],
            'assignments.done' => [
                'label' => 'Assignments done',
                'unit' => 'assignments',
                'hint' => 'This is the stories students finished each day for your active assignments. It does count one finish per student per assigned story.',
                'icon' => 'academic-cap',
                'kind' => 'sum',
                'precision' => 0,
                'detail_url' => route('teacher.assignments.index'),
                'detail_label' => 'See all assignments',
            ],
            'stories.completed' => [
                'label' => 'Stories completed',
                'unit' => 'stories',
                'hint' => 'This is the stories marked finished that day. It does count each student finishing a story once, never re-reads.',
                'icon' => 'book',
                'kind' => 'sum',
                'precision' => 0,
                'detail_url' => route('teacher.stories.index'),
                'detail_label' => 'Browse the story list',
            ],
            'students.active' => [
                'label' => 'Active students',
                'unit' => 'students',
                'hint' => 'This is the distinct students who signed in that day. It does not double-count multiple visits by the same student.',
                'icon' => 'users',
                'kind' => 'sum',
                'precision' => 0,
                'detail_url' => route('teacher.roster'),
                'detail_label' => 'Open the roster',
            ],
            'quiz.retake_gain' => [
                'label' => 'Retake growth',
                'unit' => 'pts',
                'hint' => 'This is how many points a student gained between their first and latest attempt on the same story, averaged per day. It shows comprehension improving after a re-read.',
                'icon' => 'chart-line',
                'kind' => 'avg',
                'precision' => 1,
                'detail_url' => route('teacher.stories.index'),
                'detail_label' => 'Browse the story list',
            ],
            'reading.minutes' => [
                'label' => 'Reading minutes',
                'unit' => 'min',
                'hint' => 'This is the total minutes students spent reading that day. Reading time is one of the inputs to the live comprehension score.',
                'icon' => 'clock',
                'kind' => 'sum',
                'precision' => 0,
                'detail_url' => route('teacher.roster'),
                'detail_label' => 'Open the roster',
            ],
            'quiz.baseline_met' => [
                'label' => 'Goal met',
                'unit' => 'students',
                'hint' => 'Students whose quiz average that day met or beat their own placement score. Students without a placement start at 70.',
                'icon' => 'compass',
                'kind' => 'sum',
                'precision' => 0,
                'detail_url' => null,
                'detail_label' => null,
            ],
            'book.level' => [
                'label' => 'Book level',
                'unit' => 'grade',
                'hint' => 'Average reading grade of the books students finished that day. Rising means they are reading harder texts.',
                'icon' => 'book',
                'kind' => 'avg',
                'precision' => 1,
                'detail_url' => null,
                'detail_label' => null,
            ],
            'words.saved' => [
                'label' => 'Words saved',
                'unit' => 'words',
                'hint' => 'New words students saved that day, across reading and the dictionary. Vocabulary growth, school-wide.',
                'icon' => 'book',
                'kind' => 'sum',
                'precision' => 0,
                'detail_url' => null,
                'detail_label' => null,
            ],
        ];
}

    /**
     * The daily {date, count} series for one explorer metric across the
     * shared window, bucketed to the window's granularity, oldest first.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int|float}>
     */
    private static function seriesFor(string $metric, DashboardRange $range, Collection $studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        try {
            $series = match ($metric) {
                'quiz.avg_score' => static::quizScoreSeries($range, $studentIds),
                'assignments.done' => static::assignmentsDoneSeries($range, $studentIds),
                'stories.completed' => static::storiesCompletedSeries($range, $studentIds),
                'students.active' => static::activeStudentsSeries($range, $studentIds),
                'quiz.retake_gain' => static::retakeGainSeries($range, $studentIds),
                'reading.minutes' => static::readingMinutesSeries($range, $studentIds),
                'quiz.baseline_met' => static::baselineMetSeries($range, $studentIds),
            'book.level' => static::bookLevelSeries($range, $studentIds),
            'quiz.pass_rate' => [
                'label' => 'Pass rate',
                'unit' => '%',
                'hint' => 'Share of quiz attempts that day that passed (70% or higher). Small days can swing the number.',
                'icon' => 'chart-line',
                'kind' => 'avg',
                'precision' => 0,
                'detail_url' => null,
                'detail_label' => null,
            ],
            'words.saved' => static::wordsSavedSeries($range, $studentIds),
                default => [],
            };

            return $series;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Daily average score on story quiz attempts in the window. Days with no
     * attempt carry no data (they are skipped when bucketing, so a quiet
     * week never drags the average toward zero).
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int|float}>
     */
    private static function quizScoreSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = TestAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->selectRaw('DATE(created_at) AS day, AVG(score_pct) AS total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $granularity = $range->granularity();

        if ($granularity === 'day') {
            $points = [];

            foreach ($range->dates() as $day) {
                if (isset($rows[$day])) {
                    $points[] = ['date' => $day, 'count' => round((float) $rows[$day], 1)];
                }
            }

            return $points;
        }

        $buckets = [];

        foreach ($rows as $day => $total) {
            $key = $granularity === 'week'
                ? Carbon::parse($day)->startOfWeek()->toDateString()
                : substr((string) $day, 0, 7).'-01';

            $buckets[$key]['sum'] = ($buckets[$key]['sum'] ?? 0) + (float) $total;
            $buckets[$key]['days'] = ($buckets[$key]['days'] ?? 0) + 1;
        }

        $points = [];
        foreach ($buckets as $key => $bucket) {
            $points[] = ['date' => $key, 'count' => round($bucket['sum'] / $bucket['days'], 1)];
        }

        return $points;
    }

    /** Share of quiz attempts that day that passed (70%+). Days with no attempt are skipped. */
    private static function passRateSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = TestAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->selectRaw('DATE(created_at) AS day, AVG(passed) * 100 AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $points = [];
        foreach ($range->dates() as $day) {
            if (isset($rows[$day])) {
                $points[] = ['date' => $day, 'count' => round((float) $rows[$day], 1)];
            }
        }

        return $points;
    }

    /** Average reading grade (Flesch level) of books finished that day. */
    private static function bookLevelSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = StoryProgress::query()
            ->whereIn('user_id', $studentIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$range->start(), $range->end()])
            ->join('stories', 'stories.id', '=', 'story_progress.story_id')
            ->selectRaw('DATE(story_progress.completed_at) AS day, AVG(stories.reading_level) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $points = [];
        foreach ($range->dates() as $day) {
            if (isset($rows[$day])) {
                $points[] = ['date' => $day, 'count' => round((float) $rows[$day], 1)];
            }
        }

        return $points;
    }

    /** New words saved per day (reading + dictionary), school-wide. */
    private static function wordsSavedSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = SavedWord::query()
            ->whereIn('user_id', $studentIds)
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return static::zeroFill($range, $rows);
    }

    /** Field Journal bosses defeated per day, school-wide. */
    private static function battlesWonSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = StoryBattle::query()
            ->whereIn('user_id', $studentIds)
            ->where('status', 'won')
            ->whereBetween('updated_at', [$range->start(), $range->end()])
            ->selectRaw('DATE(updated_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return static::zeroFill($range, $rows);
    }

    /**
     * Assignments finished per day: a student's finish lands on the day the
     * last required story of the teacher's active assignments was completed.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int}>
     */
    private static function assignmentsDoneSeries(DashboardRange $range, Collection $studentIds): array
    {
        $storyIds = static::activeAssignmentsQuery()->whereNotNull('story_id')->pluck('story_id')->unique()->all();

        if ($storyIds === []) {
            return static::zeroFill($range, []);
        }

        $rows = StoryProgress::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('story_id', $storyIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$range->start(), $range->end()])
            ->selectRaw('DATE(completed_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return static::zeroFill($range, $rows);
    }

    /**
     * Stories finished per day (any story, assigned or free reading).
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int}>
     */
    private static function storiesCompletedSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = StoryProgress::query()
            ->whereIn('user_id', $studentIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$range->start(), $range->end()])
            ->whereHas('story', fn ($q) => $q->where('status', '!=', StoryStatus::Archived))
            ->selectRaw('DATE(completed_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return static::zeroFill($range, $rows);
    }

    /**
     * Distinct students who signed in each day, from the request log.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int}>
     */
    private static function activeStudentsSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = DB::table('request_logs')
            ->whereIn('user_id', $studentIds)
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->distinct()
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) AS day, COUNT(DISTINCT user_id) AS total')
            ->pluck('total', 'day');

        return static::zeroFill($range, $rows);
    }

    /**
     * Average retake gain per day: for each story a student attempted again,
     * the latest in-window attempt's score minus their first-ever attempt's
     * score, averaged across the retakes that landed that day. Days with no
     * retake carry no data (a quiet week must not drag an average down).
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int|float}>
     */
    private static function retakeGainSeries(DashboardRange $range, Collection $studentIds): array
    {
        // Full history up to the window's end: a first attempt can sit well
        // before the window while the retake lands inside it.
        $attempts = TestAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->where('created_at', '<=', $range->end())
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'story_id', 'score_pct', 'created_at']);

        $first = [];
        $retaken = [];

        foreach ($attempts as $attempt) {
            $pair = $attempt->user_id.':'.$attempt->story_id;

            $first[$pair] ??= $attempt;

            if ($attempt->created_at->gte($range->start())) {
                $retaken[$pair] = $attempt; // ascending order, so the last one wins
            }
        }

        $daily = [];

        foreach ($retaken as $pair => $latest) {
            $earliest = $first[$pair];

            if ($earliest->id === $latest->id) {
                continue; // a single attempt, nothing was retaken
            }

            $day = $latest->created_at->toDateString();
            $daily[$day]['gain'] = ($daily[$day]['gain'] ?? 0) + ($latest->score_pct - $earliest->score_pct);
            $daily[$day]['retakes'] = ($daily[$day]['retakes'] ?? 0) + 1;
        }

        $rows = [];

        foreach ($daily as $day => $bucket) {
            $rows[$day] = round($bucket['gain'] / $bucket['retakes'], 1);
        }

        return static::averageBucket($range, $rows);
    }

    /**
     * Minutes read per day: the day's reading seconds summed across the
     * class, rounded to minutes and zero-filled across the window.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int}>
     */
    private static function readingMinutesSeries(DashboardRange $range, Collection $studentIds): array
    {
        $rows = UserDailyActivity::query()
            ->whereIn('user_id', $studentIds)
            ->whereBetween('activity_date', [$range->from, $range->to])
            ->selectRaw('activity_date AS day, SUM(reading_seconds_today) AS total')
            ->groupBy('activity_date')
            ->pluck('total', 'day');

        $minutes = [];

        foreach ($rows as $day => $seconds) {
            $minutes[(string) $day] = (int) round(((int) $seconds) / 60);
        }

        return static::zeroFill($range, $minutes);
    }

    /**
     * Distinct students per day whose average quiz score that day met or
     * beat their own placement comprehension bar (70 when never placed).
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array{date: string, count: int}>
     */
    private static function baselineMetSeries(DashboardRange $range, Collection $studentIds): array
    {
        $fallback = (int) config('placement.instructional_threshold', 70);
        $bars = User::query()->whereIn('id', $studentIds)->pluck('comprehension_bar', 'id');

        $rows = TestAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->selectRaw('user_id, DATE(created_at) AS day, AVG(score_pct) AS avg_score')
            ->groupBy('user_id')
            ->groupByRaw('DATE(created_at)')
            ->get();

        $daily = [];

        foreach ($rows as $row) {
            $bar = $bars[$row->user_id] ?? null;
            $bar = $bar === null ? $fallback : (int) $bar;

            if ((float) $row->avg_score >= $bar) {
                $day = (string) $row->day;
                $daily[$day] = ($daily[$day] ?? 0) + 1;
            }
        }

        return static::zeroFill($range, $daily);
    }

    /**
     * Turn daily averages into {date, count} points, skipping quiet days,
     * then bucket them to the window's granularity (averaging per bucket).
     *
     * @param  array<string, float>  $rows
     * @return array<int, array{date: string, count: int|float}>
     */
    private static function averageBucket(DashboardRange $range, array $rows): array
    {
        $granularity = $range->granularity();

        if ($granularity === 'day') {
            $points = [];

            foreach ($range->dates() as $day) {
                if (isset($rows[$day])) {
                    $points[] = ['date' => $day, 'count' => $rows[$day]];
                }
            }

            return $points;
        }

        $buckets = [];

        foreach ($rows as $day => $value) {
            $key = $granularity === 'week'
                ? Carbon::parse($day)->startOfWeek()->toDateString()
                : substr((string) $day, 0, 7).'-01';

            $buckets[$key]['sum'] = ($buckets[$key]['sum'] ?? 0) + $value;
            $buckets[$key]['days'] = ($buckets[$key]['days'] ?? 0) + 1;
        }

        $points = [];
        foreach ($buckets as $key => $bucket) {
            $points[] = ['date' => $key, 'count' => round($bucket['sum'] / $bucket['days'], 1)];
        }

        return $points;
    }

    /**
     * Zero-fill a per-day count map across every day of the window, then
     * bucket it to the window's granularity.
     *
     * @param  array<string, int>  $rows
     * @return array<int, array{date: string, count: int}>
     */
    private static function zeroFill(DashboardRange $range, array|Collection $rows): array
    {
        $rows = is_array($rows) ? $rows : $rows->all();
        $daily = [];

        foreach ($range->dates() as $day) {
            $daily[] = ['date' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }

        return $range->bucket($daily);
    }

    /**
     * The tile's big number: a total for sum metrics, a daily average for
     * the score metric, already formatted for display.
     *
     * @param  array<int, array{date: string, count: int|float}>  $series
     */
    private static function headlineFor(string $metric, array $series): string
    {
        if ($series === []) {
            return '—';
        }

        $meta = static::explorerMetrics()[$metric];

        if ($meta['kind'] === 'avg') {
            $values = collect($series)->pluck('count')->filter(fn ($value) => $value > 0);

            if ($values->isEmpty()) {
                return '—';
            }

            return round($values->avg(), $meta['precision']).' '.$meta['unit'];
        }

        return number_format((int) collect($series)->sum('count'));
    }

    /**
     * Class-wide thinking-level distribution for the shared window, filtered
     * to one grade when the panel's dropdown asks for it. Grade filter uses
     * effective grade (the advancing one), matching the grade panel.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array{levels: array<string, array{pct: int, strong: int, scored: int}>, scored: int}
     */
    private static function thinkingDistribution(Collection $studentIds, DashboardRange $range, string $grade): array
    {
        if ($grade !== '') {
            // Raw reported grade, same rule as the roster's grade filter, so
            // the "See students" link lands on the exact set shown here.
            $studentIds = User::query()
                ->whereIn('id', $studentIds)
                ->where('grade_level', $grade)
                ->pluck('id');
        }

        $levels = BloomLevels::classDistribution($studentIds, $range->start(), $range->end());

        return [
            'levels' => $levels,
            'scored' => max(array_map(fn (array $level) => $level['scored'], $levels)),
        ];
    }

    /**
     * Stories where the class struggles: lowest average score first, only
     * stories with at least 3 real attempts so one bad quiz never brands a
     * story. Links to the story list, not a per-story page.
     *
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, array{title: string, avg: float, attempts: int}>
     */
    private static function textDifficulty(Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return TestAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereHas('story', fn ($q) => $q->where('status', '!=', StoryStatus::Archived))
            ->with('story')
            ->selectRaw('story_id, AVG(score_pct) AS avg_score, COUNT(*) AS attempts')
            ->groupBy('story_id')
            ->having('attempts', '>=', 3)
            ->orderBy('avg_score')
            ->limit(7)
            ->get()
            ->map(fn ($row) => [
                'title' => $row->story?->title ?? 'Unknown story',
                'avg' => round((float) $row->avg_score, 1),
                'attempts' => (int) $row->attempts,
            ])
            ->values();
    }

    /**
     * The full class list: every student, searchable and filterable, with
     * the same honesty rules as the overview (students only, suspended
     * students visible with their badge — never hidden from a teacher).
     */
    public function roster(Request $request): View
    {
        $skill = $request->string('skill')->toString();
        $band = $request->string('band')->toString();

        $skillIds = null;

        if (in_array($skill, BloomLevels::ORDER, true) && in_array($band, ['strong', 'practice'], true)) {
            $skillIds = BloomLevels::userIdsAtLevel(
                $skill,
                $band,
                static::studentQuery()->pluck('id'),
                now()->subDays(90)->startOfDay(),
                now()->endOfDay(),
            );
        }

        $students = static::studentQuery()
            ->with(['placementAttempt', 'readingProgress'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q')->toString();

                $query->where(function ($where) use ($q) {
                    $where->where('nickname', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('grade'), fn ($query) => $query->where('grade_level', $request->string('grade')->toString()))
            ->when($request->filled('comprehension'), function ($query) use ($request) {
                $level = $request->string('comprehension')->toString();

                if ($level === 'none') {
                    $query->whereDoesntHave('readingProgress', fn ($progress) => $progress->whereNotNull('comprehension_level'));
                } else {
                    $query->where(function ($q) use ($level) {
                        $q->whereHas('readingProgress', fn ($progress) => $progress->where('comprehension_level', $level))
                            ->orWhere(function ($p) use ($level) {
                                $p->where('is_setup_complete', true)
                                    ->where('users.comprehension_level', $level)
                                    ->whereDoesntHave('readingProgress', fn ($progress) => $progress->whereNotNull('comprehension_level'));
                            });
                    });
                }
            })
            ->when($skillIds !== null, fn ($query) => $query->whereIn('users.id', $skillIds))
            ->when($request->filled('trend'), function ($query) use ($request) {
                $trend = $request->string('trend')->toString();
                $trend === 'none'
                    ? $query->whereDoesntHave('readingProgress')
                    : $query->whereHas('readingProgress', fn ($p) => $p->where('trend', $trend));
            })
            ->when($request->string('attention')->toString() === 'slipping', function ($query) {
                $query->whereHas('readingProgress', fn ($q) => $q->where('slipping', true));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $request->string('status')->toString() === 'suspended'
                    ? $query->whereNotNull('suspended_at')
                    : $query->whereNull('suspended_at');
            })
            ->when($request->filled('setup'), function ($query) use ($request) {
                $request->string('setup')->toString() === 'done'
                    ? $query->where('is_setup_complete', true)
                    : $query->where('is_setup_complete', false);
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('teacher.roster', [
            'students' => $students,
        ]);
    }

    /**
     * One student's full learning picture. Teachers see placement results
     * and account facts — read-only. Only student accounts exist here.
     */
    public function student(Request $request, User $user): View
    {
        // Teachers only reach real registered students; demo/test/scratch
        // accounts are admin-only, so their detail pages 404 here.
        abort_unless($user->role === Role::Student, 404);
        abort_unless(User::realAccounts()->whereKey($user->id)->exists(), 404);

        $user->load(['readingProgress', 'placementAttempt']);

        // Optional "back to assignment" context set when the teacher arrived
        // from an assignment rollup row, so returning stays on the assignment.
        $fromAssignment = null;
        $from = $request->string('from')->toString();
        if (str_starts_with($from, 'assignment:')) {
            $assignmentId = (int) substr($from, strlen('assignment:'));
            if ($assignmentId > 0) {
                $fromAssignment = Assignment::query()
                    ->where('id', $assignmentId)
                    ->where('teacher_id', auth()->id())
                    ->first();
            }
        }

        // One query feeds both the flagged-attempt integrity list and the
        // full quiz history. Teacher-only integrity observations are neutral
        // facts only — a starting point for a check-in.
        $attempts = $user->testAttempts()
            ->with('story')
            ->orderBy('created_at')
            ->get();

        $flaggedAttempts = $attempts
            ->filter(fn ($attempt) => $attempt->attempt_flags !== null && IntegritySignals::hasFlags($attempt->attempt_flags ?? []))
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($attempt) {
                return [
                    'attempt' => $attempt,
                    'story' => $attempt->story->title,
                    'score' => $attempt->score_pct,
                    'passed' => $attempt->passed,
                    'at' => $attempt->created_at,
                    'notes' => IntegritySignals::reviewNotes($attempt->attempt_flags ?? []),
                ];
            });

        return view('teacher.student', [
            'student' => $user,
            'fromAssignment' => $fromAssignment,
            'recentActivity' => StudentActivity::recentFor($user),
            'flaggedAttempts' => $flaggedAttempts,
            'thinking' => BloomLevels::userAccuracy($user->id),
            'quizHistory' => $attempts
                ->map(fn ($a) => [
                    'date' => $a->created_at->format('Y-m-d'),
                    'score' => $a->score_pct,
                    'passed' => $a->passed,
                    'story' => $a->story?->title ?? 'Unknown',
                    'flags' => IntegritySignals::hasFlags($a->attempt_flags ?? []),
                ]),
        ]);
    }

    /**
     * Hold a student's grade for the current school year.
     *
     * Used when a student is repeating or retained: re-anchoring the grade to
     * the current school year stops the auto-advance (effectiveGrade stops at
     * the reported grade), so ALICE matches what actually happened in school.
     * The hold lasts one school year; if the student repeats again, the
     * teacher holds the grade again the following June. ALICE never judges
     * results on its own — this is a manual, teacher-informed decision.
     */
    public function holdGrade(User $user): RedirectResponse
    {
        abort_unless($user->role === Role::Student, 404);
        abort_unless(User::realAccounts()->whereKey($user->id)->exists(), 404);

        if ($user->grade_level === null) {
            return back()->with('toast', [
                'message' => 'This student has not set a grade yet.',
            ]);
        }

        $user->update([
            'grade_hold_school_year' => SchoolYear::startYear(),
        ]);

        return back()->with('toast', [
            'message' => 'Grade held for this school year. It will no longer advance automatically.',
        ]);
    }

    /**
     * Release a grade hold so advancement resumes from the original anchor.
     */
    public function unholdGrade(User $user): RedirectResponse
    {
        abort_unless($user->role === Role::Student, 404);
        abort_unless(User::realAccounts()->whereKey($user->id)->exists(), 404);

        if ($user->grade_hold_school_year === null) {
            return back()->with('toast', [
                'message' => 'This student has no active grade hold.',
            ]);
        }

        $user->update(['grade_hold_school_year' => null]);

        return back()->with('toast', [
            'message' => 'Grade hold released. It will advance again this June.',
        ]);
    }

    /**
     * Base query for a teacher's student view: real registered students only.
     * The shared realAccounts() scope drops the disposable admin-preview
     * "scratch" student plus demo/test accounts, so terminal-created dummies
     * (capstone, demo, preview) never reach a class roster, count, series or
     * activity stream. Admin surfaces keep them.
     *
     * @return Builder<User>
     */
    private static function studentQuery()
    {
        return User::where('role', Role::Student)->realAccounts();
    }

    /**
     * Students grouped by their effective grade (the grade that advances
     * each school year). Anchored grades are shifted in PHP — student
     * counts at a school are small enough that this stays cheap.
     *
     * @return array<int, int>
     */
    private static function gradeBreakdown(): array
    {
        $rows = static::studentQuery()
            ->whereNotNull('grade_level')
            ->get(['grade_level', 'grade_anchor_year']);

        $breakdown = [];

        foreach ($rows as $student) {
            $grade = $student->effectiveGrade();
            $breakdown[$grade] = ($breakdown[$grade] ?? 0) + 1;
        }

        ksort($breakdown);

        return $breakdown;
    }

    /**
     * Comprehension bands from the LIVE reading-progress engine, not the
     * placement-era users.comprehension_level snapshot. Students without
     * enough evidence to have a reading_progress row yet are not shown.
     *
     * @return array<string, int>
     */
    private static function comprehensionDistribution(): array
    {
        $live = static::studentQuery()
            ->join('reading_progress', 'reading_progress.user_id', '=', 'users.id')
            ->selectRaw('reading_progress.comprehension_level AS level, COUNT(*) AS total')
            ->groupBy('reading_progress.comprehension_level')
            ->pluck('total', 'level')
            ->map(fn ($total) => (int) $total)
            ->all();

        // Finished placement, no live quiz result yet: counted at their placement band.
        $placement = static::studentQuery()
            ->where('is_setup_complete', true)
            ->whereNotNull('users.comprehension_level')
            ->whereDoesntHave('readingProgress', fn ($q) => $q->whereNotNull('comprehension_level'))
            ->selectRaw('users.comprehension_level AS level, COUNT(*) AS total')
            ->groupBy('users.comprehension_level')
            ->pluck('total', 'level')
            ->map(fn ($total) => (int) $total)
            ->all();

        foreach ($placement as $level => $total) {
            $live[$level] = ($live[$level] ?? 0) + $total;
        }

        return $live;
    }

    /** Finished placement but no live quiz result yet (shown at their placement grade). */
    private static function placementOnlyCount(): int
    {
        return static::studentQuery()
            ->where('is_setup_complete', true)
            ->whereDoesntHave('readingProgress', fn ($q) => $q->whereNotNull('comprehension_level'))
            ->count();
    }

    /** Band movement since placement: up / steady / down / no live result yet. */
    private static function growthTrends(Collection $studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return ['up' => 0, 'steady' => 0, 'down' => 0, 'none' => 0];
        }

        $rows = ReadingProgress::query()->whereIn('user_id', $studentIds)->pluck('trend', 'user_id');
        $up = $rows->filter(fn ($t) => $t === 'up')->count();
        $down = $rows->filter(fn ($t) => $t === 'down')->count();
        $withRow = $rows->count();

        return [
            'up' => $up,
            'down' => $down,
            'steady' => $withRow - $up - $down,
            'none' => $studentIds->count() - $withRow,
        ];
    }

    /**
     * Students who likely need a teacher's attention: suspended accounts,
     * unfinished placement, or no sign-in in two weeks.
     *
     * @return Collection<int, User>
     */
    private static function struggling(): Collection
    {
        return static::studentQuery()
            ->with('readingProgress')
            ->where(function ($query) {
                $query->whereNotNull('suspended_at')
                    ->orWhere('is_setup_complete', false)
                    ->orWhereNull('last_login_at')
                    ->orWhere('last_login_at', '<', now()->subDays(14))
                    ->orWhereHas('readingProgress', fn ($q) => $q->where('slipping', true));
            })
            ->latest('last_login_at')
            ->limit(8)
            ->get();
    }
}
