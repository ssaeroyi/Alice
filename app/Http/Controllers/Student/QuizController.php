<?php

namespace App\Http\Controllers\Student;

use App\Enums\QuestionType;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Question;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Services\BadgeService;
use App\Services\DailyTaskService;
use App\Services\ReadingProgressService;
use App\Services\RewardService;
use App\Support\AliceLibrary;
use App\Support\IntegritySignals;
use App\Support\NotificationService;
use App\Support\PointsRank;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The student quiz player (STUDENT_PAGES_PLAN §4.4). Entry is B2-gated,
 * questions draw balanced across sections into a session-held attempt
 * (the UUID survives refreshes), scoring is entirely server-side with
 * partial credit for matching pairs and sequence positions, and
 * settlement is idempotent: the unique attempt_uuid insert inside the
 * settle transaction means double-clicks and replayed POSTs pay once.
 */
class QuizController extends Controller
{
    public function show(Request $request, Story $story)
    {
        abort_unless($story->status === StoryStatus::Published, 404);

        if (! $story->questions()->exists()) {
            abort(404);
        }

        $opened = StoryProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('story_id', $story->id)
            ->where('current_page', '>', 0)
            ->exists();

        if (! $opened) {
            return redirect()
                ->route('story.preview', $story)
                ->with('toast', ['message' => 'Open the story first. The quiz unlocks after you start reading.']);
        }

        // Session-held attempt: refreshing the player keeps the same UUID
        // and the same draw; nothing about the attempt lives only in JS.
        $session = $request->session()->get("quiz_session.{$story->id}");

        if ($session === null || ! $this->drawStillValid($session)) {
            $session = [
                'uuid' => (string) Str::uuid(),
                'question_ids' => $this->drawQuestions($story)->pluck('id')->all(),
                'hints' => [],
                'hint_count' => 0,
            ];
            $request->session()->put("quiz_session.{$story->id}", $session);
        }

        $questions = Question::query()
            ->whereIn('id', $session['question_ids'])
            ->get()
            ->sortBy(fn (Question $q) => array_search($q->id, $session['question_ids']))
            ->values();

        $sections = QuestionSection::query()
            ->whereIn('id', $questions->pluck('section_id')->filter())
            ->pluck('name', 'id');

        $panels = $questions->map(fn (Question $q) => $this->panelFor($q, $sections[$q->section_id] ?? 'General'))->all();

        return view('student.quiz', [
            'story' => $story,
            'panels' => $panels,
            'total' => count($panels),
            'band' => AliceLibrary::difficultyBand((int) $story->reading_level),
            'balance' => (int) $request->user()->total_keys,
            'hintsUsed' => (int) $session['hint_count'],
            'hintsMax' => (int) config('gamification.hint_tokens_per_quiz'),
            'hintCost' => (int) config('gamification.sinks.hint_token'),
            'hintsApplied' => $session['hints'],
        ]);
    }

    /**
     * Server-scored settlement inside one transaction. The unique
     * attempt_uuid insert is the idempotency gatekeeper: a replayed POST
     * lands on the duplicate key, reads the already-settled row and gets
     * the original result back verbatim.
     */
    public function submit(Request $request, Story $story)
    {
        abort_unless($story->status === StoryStatus::Published, 404);

        // The answer shape varies per question type; grading coerces and
        // validates each shape defensively, so the request only pins the
        // envelope here.
        $data = $request->validate([
            'attempt_uuid' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'max:30'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.value' => ['present'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0', 'max:7200'],
            'integrity' => ['sometimes', 'array'],
            'integrity.focus_lost' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'integrity.focus_duration' => ['sometimes', 'integer', 'min:0', 'max:7200'],
            'integrity.pastes' => ['sometimes', 'array', 'max:30'],
            'integrity.pastes.*.question' => ['integer', 'nullable'],
            'integrity.pastes.*.chars' => ['integer', 'min:0'],
        ]);

        $session = $request->session()->get("quiz_session.{$story->id}");

        if ($session === null || $session['uuid'] !== $data['attempt_uuid']) {
            return response()->json(['error' => 'stale_attempt'], 409);
        }

        $questions = Question::query()
            ->whereIn('id', $session['question_ids'])
            ->get()
            ->keyBy('id');

        [$earned, $points, $correctCount] = $this->score($questions, $data['answers']);
        $pct = $points > 0 ? (int) round(100 * $earned / $points) : 0;
        $passThreshold = (int) config('gamification.pass_threshold_pct');
        $passed = $pct >= $passThreshold;
        $now = now();

        // Pre-insert assignment state on this story: the attempt below is what
        // may flip an active assignment to complete, and the banner must only
        // fire on that true false -> true transition (a re-try or a replayed
        // POST never re-celebrates).
        $alreadyComplete = Assignment::activeForStory($request->user(), (int) $story->id)
            ->filter(fn (Assignment $assignment) => $assignment->completedByEvidence($request->user()))
            ->pluck('id')
            ->all();

        try {
            $result = DB::transaction(function () use ($request, $story, $data, $questions, $session, $correctCount, $pct, $passed, $now) {
                $previousBest = StoryProgress::query()
                    ->where('user_id', $request->user()->id)
                    ->where('story_id', $story->id)
                    ->lockForUpdate()
                    ->value('best_score_pct');

                $auditItems = $this->answerAudit($questions, $data['answers']);

                $attempt = TestAttempt::create([
                    'user_id' => $request->user()->id,
                    'story_id' => $story->id,
                    'attempt_uuid' => $data['attempt_uuid'],
                    'score_pct' => $pct,
                    'passed' => $passed,
                    'correct_count' => $correctCount,
                    'wrong_count' => max($questions->count() - $correctCount, 0),
                    'time_spent_seconds' => (int) ($data['time_spent_seconds'] ?? 0),
                    'answers_json' => ['items' => $auditItems],
                    'attempt_flags' => $this->integrityFlags($data, (int) ($data['time_spent_seconds'] ?? 0), $questions->count()),
                    'reward' => 0,
                    'xp_awarded' => 0,
                    'hint_tokens_used' => (int) ($session['hint_count'] ?? 0),
                ]);

                $earnings = RewardService::settleQuizAttempt(
                    $request->user(),
                    $story->id,
                    AliceLibrary::difficultyBand((int) $story->reading_level),
                    (float) $pct,
                    $previousBest !== null ? (float) $previousBest : null,
                    $now,
                );

                // Every submission stamps the day - including failures.
                $stamp = RewardService::stampDay($request->user(), 'quiz', $now);

                $progress = StoryProgress::query()
                    ->where('user_id', $request->user()->id)
                    ->where('story_id', $story->id)
                    ->lockForUpdate()
                    ->first();

                $progress?->update([
                    'best_score_pct' => max((int) ($progress->best_score_pct ?? 0), $pct),
                ] + ($passed && $progress->status !== StoryProgressStatus::Completed ? [
                    'status' => StoryProgressStatus::Completed,
                    'completed_at' => $now,
                ] : []));

                // Backfill the honest settlement snapshot so a replayed POST
                // can report the exact same reason and multipliers verbatim.
                $attempt->update([
                    'reward' => $earnings['keys'],
                    'xp_awarded' => $earnings['xp'],
                    'answers_json' => [
                        'items' => $auditItems,
                        'settlement' => [
                            'reason' => $earnings['reason'],
                            'multiplier' => $earnings['multiplier'],
                        ],
                    ],
                ]);

                $request->session()->forget("quiz_session.{$story->id}");

                // Route any newly reached title to the results page via the
                // session (same one-shot pattern as story_clear_award), so the
                // GET results view can render the ceremony exactly once.
                if (! empty($earnings['promotion'])) {
                    $request->session()->flash(
                        'title_promotion',
                        ['quiz' => $earnings['promotion']],
                    );
                }

                return [
                    'attempt' => $attempt,
                    'earnings' => $earnings,
                    'stamp' => $stamp,
                ];
            });
        } catch (QueryException $e) {
            if (! $this->isDuplicateAttempt($e)) {
                throw $e;
            }

            // Replay of an already-settled attempt: answer verbatim from
            // the stored row so nobody can ever be paid twice.
            $attempt = TestAttempt::query()
                ->where('attempt_uuid', $data['attempt_uuid'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            return response()->json($this->resultPayload(
                $story,
                $attempt,
                [
                    'keys' => (int) $attempt->reward,
                    'xp' => (int) $attempt->xp_awarded,
                    'multiplier' => (float) ($attempt->answers_json['settlement']['multiplier']
                        ?? RewardService::accuracyTier((float) $attempt->score_pct)),
                    'reason' => $attempt->answers_json['settlement']['reason'] ?? $this->replayReason($attempt),
                ],
                ['stamped' => false, 'streak' => $request->user()->current_streak],
                replayed: true,
                live: $this->liveTaskState($request),
            ));
        }

        // Refresh the student's live reading progress from this new evidence so
        // their "reading level" bar + recommendation band stay current.
        ReadingProgressService::recompute($request->user());

        // Tell any active group-scoped assignment's teacher that this student
        // just completed it (dedupe'd, same semantics as the rollup).
        NotificationService::studentCompletedStory($request->user(), (int) $story->id);

        // A freshly completed assignment shouldn't leave its own notices sitting
        // in the bell dot — clear them now since the work is done.
        NotificationService::markCompletedAssignmentsRead($request->user(), (int) $story->id);

        $awarded = BadgeService::award(
            $request->user(),
            array_merge(
                ['stamp', 'freeze'],
                $result['attempt']->passed ? ['story'] : [],
                $result['attempt']->score_pct >= 100 && $result['attempt']->passed ? ['quiz'] : [],
            ),
            now(),
        );

        if ($awarded->isNotEmpty()) {
            $request->session()->flash('badges_awarded', BadgeService::summarize($awarded));
        }

        if (($result['stamp']['freeze_used'] ?? false) === true) {
            $request->session()->flash("freeze_used_{$result['attempt']->attempt_uuid}", true);
        }

        // This attempt just satisfied an active assignment: fire the one-shot
        // "Assignment complete" banner so it rides the JS redirect to the
        // results page. Only genuine false -> true transitions qualify.
        $freshAssignment = Assignment::activeForStory($request->user(), (int) $story->id)
            ->reject(fn (Assignment $assignment) => in_array($assignment->id, $alreadyComplete))
            ->first(fn (Assignment $assignment) => $assignment->completedByEvidence($request->user()));

        if ($freshAssignment !== null) {
            $request->session()->flash('assignment_complete', [
                'title' => $freshAssignment->title,
                'story_id' => (int) $story->id,
            ]);
        }

        return response()->json($this->resultPayload(
            $story,
            $result['attempt'],
            $result['earnings'],
            $result['stamp'],
            live: app(DailyTaskService::class)->syncSeen($request->user(), now(), $request),
        ));
    }

    /**
     * Dedicated quiz results page (Post/Redirect/Get). A read-only render of a
     * single settled attempt: ownership-gated, idempotent, and safe to refresh
     * or deep-link — nothing is paid or mutated here. Mirrors the reading
     * summary's reader-rank bar and the placement card's accuracy/review.
     */
    public function results(Request $request, Story $story, string $attemptUuid)
    {
        abort_unless($story->status === StoryStatus::Published, 404);

        $attempt = TestAttempt::query()
            ->where('user_id', $request->user()->id)
            ->where('story_id', $story->id)
            ->where('attempt_uuid', $attemptUuid)
            ->firstOrFail();

        $earnings = [
            'keys' => (int) $attempt->reward,
            'xp' => (int) $attempt->xp_awarded,
            'multiplier' => (float) ($attempt->answers_json['settlement']['multiplier']
                ?? RewardService::accuracyTier((float) $attempt->score_pct)),
            'reason' => $attempt->answers_json['settlement']['reason'] ?? $this->replayReason($attempt),
        ];

        $payload = $this->resultPayload(
            $story,
            $attempt,
            $earnings,
            ['stamped' => false, 'streak' => (int) $request->user()->current_streak],
            replayed: true,
        );

        $rank = PointsRank::for($request->user());
        $rankNow = (int) $rank['points'];
        $baseline = max(0, $rankNow - (int) $payload['xp']);

        $freezeUsed = (bool) $request->session()->pull("freeze_used_{$attemptUuid}", false);

        return view('student.quiz-results', [
            'story' => $story,
            'scorePct' => (int) $payload['score_pct'],
            'passed' => (bool) $payload['passed'],
            'correctCount' => (int) $payload['correct_count'],
            'wrongCount' => (int) $payload['wrong_count'],
            'pointsEarned' => (int) $payload['points_earned'],
            'pointsTotal' => (int) $payload['points_total'],
            'keys' => (int) $payload['keys'],
            'xp' => (int) $payload['xp'],
            'multiplier' => (float) $payload['multiplier'],
            'reason' => (string) $earnings['reason'],
            'streak' => (int) $payload['streak'],
            'freezeUsed' => $freezeUsed,
            'review' => $payload['review'],
            'backUrl' => (string) $payload['back_url'],
            'retakeUrl' => (string) $payload['retake_url'],
            'rank' => $rank,
            'rankBaseline' => $baseline,
            'rankNow' => $rankNow,
            'rankLadder' => collect(config('gamification.points.titles', []))
                ->sortBy(fn (array $step) => (int) $step['min_points'])
                ->values()
                ->map(fn (array $step) => ['name' => (string) $step['name'], 'min_points' => (int) $step['min_points']])
                ->all(),
            'titlePromotion' => $request->session()->pull('title_promotion'),
        ]);
    }

    /**
     * Hint tokens: charged IMMEDIATELY on use (user ruling - abandoned
     * quizzes keep spent hints). Every type gets a fair, non-misleading
     * nudge derived from the stored answer: MCQ strikes a wrong option,
     * identification shows the first letter + length, matching locks one
     * correct pair, sequence pins the first event. Never the full answer.
     */
    public function hint(Request $request, Story $story)
    {
        $data = $request->validate([
            'attempt_uuid' => ['required', 'uuid'],
            'question_id' => ['required', 'integer'],
        ]);

        $key = "quiz_session.{$story->id}";
        $session = $request->session()->get($key);

        if ($session === null || $session['uuid'] !== $data['attempt_uuid']) {
            return response()->json(['error' => 'stale_attempt'], 409);
        }

        $maxHints = (int) config('gamification.hint_tokens_per_quiz');
        $hintsUsed = (int) $session['hint_count'];

        if ($hintsUsed >= $maxHints) {
            return response()->json(['error' => 'no_hints_left'], 422);
        }

        $question = Question::query()
            ->whereIn('id', $session['question_ids'])
            ->whereKey($data['question_id'])
            ->firstOrFail();

        if ($question->type === QuestionType::TrueFalse) {
            return response()->json(['error' => 'not_hintable'], 422);
        }

        $action = $this->hintActionFor($question, collect($session['hints'][$data['question_id']] ?? []));

        if ($action === null) {
            return response()->json([
                'error' => 'nothing_to_hint',
                'message' => 'This question has no hint left to give.',
            ], 422);
        }

        $spent = RewardService::spend(
            $request->user(),
            (int) config('gamification.sinks.hint_token'),
            'hint_token',
            'story',
            $story->id,
            ['attempt_uuid' => $data['attempt_uuid'], 'question_id' => $question->id],
        );

        if (! $spent) {
            return response()->json([
                'error' => 'insufficient_keys',
                'message' => 'Not enough keys for a hint. Earn more by clearing quizzes.',
            ], 422);
        }

        $session['hints'][$data['question_id']][] = $action;
        $session['hint_count'] = $hintsUsed + 1;
        $request->session()->put($key, $session);

        return response()->json([
            'ok' => true,
            'balance' => (int) $request->user()->fresh()->total_keys,
            'hints_used' => $hintsUsed + 1,
            'hints_left' => $maxHints - $hintsUsed - 1,
        ] + $action);
    }

    /**
     * The per-type hint payload, or null when nothing honest is left to
     * reveal. Shapes double as the session record and the JSON response.
     *
     * @return array<string, mixed>|null
     */
    private function hintActionFor(Question $q, Collection $spent): ?array
    {
        return match ($q->type) {
            QuestionType::Mcq => $this->strikeAction($q, $spent),
            QuestionType::Identification => $this->clueAction($q),
            QuestionType::Matching => $this->pairAction($q, $spent),
            QuestionType::Sequence => $this->firstAction($q, $spent),
            QuestionType::TrueFalse => null,
        };
    }

    private function strikeAction(Question $q, Collection $spent): ?array
    {
        $alreadyStruck = $spent->where('kind', 'strike')
            ->pluck('letter')
            ->map(fn ($l) => strtolower((string) $l));

        // Strike a wrong option at random - never the correct one, never
        // one this attempt already struck.
        $correctLetter = strtolower($q->correct_option?->value ?? '');
        $letters = collect(['a' => $q->option_a, 'b' => $q->option_b, 'c' => $q->option_c, 'd' => $q->option_d])
            ->filter(fn ($text) => $text !== null && trim((string) $text) !== '')
            ->keys()
            ->reject(fn ($letter) => strtolower($letter) === $correctLetter)
            ->reject(fn ($letter) => $alreadyStruck->contains(strtolower($letter)))
            ->values();

        if ($letters->isEmpty()) {
            return null;
        }

        return ['kind' => 'strike', 'letter' => $letters->random()];
    }

    private function clueAction(Question $q): ?array
    {
        $primary = trim((string) (collect($q->acceptable_answers ?? [])->first() ?? ''));

        if ($primary === '') {
            return null;
        }

        $initial = mb_strtoupper(mb_substr($primary, 0, 1));
        $length = mb_strlen($primary);

        return [
            'kind' => 'clue',
            'clue' => "Starts with '{$initial}', {$length} ".($length === 1 ? 'letter' : 'letters').'.',
        ];
    }

    private function pairAction(Question $q, Collection $spent): ?array
    {
        $revealed = $spent->where('kind', 'pair')
            ->pluck('left')
            ->map(fn ($l) => mb_strtolower(trim((string) $l)));

        $pair = collect($q->match_pairs ?? [])
            ->first(fn ($p) => trim((string) ($p['left'] ?? '')) !== ''
                && ! $revealed->contains(mb_strtolower(trim((string) ($p['left'] ?? '')))));

        if ($pair === null) {
            return null;
        }

        return ['kind' => 'pair', 'left' => $pair['left'], 'right' => $pair['right']];
    }

    private function firstAction(Question $q, Collection $spent): ?array
    {
        $item = collect($q->sequence_items ?? [])->first();

        if ($item === null || $spent->contains(fn ($h) => ($h['kind'] ?? null) === 'first')) {
            return null;
        }

        return ['kind' => 'first', 'item' => (string) $item];
    }

    /**
     * Random balanced draw across sections (round-robin over shuffled
     * section groups); pool smaller than the recommended size uses all.
     *
     * @return Collection<int, Question>
     */
    private function drawQuestions(Story $story): Collection
    {
        $target = max((int) config('stories.quiz_recommended'), 1);

        $groups = Question::query()
            ->where('story_id', $story->id)
            ->get()
            ->groupBy(fn (Question $q) => $q->section_id ?? 0)
            ->map(fn ($group) => $group->shuffle()->values());

        $drawn = collect();
        $index = 0;

        while ($drawn->count() < $target) {
            $before = $drawn->count();

            foreach ($groups as $group) {
                if ($group->has($index)) {
                    $drawn->push($group[$index]);
                }
            }

            if ($drawn->count() === $before) {
                break;
            }

            $index++;
        }

        return $drawn->slice(0, $target)->values();
    }

    private function drawStillValid(array $session): bool
    {
        return isset($session['uuid'], $session['question_ids'])
            && Question::query()->whereIn('id', $session['question_ids'])->count() === count($session['question_ids']);
    }

    /**
     * Per-type display payload for the blade panels. Matching rights are
     * shuffled and sequence blocks scrambled here so refreshes render the
     * same arrangement (grading is text-based, so order never matters).
     */
    private function panelFor(Question $q, string $sectionName): array
    {
        return match ($q->type) {
            QuestionType::Mcq => [
                'id' => $q->id,
                'type' => $q->type->value,
                'text' => $q->question_text,
                'section' => $sectionName,
                'options' => collect(['a' => $q->option_a, 'b' => $q->option_b, 'c' => $q->option_c, 'd' => $q->option_d])
                    ->filter(fn ($text) => $text !== null && trim((string) $text) !== '')
                    ->map(fn (string $text, string $letter) => ['letter' => $letter, 'text' => $text])
                    ->values()
                    // Each entry carries its true letter, so re-ordering the
                    // displayed options never changes how answers are graded.
                    ->shuffle()
                    ->all(),
            ],
            QuestionType::TrueFalse => [
                'id' => $q->id,
                'type' => $q->type->value,
                'text' => $q->question_text,
                'section' => $sectionName,
            ],
            QuestionType::Identification => [
                'id' => $q->id,
                'type' => $q->type->value,
                'text' => $q->question_text,
                'section' => $sectionName,
            ],
            QuestionType::Matching => [
                'id' => $q->id,
                'type' => $q->type->value,
                'text' => $q->question_text,
                'section' => $sectionName,
                'lefts' => collect($q->match_pairs ?? [])->pluck('left')->all(),
                'rights' => collect($q->match_pairs ?? [])->pluck('right')->shuffle()->values()->all(),
            ],
            QuestionType::Sequence => [
                'id' => $q->id,
                'type' => $q->type->value,
                'text' => $q->question_text,
                'section' => $sectionName,
                'scrambled' => $this->scrambleSequence(collect($q->sequence_items ?? [])->all()),
            ],
        };
    }

    /** Scramble that never accidentally leaves the correct order intact. */
    private function scrambleSequence(array $items): array
    {
        $items = array_values($items);

        if (count($items) < 2) {
            return $items;
        }

        $scrambled = collect($items)->shuffle()->values()->all();

        while ($scrambled === $items) {
            $scrambled = collect($items)->shuffle()->values()->all();
        }

        return $scrambled;
    }

    /**
     * Partial-credit scoring: MCQ/TF/identification are worth 1 point,
     * matching one per correct pair, sequence one per item in its exact
     * position. Returns [earnedPoints, totalPoints, fullyCorrectQuestions].
     *
     * @return array{0: float, 1: int, 2: int}
     */
    private function score($questions, array $submitted): array
    {
        $earned = 0.0;
        $points = 0;
        $fullyCorrect = 0;

        foreach ($submitted as $entry) {
            $question = $questions[$entry['question_id'] ?? null] ?? null;

            if ($question === null) {
                continue;
            }

            $got = $this->pointsFor($question, $entry['value'] ?? null);
            $worth = $this->pointsWorth($question);

            $earned += min(max($got, 0), $worth);
            $points += $worth;

            if ($got >= $worth) {
                $fullyCorrect++;
            }
        }

        return [$earned, $points, $fullyCorrect];
    }

    private function pointsWorth(Question $q): int
    {
        return match ($q->type) {
            QuestionType::Matching => count($q->match_pairs ?? []),
            QuestionType::Sequence => count($q->sequence_items ?? []),
            default => 1,
        };
    }

    private function pointsFor(Question $q, mixed $value): float
    {
        return match ($q->type) {
            QuestionType::Mcq => $this->sameLetter($value, $q->correct_option?->value) ? 1.0 : 0.0,
            QuestionType::TrueFalse => $this->sameLetter($value, $q->correct_option?->value) ? 1.0 : 0.0,
            QuestionType::Identification => $this->matchesAcceptable($value, $q->acceptable_answers ?? []) ? 1.0 : 0.0,
            QuestionType::Matching => $this->matchedPairs($q, $value),
            QuestionType::Sequence => $this->positionsCorrect($q, $value),
        };
    }

    private function sameLetter(mixed $value, ?string $correct): bool
    {
        return is_string($value) && strtolower(trim($value)) === strtolower((string) $correct);
    }

    /**
     * Identification grading mirrors the teacher preview's normalizer:
     * case, spacing and trailing punctuation never decide right/wrong.
     */
    private function matchesAcceptable(mixed $value, array $acceptable): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $given = self::normalizeText($value);

        foreach ($acceptable as $candidate) {
            if ($given === self::normalizeText((string) $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{left: string, right: string}> */
    private function integrityFlags(array $data, int $timeSpent, int $questionCount): ?array
    {
        $raw = $data['integrity'] ?? [];

        $flags = IntegritySignals::buildFlags($raw);

        // The server is the source of truth for timing — never trust the
        // client's plausible-speed signal alone against its own clock.
        if ($timeSpent > 0 && $questionCount > 0) {
            $minPlausible = $questionCount * IntegritySignals::SPEED_SECONDS_PER_QUESTION;
            if ($timeSpent < $minPlausible) {
                $flags['speed_implausible'] = [
                    'time' => $timeSpent,
                    'questions' => $questionCount,
                    'min_plausible' => $minPlausible,
                ];
            }
        }

        return $flags !== [] ? $flags : null;
    }

    private function submittedPairs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $pairs = [];

        foreach ($value as $pair) {
            if (is_array($pair) && isset($pair['left']) && is_string($pair['left'])) {
                $pairs[] = ['left' => $pair['left'], 'right' => (string) ($pair['right'] ?? '')];
            }
        }

        return $pairs;
    }

    private function matchedPairs(Question $q, mixed $value): float
    {
        $chosen = collect($this->submittedPairs($value))
            ->unique(fn ($p) => mb_strtolower(trim($p['left'])))
            ->mapWithKeys(fn ($p) => [mb_strtolower(trim($p['left'])) => trim((string) $p['right'])]);

        $correct = 0.0;

        foreach ($q->match_pairs ?? [] as $pair) {
            $key = mb_strtolower(trim((string) $pair['left']));
            $want = trim((string) $pair['right']);

            if ($chosen->has($key) && $chosen->get($key) === $want) {
                $correct += 1.0;
            }
        }

        return $correct;
    }

    private function positionsCorrect(Question $q, mixed $value): float
    {
        if (! is_array($value)) {
            return 0.0;
        }

        $correct = $q->sequence_items ?? [];
        $hits = 0.0;

        foreach (array_values($value) as $i => $item) {
            if (isset($correct[$i]) && is_string($item) && trim($item) === trim((string) $correct[$i])) {
                $hits += 1.0;
            }
        }

        return $hits;
    }

    private static function normalizeText(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return rtrim($value, " \t\n\r.!?,;:");
    }

    /** Stored audit trail: what was submitted and whether it scored. */
    private function answerAudit($questions, array $submitted): array
    {
        $audit = [];

        foreach ($submitted as $entry) {
            $question = $questions[$entry['question_id'] ?? null] ?? null;

            if ($question === null) {
                continue;
            }

            $audit[] = [
                'question_id' => $question->id,
                'value' => $entry['value'] ?? null,
                'earned' => $this->pointsFor($question, $entry['value'] ?? null),
                'worth' => $this->pointsWorth($question),
            ];
        }

        return $audit;
    }

    private function isDuplicateAttempt(QueryException $e): bool
    {
        return (string) ($e->errorInfo[1] ?? '') === '1062';
    }

    /**
     * Legacy rows (or anything predating the settlement snapshot) still get
     * an honest reason reconstructed from the ledger facts on disk.
     */
    private function replayReason(TestAttempt $attempt): string
    {
        if ((int) $attempt->reward > 0) {
            return 'quiz_first_clear';
        }

        $earlierExists = TestAttempt::query()
            ->where('user_id', $attempt->user_id)
            ->where('story_id', $attempt->story_id)
            ->where('id', '<', $attempt->id)
            ->exists();

        if (! $earlierExists) {
            return 'quiz_first_attempt';
        }

        $earlierBest = (float) (TestAttempt::query()
            ->where('user_id', $attempt->user_id)
            ->where('story_id', $attempt->story_id)
            ->where('id', '<', $attempt->id)
            ->max('score_pct') ?? 0);

        return (float) $attempt->score_pct > $earlierBest ? 'quiz_improvement' : 'quiz_no_reward';
    }

    /**
     * Replayed POSTs are settled already, so there is nothing new to claim.
     * Still carry the live counters so the result screen can paint the correct
     * claimable badge / dot without inventing a false "completed right now".
     */
    private function liveTaskState(Request $request): array
    {
        $state = app(DailyTaskService::class)->state($request->user(), now());

        return [
            'completed_now' => [],
            'tasks' => [
                'claimable' => $state['claimable'],
                'in_progress' => $state['in_progress'],
            ],
        ];
    }

    private function resultPayload(Story $story, TestAttempt $attempt, array $earnings, array $stamp, bool $replayed = false, array $live = []): array
    {
        $base = RewardService::bandRewards(AliceLibrary::difficultyBand((int) $story->reading_level));
        [$review, $pointsEarned, $pointsTotal] = $this->buildReview($attempt);

        return [
            'ok' => true,
            'replayed' => $replayed,
            'score_pct' => (int) $attempt->score_pct,
            'passed' => (bool) $attempt->passed,
            'correct_count' => (int) $attempt->correct_count,
            'wrong_count' => (int) $attempt->wrong_count,
            'points_earned' => $pointsEarned,
            'points_total' => $pointsTotal,
            'pass_threshold' => (int) config('gamification.pass_threshold_pct'),
            'review' => $review,
            'keys' => (int) $earnings['keys'],
            'xp' => (int) $earnings['xp'],
            'multiplier' => (float) $earnings['multiplier'],
            'reason' => $earnings['reason'],
            'breakdown' => [
                'base_keys' => (int) $base['keys'],
                'base_xp' => (int) $base['xp'],
                'tier_multiplier' => RewardService::accuracyTier((float) $attempt->score_pct),
                'same_day_multiplier' => (float) $earnings['multiplier'],
            ],
            'stamped' => (bool) ($stamp['stamped'] ?? false),
            'streak' => (int) ($stamp['streak'] ?? 0),
            'retake_url' => route('reader.quiz', $story),
            'back_url' => route('story.preview', $story),
            'results_url' => route('reader.quiz.results', [$story, $attempt->attempt_uuid]),
            'live' => $live,
        ];
    }

    /**
     * Answer-review payload for the post-quiz results screen. Built from the
     * stored audit trail (submitted values + per-question earned/worth) so a
     * replayed POST shows the exact same review. Points come from the same
     * item sums, keeping "X of Y points" perfectly in step with score_pct.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: int}
     */
    private function buildReview(TestAttempt $attempt): array
    {
        $items = $attempt->answers_json['items'] ?? [];
        $questions = Question::query()
            ->whereIn('id', collect($items)->pluck('question_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $review = [];
        $earned = 0.0;
        $total = 0;

        foreach ($items as $item) {
            $question = $questions[$item['question_id'] ?? null] ?? null;

            if ($question === null) {
                continue;
            }

            $itemEarned = (float) ($item['earned'] ?? 0);
            $itemWorth = (int) ($item['worth'] ?? 0);
            $earned += min(max($itemEarned, 0), max($itemWorth, 0));
            $total += max($itemWorth, 0);

            $review[] = [
                'prompt' => (string) $question->question_text,
                'type' => $question->type->shortLabel(),
                'earned' => (float) $itemEarned,
                'worth' => $itemWorth,
                'correct' => $itemWorth > 0 && $itemEarned >= $itemWorth,
                'submitted' => $this->formatSubmitted($question, $item['value'] ?? null),
                'expected' => $this->formatExpected($question),
            ];
        }

        return [$review, (int) round($earned), $total];
    }

    private function formatExpected(Question $q): string
    {
        return match ($q->type) {
            QuestionType::Mcq => $this->formatSelectedLetter($q, $q->correct_option?->value),
            QuestionType::TrueFalse => mb_strtoupper((string) $q->correct_option?->value) === 'A' ? 'TRUE' : 'FALSE',
            QuestionType::Identification => (function (array $answers): string {
                $answers = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $answers), fn ($v) => $v !== ''));
                if ($answers === []) {
                    return '—';
                }

                return implode(', ', array_slice($answers, 0, 3)).(count($answers) > 3 ? '…' : '');
            })($q->acceptable_answers ?? []),
            QuestionType::Matching => collect($q->match_pairs ?? [])
                ->map(fn ($p) => trim((string) ($p['left'] ?? '')).' → '.trim((string) ($p['right'] ?? '')))
                ->filter(fn ($row) => ! str_starts_with($row, ' → '))
                ->implode('; '),
            QuestionType::Sequence => collect($q->sequence_items ?? [])->map(fn ($v) => (string) $v)->implode(' → '),
        };
    }

    private function formatSubmitted(Question $q, mixed $value): string
    {
        return match ($q->type) {
            QuestionType::Mcq => $this->formatSelectedLetter($q, $value),
            QuestionType::TrueFalse => is_string($value) && trim($value) !== ''
                ? (mb_strtoupper(trim($value)) === 'A' ? 'TRUE' : 'FALSE')
                : '—',
            QuestionType::Identification => is_string($value) && trim($value) !== '' ? (string) $value : '—',
            QuestionType::Matching => (function (mixed $value, Question $q): string {
                if (! is_array($value) || $value === []) {
                    return '—';
                }

                $correct = collect($q->match_pairs ?? [])
                    ->mapWithKeys(fn ($p) => [mb_strtolower(trim((string) $p['left'])) => trim((string) $p['right'])]);

                return collect($this->submittedPairs($value))
                    ->map(function ($p) use ($correct) {
                        $want = $correct->get(mb_strtolower(trim((string) $p['left'])));
                        $ok = $want !== null && $want === trim((string) $p['right']);

                        return trim((string) $p['left']).' → '.trim((string) $p['right']).($ok ? ' ✓' : ' ✗');
                    })
                    ->filter(fn ($row) => ! str_starts_with($row, ' → '))
                    ->implode('; ');
            })($value, $q),
            QuestionType::Sequence => (function (mixed $value): string {
                if (! is_array($value) || $value === []) {
                    return '—';
                }

                return collect($value)->map(fn ($v) => (string) $v)->implode(' → ');
            })($value),
        };
    }

    /** Own answer formatter: a letter plus its option text, or just the letter. */
    private function formatSelectedLetter(Question $q, mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        $letter = mb_strtoupper(trim($value));
        $text = (string) ($q->{'option_'.mb_strtolower($letter)} ?? '');

        return $text !== '' ? $letter.'. '.$text : $letter;
    }
}
