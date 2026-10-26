<?php

namespace App\Http\Controllers\Student;

use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Http\Controllers\Student\Concerns\ReadsStories;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\KeyTransaction;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\StoryReport;
use App\Models\UserDailyActivity;
use App\Services\BadgeService;
use App\Services\DailyTaskService;
use App\Services\ReadingProgressService;
use App\Services\RewardService;
use App\Services\TitleRewardService;
use App\Support\AliceLibrary;
use App\Support\LibraryPresentation;
use App\Support\Narration;
use App\Support\NotificationService;
use App\Support\PointsRank;
use App\Support\StoryPagination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The student reader (STUDENT_PAGES_PLAN §4.3). State is
 * server-authoritative: position and time live in story_progress, so a
 * refresh resumes exactly where the student was - nothing durable lives
 * only in the browser.
 *
 * Reading is continuous-scroll (webnovel style): the full story loads at
 * once, progress is tracked via scroll percentage, and narration plays
 * all segments sequentially with auto-scroll follow-along.
 */
class ReaderController extends Controller
{
    use ReadsStories;

    /**
     * Absolute anti-cheat ceiling on any single heartbeat's claimed seconds.
     * This is a safety cap for a hostile/lying client only — it is NOT the
     * intended size of a normal beat. Real reading time is measured on the
     * client in per-window increments (see reader.js sendBeat) and arrives in
     * small real-seconds deltas; this bound simply prevents one request from
     * ever granting a whole minute (or more) in a single shot.
     */
    private const MAX_DELTA_SECONDS = 60;

    /** A session must span this much time to qualify for a daily stamp. */
    private const STAMP_MIN_SECONDS = 60;

    /** Scroll percentage at which the story is considered read. */
    private const SCROLL_READ_THRESHOLD = 95;

    public function show(Request $request, Story $story)
    {
        $this->assertReadable($story);

        if (trim((string) $story->content) === '') {
            abort(404);
        }

        $pages = StoryPagination::pages($story->content);
        $pageList = $pages['pages'];
        $pageContinues = $pages['continues'];
        $totalPages = max(count($pageList), 1);

        $progress = StoryProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'story_id' => $story->id],
            ['current_page' => 1],
        );

        $savedWords = SavedWord::query()
            ->where('user_id', $request->user()->id)
            ->pluck('word')
            ->all();

        // C3 summary points bar baseline: how many points the student had when
        // this (user, story) reading run started in this login session. It is
        // snapshotted ONCE per (user, story) per session — never re-taken on a
        // mid-session refresh/reopen — so a first-clear banked by a heartbeat
        // always lands inside the "earned this session" tally window and the
        // summary card can never disagree with the point bar. (Keyed by user so
        // the admin view-as switcher can't leak one student's baseline to another.)
        $baselineKey = "summary_baseline.{$request->user()->id}.{$story->id}";
        if (! $request->session()->has($baselineKey)) {
            $request->session()->put($baselineKey, (int) $request->user()->points);
        }

        $narrationSegments = Narration::forStory($story, $pageList);

        // Field Journal battle (demo: missions_enabled stories only). The
        // battle row is created lazily; a won battle swaps JOURNAL for FINISH.
        $missionsEnabled = (bool) $story->missions_enabled;
        $battleState = null;
        if ($missionsEnabled) {
            $battle = app(\App\Games\Journal\BattleService::class)->stateFor($request->user(), $story);
            $battleState = \App\Games\Journal\BattleService::payload($battle);
        }

        // Field Journal wardrobe: full catalog + the reader's state (owned,
        // equipped, keys) ship with the page so the skins tab renders live
        // with no extra round-trip; buy/equip update via JSON endpoints.
        $skinsCatalog = config('skins');
        $skinsCatalog['background'] = config('backgrounds.list');
        $skinsState = \App\Http\Controllers\Student\SkinsController::state($request->user());
        $equippedAlice = $skinsCatalog['alice'][$skinsState['equipped']['alice']];
        $equippedBoss = $skinsCatalog['boss'][$skinsState['equipped']['boss']];

        return view('student.reader', [
            'story' => $story,
            'pageList' => $pageList,
            'pageContinues' => $pageContinues,
            'totalPages' => $totalPages,
            'progress' => $progress,
            'isFavorite' => (bool) $progress->is_favorite,
            'hasQuiz' => $story->questions()->exists(),
            'minutes' => LibraryPresentation::minutes((int) $story->word_count),
            'band' => AliceLibrary::difficultyBand((int) $story->reading_level),
            'savedWords' => $savedWords,
            'nextStory' => $this->suggestionsFor($story, 1)->first(),
            'narrationSegments' => $narrationSegments,
            'missionsEnabled' => $missionsEnabled,
            'battleState' => $battleState,
            'battleComplete' => $battleState !== null && $battleState['status'] === 'won',
            'skinsPayload' => ['catalog' => $skinsCatalog, 'state' => $skinsState],
            'equippedAlice' => $equippedAlice,
            'equippedBoss' => $equippedBoss,
        ]);
    }

    /**
     * Heartbeat: scroll percentage + clamped seconds delta. Progress only
     * moves forward (never regresses). After 60s of cumulative reading the
     * day is stamped once; reaching 95% scroll marks the story read.
     */
    public function heartbeat(Request $request, Story $story)
    {
        $data = $request->validate([
            'scroll_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'seconds_delta' => ['required', 'integer', 'min:0'],
        ]);

        $result = DB::transaction(function () use ($request, $story, $data) {
            $progress = StoryProgress::query()
                ->where('user_id', $request->user()->id)
                ->where('story_id', $story->id)
                ->lockForUpdate()
                ->firstOrFail();

            $delta = min(max((int) $data['seconds_delta'], 0), self::MAX_DELTA_SECONDS);
            $scrollPct = min(max((int) $data['scroll_pct'], 0), 100);

            $session = $request->session()->get("read_session.{$story->id}", [
                'seconds' => 0, 'stamped_date' => null,
            ]);

            $session['seconds'] += $delta;

            $stampedNow = false;
            $freezeUsed = false;

            if ($session['stamped_date'] !== now()->toDateString()
                && $session['seconds'] >= self::STAMP_MIN_SECONDS) {
                $stamp = RewardService::stampDay($request->user(), 'read', now());
                $session['stamped_date'] = now()->toDateString();
                $stampedNow = true;
                $freezeUsed = (bool) ($stamp['freeze_used'] ?? false);

                $awarded = BadgeService::award($request->user(), ['read_stamp', 'stamp', 'reading', 'freeze'], now());
                if ($awarded->isNotEmpty()) {
                    $request->session()->flash('badges_awarded', BadgeService::summarize($awarded));
                }
            }

            $request->session()->put("read_session.{$story->id}", $session);

            // Progress only moves forward — never regresses on refresh/reload.
            $updates = [];

            if ($scrollPct > (int) $progress->current_page) {
                $updates['current_page'] = $scrollPct;
            }

            if ($delta > 0) {
                $updates['time_spent_seconds'] = DB::raw('time_spent_seconds + '.$delta);
            }

            if ($scrollPct >= self::SCROLL_READ_THRESHOLD
                && $progress->status !== StoryProgressStatus::Completed) {
                $updates['status'] = StoryProgressStatus::Read->value;
            }

            // Real reading evidence — seconds on the page or reaching the read
            // threshold — stamps `completed_at`, which assignment completion
            // gates on. It is never cleared and never back-dated: a story
            // finished (or re-read) after an assignment went live counts as
            // fresh work, while a replayed beat just re-stamps "last seen".
            if ($delta > 0 || isset($updates['status'])) {
                $updates['completed_at'] = now();
            }

            if ($updates !== []) {
                $progress->update($updates);
            }

            $progress->refresh();

            // Story-clear (C2): settled on ANY end-state beat, so a story
            // flipped to Read before the session minimum is never stuck — the
            // next beat once cumulative time qualifies banks it. The min-session
            // gate, the daily cap, and the ledger keep it honest and once-only.
            // The reveal note survives so finish() can surface the card even
            // when the heartbeat did the paying (a flash can't ride a JSON beat).
            $storyCleared = false;
            if ($progress->status === StoryProgressStatus::Read) {
                $settle = RewardService::settleStoryClear(
                    $request->user(),
                    $story->id,
                    (int) $progress->time_spent_seconds,
                    now(),
                    AliceLibrary::difficultyBand((int) $story->reading_level),
                );
                $storyCleared = (bool) ($settle['paid'] ?? false);

                if ($storyCleared) {
                    $request->session()->put("read_session.{$story->id}.story_clear", [
                        'keys' => (int) ($settle['keys'] ?? 0),
                        'xp' => (int) ($settle['xp'] ?? 0),
                    ]);
                }
            }

            // Accumulate daily reading seconds for the goal tracker, then
            // credit the reading-points delta (S1). Reading points are a pure
            // function of stored daily seconds (hard-capped), so replaying a
            // beat never double-pays.
            if ($delta > 0) {
                $activity = UserDailyActivity::query()
                    ->where('user_id', $request->user()->id)
                    ->where('activity_date', now()->toDateString())
                    ->lockForUpdate()
                    ->first();

                // Ensure the day row exists so the very first beat's seconds
                // are never silently dropped (which would under-count the
                // goal meter and reading points by that window).
                if ($activity === null) {
                    $activity = UserDailyActivity::create([
                        'user_id' => $request->user()->id,
                        'activity_date' => now()->toDateString(),
                        'reading_seconds_today' => 0,
                    ]);
                }

                $before = (int) $activity->reading_seconds_today;
                $activity->increment('reading_seconds_today', $delta);
                $newTotal = $before + $delta;

                RewardService::awardReadingPoints($request->user(), $newTotal, $before);
            }

            // Live task notification (Phase E): after this beat's activity lands,
            // diff today's task state against what the last interaction saw, so
            // the reader can raise a "task complete — claim it!" banner exactly
            // once per transition. The fresh shape is stored back to the session
            // so a replay or page refresh never double-fires the same completion.
            $live = app(DailyTaskService::class)->syncSeen($request->user(), now(), $request);

            return [
                'stamped_now' => $stampedNow,
                'freeze_used' => $freezeUsed,
                'story_cleared' => $storyCleared,
                'time_spent_seconds' => (int) $progress->refresh()->time_spent_seconds,
                'status' => $progress->status,
                'completed_now' => $live['completed_now'],
                'tasks' => $live['tasks'],
            ];
        });

        return response()->json(['ok' => true] + $result);
    }

    /**
     * Quiz entry lives in QuizController (D2 player). The B2 gate and the
     * no-questions 404 remain enforced there.
     */

    /**
     * MARK AS READ (B3): the finish-line button. Reaching the last page
     * already marks the story read via heartbeat; this makes it certain,
     * pins the read to 100%, and hands off to the celebration ceremony.
     * Never downgrades a passed quiz, never stamps (stamping stays
     * heartbeat-driven).
     */
    public function finish(Request $request, Story $story)
    {
        $this->assertReadable($story);

        // Field Journal hard gate: a mission story cannot be finished until
        // its battle is won (client hides the button too; this is the rule).
        if ($story->missions_enabled) {
            $battle = \App\Models\StoryBattle::query()
                ->where('user_id', $request->user()->id)
                ->where('story_id', $story->id)
                ->first();

            if (! $battle || ! $battle->isWon()) {
                return redirect()
                    ->route('reader.show', $story)
                    ->with('toast.message', 'Defeat Bruno in the Field Journal first, then finish.');
            }
        }

        $progress = StoryProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('story_id', $story->id)
            ->first();

        if (! $progress) {
            return redirect()
                ->route('story.preview', $story)
                ->with('toast.message', 'Open the story first. Mark it read after you start reading.');
        }

        // Which active assignments on this story were already satisfied before
        // this finish? Diffed against the post-finish state below so the
        // one-shot "Assignment complete" banner only fires on the true
        // false -> true transition (a re-read of an already-done story never
        // re-celebrates). Evidence-only: a teacher's manual "mark done" must
        // not suppress the student's genuine completion celebration.
        $alreadyComplete = Assignment::activeForStory($request->user(), (int) $story->id)
            ->filter(fn (Assignment $assignment) => $assignment->completedByEvidence($request->user()))
            ->pluck('id')
            ->all();

        // Finish always pins the read to 100%: anything except a passed quiz
        // (Completed is never downgraded) becomes Read with the progress bar at
        // 100, so the summary can never show less once the finish button was hit.
        if ($progress->status !== StoryProgressStatus::Completed) {
            $progress->update([
                'status' => StoryProgressStatus::Read->value,
                'current_page' => max((int) $progress->current_page, 100),
                'completed_at' => now(),
            ]);
            $progress->refresh();

            // Story-clear (C2): settled here too, whether the story flipped now
            // or the heartbeat already marked it Read — the real-reading-time
            // check, daily cap, and ledger keep it once and honest. The reward
            // card is revealed when this settle pays, or when the heartbeat
            // banked it earlier this session (via the note). A plain rerun pays
            // nothing and stays quiet.
            $settled = RewardService::settleStoryClear(
                $request->user(),
                $story->id,
                (int) $progress->time_spent_seconds,
                now(),
                AliceLibrary::difficultyBand((int) $story->reading_level),
            );

            if ($settled['paid']) {
                $request->session()->forget("read_session.{$story->id}.story_clear");
                $earned = ['keys' => $settled['keys'], 'xp' => $settled['xp']];
            } else {
                $earned = $request->session()->pull("read_session.{$story->id}.story_clear");
            }

            if ($earned !== null) {
                $request->session()->flash('story_clear_award', $earned);
            }
        } else {
            // Re-finishing an already-cleared story is still real reading
            // evidence: keep `completed_at` current so a reread after the
            // assignment went live counts as fresh work (the "must re-engage"
            // rule), while advance-read completions stay untouched.
            $progress->update(['completed_at' => now()]);
            $progress->refresh();
        }

        // Finishing a story is real reading evidence — nudge the student's
        // live reading progress so the bar reflects their reading habit too.
        ReadingProgressService::recompute($request->user());

        // Tell any active group-scoped assignment's teacher that this student
        // just finished it (dedupe'd, same semantics as the rollup).
        NotificationService::studentCompletedStory($request->user(), (int) $story->id);

        // A freshly completed assignment shouldn't leave its own "new/due/overdue"
        // notices sitting in the bell dot — clear them now since the work is done.
        NotificationService::markCompletedAssignmentsRead($request->user(), (int) $story->id);

        // A finish that completes a story-only (no-quiz) assignment flips the
        // banner now; quiz assignments are completed by the quiz itself, so
        // they stay silent here. The flash rides the PRG redirect.
        $freshAssignment = Assignment::activeForStory($request->user(), (int) $story->id)
            ->reject(fn (Assignment $assignment) => in_array($assignment->id, $alreadyComplete))
            ->first(fn (Assignment $assignment) => $assignment->completedByEvidence($request->user()));

        if ($freshAssignment !== null) {
            $request->session()->flash('assignment_complete', [
                'title' => $freshAssignment->title,
                'story_id' => (int) $story->id,
            ]);
        }

        // Field Journal: finishing the story resets the battle so the student
        // can replay it next visit (fresh action set, full hearts/HP).
        if ($story->missions_enabled) {
            \App\Models\StoryBattle::query()
                ->where('user_id', $request->user()->id)
                ->where('story_id', $story->id)
                ->delete();
        }

        return redirect()->route('reader.summary', $story);
    }

    /**
     * Post-read summary page: celebration greeting + reading time vs
     * daily goal, streak state, and suggested books.  Gated — students
     * who never opened the story bounce back to the preview.
     */
    public function summary(Request $request, Story $story)
    {
        $this->assertReadable($story);

        $progress = StoryProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('story_id', $story->id)
            ->first();

        if (! $progress) {
            return redirect()
                ->route('story.preview', $story)
                ->with('toast.message', 'Open the story first. Its summary unlocks after you start reading.');
        }

        $user = $request->user();

        $baseline = (int) $request->session()->get("summary_baseline.{$user->id}.{$story->id}", $user->points);
        $gained = (int) $user->points - $baseline;

        // No-reward disclosure: when the session banked literally nothing (no
        // first-clear card and no points gained), explain HOW rewards are
        // actually earned instead of leaving a bare "+0". The card always wins
        // the tie (a card implies a paid clear this login, which the put-once
        // baseline necessarily predates). Two honest flavors:
        //   - skipped: never cleared and no real reading time banked -> a
        //     finish-URL skip / instant jump to the end; nudge to actually read.
        //   - no_new: everything else that still banked zero (already cleared,
        //     or the daily 3-clear cap already hit).
        $noRewardReason = null;
        if ($request->session()->get('story_clear_award') === null && $gained <= 0) {
            $everCleared = KeyTransaction::query()
                ->where('user_id', $user->id)
                ->where('reason', 'story_clear')
                ->where('subject_type', 'story')
                ->where('subject_id', $story->id)
                ->exists();

            $noRewardReason = $everCleared || (int) $progress->time_spent_seconds > 0
                ? 'no_new'
                : 'skipped';
        }

        // A read story always reads 100% — legacy/heartbeat rows where the
        // scroll % lags behind the status can never under-report a finished book.
        $finished = $progress->status === StoryProgressStatus::Read
            || $progress->status === StoryProgressStatus::Completed;

        // Reading points accrue via per-minute heartbeats (which never pay a
        // ceremony mid-read); surface any pending title promotion here, once per
        // session end. Idempotent + locked inside congratulate(), so concurrent
        // summary loads can never double-pay.
        $titlePromotion = TitleRewardService::congratulate($request->user(), now());

        return view('student.summary', [
            'story' => $story,
            'progress' => $progress,
            'readPct' => $finished ? 100 : min((int) $progress->current_page, 100),
            'streak' => (int) $user->current_streak,
            'longestStreak' => (int) $user->longest_streak,
            'pointsBaseline' => $baseline,
            'pointsNow' => (int) $user->points,
            'rank' => PointsRank::for($user),
            'baselineRank' => PointsRank::forPoints($baseline),
            'rankLadder' => collect(config('gamification.points.titles', []))
                ->sortBy(fn (array $step) => (int) $step['min_points'])
                ->values()
                ->map(fn (array $step) => ['name' => (string) $step['name'], 'min_points' => (int) $step['min_points']])
                ->all(),
            'storyClearAward' => $request->session()->get('story_clear_award'),
            'noRewardReason' => $noRewardReason,
            'suggestions' => $this->suggestionsFor($story, 5),
            'titlePromotion' => $titlePromotion,
        ]);
    }

    /**
     * PATCH /read/{slug}/preferences — persist reader preferences
     * (theme, font, spacing, no_line_break) into the user's JSON column.
     */
    public function preferences(Request $request, Story $story): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['nullable', 'string', 'in:default,dracula,green'],
            'font' => ['nullable', 'string', 'in:default,lexend,atkinson,opendyslexic,literata,patrick'],
            'spacing' => ['nullable', 'string', 'in:narrow,normal,wide'],
            'no_line_break' => ['nullable', 'boolean'],
            'font_size' => ['nullable', 'integer', 'in:0,1,2,3,4'],
        ]);

        $user = $request->user();
        $prefs = $user->preferences ?? [];

        foreach ($data as $key => $value) {
            $prefs[$key] = $value;
        }

        $user->update(['preferences' => $prefs]);

        return response()->json(['ok' => true, 'preferences' => $prefs]);
    }

    /**
     * POST /read/{slug}/report — store a story report. The reader submits
     * this from the in-page modal (fetch, JSON); a non-JS fallback still
     * gets a normal redirect back to the reader.
     */
    public function reportStore(Request $request, Story $story): RedirectResponse|JsonResponse
    {
        $this->assertReadable($story);

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:typo,content,other'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $report = StoryReport::create([
            'user_id' => $request->user()->id,
            'story_id' => $story->id,
            'category' => $validated['category'],
            'description' => $validated['description'],
        ]);

        NotificationService::reportCreated($report);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('reader.show', $story)
            ->with('toast.message', 'Thank you! Your report has been submitted.');
    }

    /** Same band, different flavour: up to $limit story suggestions. */
    private function suggestionsFor(Story $story, int $limit = 5): Collection
    {
        $floor = AliceLibrary::bandFloor((int) $story->reading_level);
        $ceiling = AliceLibrary::bandCeiling((int) $story->reading_level);
        $genreIds = $story->genres()->pluck('genres.id');

        // Prefer different-genre stories first, then fall back to any same-band.
        return Story::query()
            ->where('status', StoryStatus::Published->value)
            ->whereKeyNot($story->getKey())
            ->whereBetween('reading_level', [$floor, $ceiling])
            ->whereDoesntHave('genres', fn ($q) => $q->whereIn('genres.id', $genreIds))
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->when(count($genreIds), fn ($col) => $col->count() >= $limit ? $col : $col->concat(
                Story::query()
                    ->where('status', StoryStatus::Published->value)
                    ->whereKeyNot($story->getKey())
                    ->whereBetween('reading_level', [$floor, $ceiling])
                    ->whereKeyNot($col->pluck('id'))
                    ->inRandomOrder()
                    ->limit($limit - $col->count())
                    ->get()
            ))
            ->take($limit);
    }
}
