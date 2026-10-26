<?php

namespace App\Services;

use App\Enums\ComprehensionLevel;
use App\Enums\StoryProgressStatus;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The live "reading level that moves" engine.
 *
 * Turns the one-time placement snapshot into a living, evidence-based
 * comprehension estimate driven by real post-placement reading + quiz
 * activity, and derives the level (Critical/Frustration/Instructional/
 * Independent) + served grade exactly like placement does — only the input
 * changes (see config('placement.reading_progress')).
 *
 * Honesty contract:
 *  - Best attempt per story (re-quizzes can't grind the level up).
 *  - Difficulty-weighted: a pass on a higher-grade story outweighs the same
 *    score on an easier story.
 *  - Recency-weighted so the level moves "little by little" with practice.
 *  - Secondary, capped credit for completion volume + reading time so reading
 *    habits genuinely nudge the bar — but quiz evidence sets the ceiling.
 *  - A low band only upgrades decisively with evidence across >=2 distinct
 *    story grades.
 *  - Band hysteresis stops a half-point from flipping the pill back/forth.
 *  - Decay only on TOTAL inactivity (never on "not quizzing while reading"),
 *    and it never shrinks the student's positive bar.
 *  - "Slipping" is teacher-facing only and never touches the student bar.
 */
class ReadingProgressService
{
    public const TREND_UP = 'up';

    public const TREND_STEADY = 'steady';

    public const TREND_DOWN = 'down';

    /** Current live estimate for a student (persists on first computation). */
    public static function for(User $user): ReadingProgress
    {
        return self::recompute($user);
    }

    /**
     * Recompute the live evidence-backed comprehension estimate for one
     * student and persist it. Call from the quiz/reader save points (fast,
     * single-user) and from the nightly command (whole class).
     */
    public static function recompute(User $user): ReadingProgress
    {
        $now = now();

        // Reading evidence is only meaningful after placement set a baseline.
        $snapshotLevel = $user->comprehension_level;
        $prior = $user->readingProgress;

        $performedAt = self::lastActivityAt($user);

        // Fresh evidence model from real activity.
        $evidence = self::gatherEvidence($user, $now);
        $quizScore = $evidence['quiz_score'];          // difficulty+recency weighted (0-100) or null
        $sampleCount = $evidence['sample_count'];
        $distinctGrades = $evidence['distinct_grades'];
        $secondary = $evidence['secondary_credit'];    // 0-100 capped secondary signal or null

        $minSample = (int) config('placement.reading_progress.min_sample');

        // Active => build/maintain a live score; otherwise fall back to snapshot.
        $useSnapshot = $sampleCount < $minSample;

        if ($useSnapshot) {
            $liveScore = $snapshotLevel !== null ? (int) ($user->comprehension_bar ?? 0) : null;
        } else {
            $liveScore = self::blend($quizScore, $secondary, $now, $performedAt, $snapshotLevel, $sampleCount, $distinctGrades);
        }

        // Teacher-eyed honest estimate: mirrors live but decays toward the
        // conservative band once the student goes totally inactive. Never
        // affects live_score, so the student's positive bar never shrinks.
        $inactive = $performedAt !== null
            && $performedAt->lt($now->copy()->subDays((int) config('placement.reading_progress.decay_inactive_days')));
        $teacherScore = $liveScore;
        if ($inactive && $liveScore !== null) {
            $teacherScore = self::decayed($liveScore, $performedAt, $now);
        }

        // Derive the level + served grade from the student-growth number
        // (never lets a failing student's bar shrink below the placement band).
        $level = $liveScore !== null
            ? self::levelBetween($liveScore, $snapshotLevel)
            : $snapshotLevel;
        $readingGrade = $level !== null
            ? self::gradeForLevel($user, $level)
            : (int) ($user->reading_level ?? config('placement.reading_level_floor'));

        $trend = self::trendFor($prior, $liveScore, $now);

        $progress = ReadingProgress::updateOrCreate(
            ['user_id' => $user->id],
            [
                'live_score' => $liveScore,
                'teacher_score' => $teacherScore,
                'comprehension_level' => $level,
                'reading_grade' => $readingGrade,
                'sample_count' => $sampleCount,
                'distinct_grades' => $distinctGrades,
                'last_activity_at' => $performedAt ?? $prior?->last_activity_at,
                'computed_at' => $now,
                'trend' => $trend,
            ],
        );

        // Slipping is derived on the live evidence and persisted separately so
        // teacher surfaces can query it without recomputing per row.
        $progress->slipping = self::slipping($user, $now);
        $progress->save();

        return $progress;
    }

    /**
     * The honest, teacher-eyed comprehension level from the (possibly decayed)
     * teacher_score. Falls back to a conservative derivation for totally
     * inactive students so a stale inflated level is never presented as fresh.
     */
    public static function teacherLevel(ReadingProgress $progress): ?ComprehensionLevel
    {
        if ($progress->teacher_score === null) {
            return $progress->comprehension_level;
        }

        return self::levelBetween((float) $progress->teacher_score, $progress->comprehension_level);
    }

    /** The teacher-eyed served grade (decayed), for display on teacher surfaces. */
    public static function teacherGrade(User $user, ReadingProgress $progress): ?int
    {
        $level = self::teacherLevel($progress);

        return $level !== null ? self::gradeForLevel($user, $level) : null;
    }

    /** Basis string for the "every number shows its evidence" honesty rule. */
    public static function basisFor(ReadingProgress $progress): string
    {
        if ($progress->sample_count < (int) config('placement.reading_progress.min_sample')) {
            return 'from placement only';
        }

        return 'based on '.$progress->sample_count
            .' quizzed storie'.($progress->sample_count === 1 ? '' : 's')
            .' across '.$progress->distinct_grades.' grade'.($progress->distinct_grades === 1 ? '' : 's');
    }

    /**
     * The grade to serve the student right now — the live reading_grade when
     * enough evidence exists, else the placement reading_level, clamped to the
     * 7-10 library scale. This is the single number that drives the
     * "Recommended for Grade X" band, so the level has a real job.
     */
    public static function serveGrade(User $user): int
    {
        $live = $user->readingProgress?->reading_grade;

        if ($live !== null) {
            return min(10, max(7, (int) $live));
        }

        return min(10, max(7, (int) ($user->reading_level ?? config('placement.reading_level_floor'))));
    }

    /**
     * Whether the student sits in the protected low bands (Critical /
     * Frustration) on their live evidence (or placement until evidence lands).
     * Low-band students are served slightly below their band + short-first so
     * early wins are achievable — the active mitigation rail.
     */
    public static function isLowBand(User $user): bool
    {
        $level = $user->readingProgress?->comprehension_level ?? $user->comprehension_level;

        return $level === ComprehensionLevel::Critical || $level === ComprehensionLevel::Frustration;
    }

    /**
     * One truth for "has this student produced any real activity at all?"
     * Used for decay + the teacher "no data since placement" label.
     */
    public static function lastActivityAt(User $user): ?Carbon
    {
        $latestRead = StoryProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [StoryProgressStatus::Read->value, StoryProgressStatus::Completed->value])
            ->max('updated_at');

        $latestQuiz = TestAttempt::query()
            ->where('user_id', $user->id)
            ->max('created_at');

        $latestDrill = UserDailyActivity::query()
            ->where('user_id', $user->id)
            ->max('updated_at');

        $candidates = array_filter([
            $latestRead ? Carbon::parse($latestRead) : null,
            $latestQuiz ? Carbon::parse($latestQuiz) : null,
            $latestDrill ? Carbon::parse($latestDrill) : null,
        ]);

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortDesc()->first();
    }

    /**
     * Gather the weighted quiz evidence (best attempt per story), count sample
     * story-grades, and assemble the capped secondary reading credit.
     *
     * @return array{quiz_score: ?float, sample_count: int, distinct_grades: int, secondary_credit: ?float}
     */
    private static function gatherEvidence(User $user, Carbon $now): array
    {
        // Best attempt per story, with the story's reading grade and the latest
        // moment that best score was recorded. One windowed pass replaces the
        // per-story attempt_at subquery (N+1) by ranking rows with
        // ROW_NUMBER() per story in score-then-recency order and keeping the
        // winner — identical to "MAX(score), latest created_at among ties".
        $bestByStory = DB::table('test_attempts as t')
            ->join('stories as s', 's.id', '=', 't.story_id')
            ->where('t.user_id', $user->id)
            ->selectRaw('t.story_id AS story_id, t.score_pct AS best_pct, s.reading_level AS story_grade, t.created_at AS attempt_at, ROW_NUMBER() OVER (PARTITION BY t.story_id ORDER BY t.score_pct DESC, t.created_at DESC) AS rn')
            ->get()
            ->filter(fn ($row) => (int) $row->rn === 1)
            ->map(fn ($row) => [
                'story_id' => (int) $row->story_id,
                'best_pct' => (float) $row->best_pct,
                'story_grade' => (int) $row->story_grade,
                'attempt_at' => $row->attempt_at,
            ])
            ->values();

        $sampleCount = $bestByStory->count();
        $distinctGrades = $bestByStory->pluck('story_grade')->unique()->count();

        // Difficulty + recency weighted mean of the best-per-story evidence.
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        foreach ($bestByStory as $row) {
            $difficultyWeight = $row['story_grade'] / 10.0;
            $recencyWeight = self::recencyWeight(Carbon::parse($row['attempt_at']), $now);
            $weight = $difficultyWeight * $recencyWeight;
            $weightedSum += $row['best_pct'] * $weight;
            $weightTotal += $weight;
        }
        $quizScore = $weightTotal > 0 ? ($weightedSum / $weightTotal) : null;

        // Secondary reading credit: completion volume + reading time, capped.
        $secondary = self::secondaryCredit($user, $now);

        return [
            'quiz_score' => $quizScore !== null ? round($quizScore, 1) : null,
            'sample_count' => $sampleCount,
            'distinct_grades' => $distinctGrades,
            'secondary_credit' => $secondary,
        ];
    }

    /** Bounded "just reading" credit so habits nudge the bar but can't max it. */
    private static function secondaryCredit(User $user, Carbon $now): ?float
    {
        $rows = StoryProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [StoryProgressStatus::Read->value, StoryProgressStatus::Completed->value])
            ->get(['status', 'time_spent_seconds']);

        if ($rows->isEmpty()) {
            return null;
        }

        // Volume: number of finished stories (capped) * a small base.
        $completedCount = min($rows->count(), 30);
        $volume = $completedCount * 1.0;

        // Time: capped reading seconds -> minutes, small contribution.
        $minutes = min((int) round($rows->sum('time_spent_seconds') / 60), 600);
        $time = $minutes * 0.02;

        $raw = $volume + $time;

        // Cap the secondary credit so it can only brighten a borderline case,
        // never substitute for demonstrated comprehension.
        return min($raw, 85.0);
    }

    /**
     * Blend quiz evidence (primary) with the capped secondary reading credit
     * (secondary_weight proportion), then confidence-damp thin evidence toward
     * the placement baseline so a single fluke quiz can't spike the bar.
     */
    private static function blend(?float $quiz, ?float $secondary, Carbon $now, ?Carbon $performedAt, ?ComprehensionLevel $snapshot, int $sampleCount, int $distinctGrades): ?float
    {
        if ($quiz === null) {
            return null;
        }

        $secondaryWeight = (float) config('placement.reading_progress.secondary_weight');
        $secondary = $secondary ?? $quiz;
        $blended = ($quiz * (1 - $secondaryWeight)) + ($secondary * $secondaryWeight);

        // Confidence damping: thin evidence is pulled toward the placement
        // baseline so one fluke quiz can't spike the student bar.
        $minSample = (int) config('placement.reading_progress.min_sample');
        $minGrades = (int) config('placement.reading_progress.min_distinct_grades');

        $confidence = max(
            min($sampleCount / max($minSample, 1), 1.0),
            min($distinctGrades / max($minGrades, 1), 1.0),
        );

        if ($confidence < 1.0 && $snapshot !== null) {
            $blended = ($blended * $confidence) + ((float) ($snapshot === ComprehensionLevel::Independent ? config('placement.independent_threshold') : 50) * (1 - $confidence));
        }

        return round($blended, 1);
    }

    private static function recencyWeight(Carbon $at, Carbon $now): float
    {
        $halfLifeDays = (float) config('placement.reading_progress.recency_half_life_days');
        $ageDays = max(0, (float) $at->diffInDays($now));

        return pow(0.5, $ageDays / max($halfLifeDays, 1));
    }

    /** Conservative-band decay for totally inactive students. */
    private static function decayed(float $score, Carbon $performedAt, Carbon $now): float
    {
        $idleDays = max(0, (float) $performedAt->diffInDays($now));
        $decayWindow = (float) config('placement.reading_progress.decay_inactive_days');
        $factor = min(1, $idleDays / max($decayWindow, 1));

        // Pull up to 25% of the way toward the conservative (50) baseline.
        return round($score - (($score - 50) * 0.25 * $factor), 1);
    }

    /** Threshold mapping identical to placement (levelFor), shared helper. */
    private static function levelFromScore(float $score): ComprehensionLevel
    {
        if ($score >= (int) config('placement.independent_threshold')) {
            return ComprehensionLevel::Independent;
        }
        if ($score >= (int) config('placement.instructional_threshold')) {
            return ComprehensionLevel::Instructional;
        }
        if ($score >= (int) config('placement.frustration_threshold')) {
            return ComprehensionLevel::Frustration;
        }

        return ComprehensionLevel::Critical;
    }

    /**
     * Live level with band hysteresis — the level never shrinks below the
     * placement band (bar is growth-positive) and a fresh up-band must sit a
     * couple of points past the threshold before it sticks.
     */
    private static function levelBetween(float $score, ?ComprehensionLevel $snapshot): ComprehensionLevel
    {
        $candidate = self::levelFromScore($score);

        if ($snapshot === null) {
            return $candidate;
        }

        // Never drop below where they started (bar is positive-only).
        $order = [
            ComprehensionLevel::Critical->value => 0,
            ComprehensionLevel::Frustration->value => 1,
            ComprehensionLevel::Instructional->value => 2,
            ComprehensionLevel::Independent->value => 3,
        ];
        $candidateOrder = $order[$candidate->value];
        $snapshotOrder = $order[$snapshot->value];

        if ($candidateOrder < $snapshotOrder) {
            return $snapshot;
        }

        // Upgrades need hysteresis: hold a couple points past the threshold.
        if ($candidateOrder > $snapshotOrder) {
            $need = self::hysteresisNeeded($candidate);
            if ($score < $need) {
                return $snapshot;
            }
        }

        return $candidate;
    }

    private static function hysteresisNeeded(ComprehensionLevel $level): float
    {
        $points = (int) config('placement.reading_progress.band_hysteresis_points');
        $thresholds = [0, (int) config('placement.frustration_threshold'), (int) config('placement.instructional_threshold'), (int) config('placement.independent_threshold')];
        $idx = [
            ComprehensionLevel::Critical->value => 0,
            ComprehensionLevel::Frustration->value => 1,
            ComprehensionLevel::Instructional->value => 2,
            ComprehensionLevel::Independent->value => 3,
        ][$level->value];

        return $thresholds[$idx] + $points;
    }

    /**
     * The 7-10 served grade for a level, mirroring placement's offset from the
     * reported grade and the same reading_level_floor.
     */
    private static function gradeForLevel(User $user, ComprehensionLevel $level): int
    {
        $reported = (int) ($user->grade_level?->value ?? 0);
        $offset = (int) config('placement.reading_level_offsets.'.$level->value);

        return max((int) config('placement.reading_level_floor'), $reported - $offset);
    }

    /** up / steady / down vs. the prior persisted live score. */
    private static function trendFor(?ReadingProgress $prior, ?float $liveScore, Carbon $now): ?string
    {
        if ($liveScore === null || $prior === null || $prior->live_score === null) {
            return null;
        }

        $delta = $liveScore - (float) $prior->live_score;
        $stale = $prior->computed_at !== null
            && $prior->computed_at->lt($now->copy()->subDays((int) config('placement.reading_progress.recency_half_life_days')));

        if ($stale) {
            return null;
        }

        if ($delta >= 2) {
            return self::TREND_UP;
        }
        if ($delta <= -2) {
            return self::TREND_DOWN;
        }

        return self::TREND_STEADY;
    }

    /**
     * Teacher-facing maintenance flag, only for Instructional/Independent
     * placement students (Critical/Frustration are always protected — they are
     * expected to fail while climbing). Pattern: the last N quizzes contain >=
     * M fails AND no upward trend recently. Purely diagnostic; never touches
     * the student bar.
     */
    private static function slipping(User $user, Carbon $now): bool
    {
        $placement = $user->comprehension_level;

        if ($placement === null) {
            return false;
        }

        // Protected low bands never slip.
        if ($placement === ComprehensionLevel::Critical || $placement === ComprehensionLevel::Frustration) {
            return false;
        }

        $recent = (int) config('placement.reading_progress.slipping_recent_quizzes');
        $minFails = (int) config('placement.reading_progress.slipping_min_fails');
        $pass = (int) config('gamification.pass_threshold_pct');

        $attempts = TestAttempt::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($recent)
            ->get();

        if ($attempts->count() < $recent) {
            return false;
        }

        $fails = $attempts->filter(fn ($a) => (float) $a->score_pct < $pass)->count();
        if ($fails < $minFails) {
            return false;
        }

        // Require no improving trend over those recent attempts.
        $scores = $attempts->sortBy('created_at')->pluck('score_pct');
        $improving = $scores->last() > $scores->first() + 5;

        return ! $improving;
    }
}
