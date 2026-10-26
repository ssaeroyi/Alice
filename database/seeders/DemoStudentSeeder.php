<?php

namespace Database\Seeders;

use App\Enums\ComprehensionLevel;
use App\Enums\DailyTaskType;
use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Enums\UserDailyTaskStatus;
use App\Models\Assignment;
use App\Models\DailyTask;
use App\Models\GameRound;
use App\Models\Group;
use App\Models\ReadingProgress;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Models\UserDailyTask;
use App\Models\WordGymDeck;
use App\Services\BadgeService;
use App\Services\DailyTaskService;
use App\Services\RewardService;
use App\Services\TitleRewardService;
use App\Support\NotificationService;
use App\Support\SchoolYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One lived-in demo student so a reviewer (or the classroom) can sign in and
 * see how the Hub looks after about two months of steady use.
 *
 * Every number the Hub reads is real domain data: reading minutes live in
 * user_daily_activities, quiz accuracy in test_attempts, titles and keys in
 * the economy ledger (settles + grants only), badges in user_badges, stamps in
 * the stamped calendar cells, classwork in real assignments. Nothing is
 * hand-faked to a stat card.
 *
 * Idempotency: all rows are updateOrCreate/firstOrCreate on natural keys
 * (email, user+date, attempt_uuid, round_id, slug). Every key movement passes
 * through RewardService so `total_keys` always equals the sum of
 * key_transactions, exactly like a real student.
 */
class DemoStudentSeeder extends Seeder
{
    use WithoutModelEvents;

    public const DEMO_STUDENT_EMAIL = 'demo.student@example.com';

    public const DEMO_STUDENT_PASSWORD = 'password';

    private Carbon $now;

    public function run(): void
    {
        $this->now = Carbon::today()->startOfDay();

        $this->callOnce(DailyTaskSeeder::class);
        $this->callOnce(BadgeSeeder::class);

        $teacher = $this->upsertUser('demo.teacher@example.com', [
            'first_name' => 'Dana',
            'last_name' => 'Carter',
            'nickname' => 'Ms. Carter',
            'role' => Role::Teacher,
            'is_setup_complete' => true,
        ]);

        $demo = $this->upsertUser(self::DEMO_STUDENT_EMAIL, [
            'nickname' => 'Alex',
            'role' => Role::Student,
            'grade_level' => GradeLevel::Grade8,
            'grade_anchor_year' => SchoolYear::startYear(),
            'is_setup_complete' => true,
            'comprehension_level' => ComprehensionLevel::Instructional,
            'comprehension_bar' => 82,
            'reading_level' => 8,
            'daily_reading_goal' => 20,
            'daily_study_goal' => 10,
            'last_login_at' => $this->now->copy()->subHours(3),
        ]);

        $classmates = collect(range(1, 3))->map(fn (int $n) => $this->upsertUser(
            'demo.classmate'.$n.'@example.com',
            [
                'nickname' => ['Maya', 'Sam', 'Noah'][$n - 1],
                'role' => Role::Student,
                'grade_level' => GradeLevel::Grade8,
                'grade_anchor_year' => SchoolYear::startYear(),
                'is_setup_complete' => true,
            ],
        ));

        $stories = $this->ensureStories();
        $offsets = $this->stampedOffsets();

        // The history block below only builds the lived-in record once. On a
        // re-seed (same account already has activity rows) it is skipped so
        // the economy settles can never pay keys twice; every other step stays
        // idempotent through its own anchors (updateOrCreate, claim guards,
        // ledger dedupe, notification dedupe).
        $alreadySeeded = UserDailyActivity::query()->where('user_id', $demo->id)->exists();

        if (! $alreadySeeded) {
            // ------------------------------------------------------------------
            // 1. Stamp replay (chronological). This owns the streak math: each
            //    stamped day advances/blows the current streak exactly like the
            //    real engine, so the 6-day current streak, 12-day longest streak
            //    and the single consumed freeze all fall out of the schedule.
            // ------------------------------------------------------------------
            foreach ($offsets as $offset) {
                RewardService::stampDay($demo, 'read', $this->stampedAt($offset));
            }

            // ------------------------------------------------------------------
            // 2. Story history: 6 completed (quiz-passed) + 1 in progress.
            // ------------------------------------------------------------------
            $clears = [
                0 => -41,
                1 => -34,
                2 => -30,
                3 => -26,
                4 => -19,
                5 => -13,
            ];

            foreach ($clears as $i => $offset) {
                $story = $stories[$i];
                $day = $this->day($offset);

                StoryProgress::updateOrCreate(
                    ['user_id' => $demo->id, 'story_id' => $story->id],
                    [
                        'status' => StoryProgressStatus::Completed,
                        'is_favorite' => in_array($i, [1, 3], true),
                        'time_spent_seconds' => 480 + $i * 60,
                        'current_page' => 0,
                        'best_score_pct' => [86, 100, 78, 96, 72, 100][$i],
                        'completed_at' => $day,
                    ],
                );

                $minutes = 14 + $i;
                $row = UserDailyActivity::updateOrCreate(
                    ['user_id' => $demo->id, 'activity_date' => $day->toDateString()],
                    ['reading_seconds_today' => $minutes * 60, 'read_sessions' => 1],
                );
                $row->stamped_at ??= $day->copy()->setTime(15, 30);
                $row->save();

                RewardService::settleStoryClear(
                    $demo,
                    $story->id,
                    $minutes * 60,
                    $day,
                    $story->difficulty_label ?: 'Intermediate',
                );
            }

            // In-progress story (the Continue Reading card + "in progress" shelf).
            $open = $stories[6];
            $openProgress = StoryProgress::updateOrCreate(
                ['user_id' => $demo->id, 'story_id' => $open->id],
                [
                    'status' => StoryProgressStatus::Reading,
                    'current_page' => 9,
                    'time_spent_seconds' => 540,
                    'best_score_pct' => null,
                    'completed_at' => null,
                ],
            );
            $openProgress->timestamps = false;
            $openProgress->forceFill(['updated_at' => $this->now->copy()->addMinutes(20)])->save();

            // ------------------------------------------------------------------
            // 3. Quiz attempts (one per "first try" storyline, retakes on 4).
            // ------------------------------------------------------------------
            $attempts = [
                // [story index, day offset, score pct, previous best]
                [0, -42, 65, null],
                [0, -41, 86, 65],
                [1, -34, 92, null],
                [1, -33, 100, 92],
                [2, -29, 58, null],
                [2, -30, 78, 58],
                [3, -26, 88, null],
                [3, -25, 96, 88],
                [4, -19, 72, null],
                [5, -13, 100, null],
            ];

            foreach ($attempts as [$storyIndex, $offset, $pct, $previousBest]) {
                $story = $stories[$storyIndex];
                $day = $this->day($offset);

                $attempt = TestAttempt::where('user_id', $demo->id)
                    ->where('attempt_uuid', 'demo-quiz-'.$story->id.'-'.$offset)
                    ->first();

                if ($attempt === null) {
                    $attempt = TestAttempt::create([
                        'user_id' => $demo->id,
                        'story_id' => $story->id,
                        'attempt_uuid' => 'demo-quiz-'.$story->id.'-'.$offset,
                        'score_pct' => $pct,
                        'passed' => $pct >= (int) config('gamification.pass_threshold_pct'),
                        'correct_count' => (int) round($pct / 100 * 20),
                        'wrong_count' => 20 - (int) round($pct / 100 * 20),
                        'time_spent_seconds' => 240,
                        'answers_json' => ['demo' => true],
                        'attempt_flags' => [],
                        'reward' => 0,
                        'xp_awarded' => 0,
                        'hint_tokens_used' => 0,
                    ]);
                }

                $attempt->timestamps = false;
                $attempt->forceFill(['created_at' => $day, 'updated_at' => $day])->save();

                RewardService::settleQuizAttempt(
                    $demo,
                    $story->id,
                    $story->difficulty_label ?: 'Intermediate',
                    $pct,
                    $previousBest,
                    $day->copy()->addHours(1),
                );
            }

            // ------------------------------------------------------------------
            // 4. Mini-game rounds across all 3 types (score is 0-100).
            // ------------------------------------------------------------------
            $rounds = [
                [1, 'wordsearch', -40, 92],
                [2, 'wordsearch', -40, 84],
                [3, 'wordsearch', -40, 75],
                [4, 'match', -16, 90],
                [5, 'match', -16, 70],
                [6, 'match', -16, 55],
                [7, 'sentence', -13, 95],
                [8, 'sentence', -13, 80],
                [9, 'sentence', -13, 65],
                [10, 'wordsearch', -19, 88],
                [11, 'wordsearch', -10, 78],
                [12, 'match', -10, 82],
                [13, 'sentence', -4, 88],
                [14, 'wordsearch', -4, 70],
                [15, 'match', -4, 60],
            ];

            foreach ($rounds as [$n, $type, $offset, $score]) {
                $day = $this->day($offset);

                GameRound::updateOrCreate(
                    ['user_id' => $demo->id, 'round_id' => 'demo-round-'.$n],
                    [
                        'game_type' => $type,
                        'score' => $score,
                        'words_count' => 10,
                        'created_at' => $day->copy()->setTime(16, 0),
                    ],
                );

                RewardService::settleGameRound(
                    $demo,
                    $type,
                    $score,
                    'demo-round-'.$n,
                    10,
                    $day->copy()->setTime(16, 0),
                );
            }

            // ------------------------------------------------------------------
            // 5. Word Gym: one deck, 30 saved words, 14 reviewed (5 drill days
            //    plus 3 reviewed today). Today's 5 new saves keep the Learn task.
            // ------------------------------------------------------------------
            $deck = WordGymDeck::updateOrCreate(
                ['user_id' => $demo->id, 'name' => 'My words'],
                ['position' => 1],
            );

            $words = collect([
                'murmur', 'gloomy', 'spare', 'shadow', 'fragile', 'wander', 'hollow',
                'venture', 'meadow', 'glimpse', 'tremble', 'pale', 'stoop', 'lantern',
                'brisk', 'quiver', 'drowsy', 'candor', 'gleam', 'falter', 'hearth',
                'morrow', 'somber', 'trudge', 'gully', 'pensive', 'tarnish', 'thicket',
                'whittle', 'veer',
            ]);

            $drillDays = [
                -25 => [0, 1],
                -22 => [2, 3],
                -15 => [4, 5, 6],
                -9 => [7, 8],
                -3 => [9, 10],
                0 => [11, 12, 13],
            ];

            foreach ($drillDays as $offset => $indexes) {
                $day = $this->day($offset);
                foreach ($indexes as $i) {
                    $this->saveWord($deck, $words[$i], $i, $day);
                }
                RewardService::settleDrill($demo, $deck->id, true, $day->copy()->setTime(17, 0));
            }

            // The rest were saved while reading (no review yet). Two of those land
            // today so the Learn-words task sees 5 fresh saves for the day (3
            // drilled this morning on the block above + these 2).
            foreach ($words->slice(14)->values() as $k => $word) {
                $index = $k + 14;
                $this->saveWord(
                    $deck,
                    $word,
                    $index,
                    in_array($index, [14, 15], true) ? $this->now : $this->day(-6),
                    in_array($index, [14, 15], true) ? $this->now->copy()->setTime(7, 30) : null,
                );
            }

            // ------------------------------------------------------------------
            // 6. Exact daily counters (overwrite the stamp/round increments with
            //    the true per-day totals, keeping stamped_at intact).
            // ------------------------------------------------------------------
            $quizzesByDay = collect($attempts)->groupBy(fn (array $a) => $this->day($a[1])->toDateString())
                ->map->count()
                ->all();
            $gamesByDay = collect($rounds)->groupBy(fn (array $r) => $this->day($r[2])->toDateString())
                ->map->count()
                ->all();

            foreach ($offsets as $offset) {
                $day = $this->day($offset);
                $key = $day->toDateString();

                UserDailyActivity::updateOrCreate(
                    ['user_id' => $demo->id, 'activity_date' => $key],
                    [
                        'read_sessions' => 1,
                        'reading_seconds_today' => $this->readingMinutesFor($offset) * 60,
                        'quiz_submissions' => (int) ($quizzesByDay[$key] ?? 0),
                        'game_rounds' => (int) ($gamesByDay[$key] ?? 0),
                    ],
                );
            }

            // Bookend stamps for the early-bird / night-owl badges: the freeze-day
            // stamp at 06:20 and the last run-B stamp at 22:10.
            $early = $this->day(-43);
            $late = $this->day(-24);
            UserDailyActivity::where('user_id', $demo->id)->whereDate('activity_date', $early->toDateString())
                ->update(['stamped_at' => $early->copy()->setTime(6, 20)]);
            UserDailyActivity::where('user_id', $demo->id)->whereDate('activity_date', $late->toDateString())
                ->update(['stamped_at' => $late->copy()->setTime(22, 10)]);

            // ------------------------------------------------------------------
            // 7. Today: the reading goal plus the three daily tasks (one claimed,
            //    one ready to claim, one still in the starting blocks).
            // ------------------------------------------------------------------
            if ($demo->daily_reading_goal > 0) {
                RewardService::creditGoalReward($demo, 'reading', $this->now->copy()->setTime(18, 30));
            }

            $taskRows = [];
            $taskTypes = [DailyTaskType::ReadStory, DailyTaskType::LearnWords, DailyTaskType::PlayGame];
            foreach ($taskTypes as $type) {
                $task = DailyTask::query()->where('type', $type->value)->where('active', true)->first();
                if ($task === null) {
                    continue;
                }

                $taskRows[$type->value] = UserDailyTask::updateOrCreate(
                    ['user_id' => $demo->id, 'daily_task_id' => $task->id, 'task_date' => $this->now->toDateString()],
                    ['progress' => 0, 'status' => UserDailyTaskStatus::Pending, 'claimed_at' => null],
                );
            }

            // Claim the reading task (today's read session already meets its target).
            $readTask = $taskRows[DailyTaskType::ReadStory->value] ?? null;
            if ($readTask !== null) {
                app(DailyTaskService::class)->claim($demo, $readTask, $this->now);
            }

            // The learn-words task is complete from today's 5 saved words.
            $learnTask = $taskRows[DailyTaskType::LearnWords->value] ?? null;
            if ($learnTask !== null && $learnTask->status !== UserDailyTaskStatus::Claimed) {
                $learnTask->updateOrFail([
                    'progress' => SavedWord::query()->where('user_id', $demo->id)->whereDate('created_at', $this->now->toDateString())->count(),
                    'status' => UserDailyTaskStatus::Completed,
                ]);
            }

        } // ! $alreadySeeded (history built once)

        // ------------------------------------------------------------------
        // 8. Points snapshot at 2450 (Word Sage), then let the ceremony pay
        //    the promotion keys, rank badges and inbox rows for every tier
        //    crossed since the last celebration.
        // ------------------------------------------------------------------
        $demo->forceFill(['points' => 2450])->save();
        TitleRewardService::congratulate($demo, $this->now->copy()->addMinutes(15));

        BadgeService::award(
            $demo,
            ['story', 'reading', 'quiz', 'word', 'game', 'stamp', 'freeze', 'read_stamp', 'drill'],
            $this->now->copy()->addMinutes(15),
        );

        // Veteran's inbox: every achievement row is read (backdated a few days
        // so the flow reads naturally); the new assignment stays unread on top.
        $backdated = $this->now->copy()->subDays(6);
        DB::table('notifications')
            ->where('user_id', $demo->id)
            ->where('type', 'like', 'achievement.%')
            ->update(['read_at' => $backdated, 'created_at' => $backdated, 'updated_at' => $backdated]);

        // Reading level row (the live evidence mirror).
        ReadingProgress::updateOrCreate(
            ['user_id' => $demo->id],
            [
                'live_score' => 82,
                'teacher_score' => 78,
                'comprehension_level' => ComprehensionLevel::Instructional,
                'reading_grade' => 8,
                'sample_count' => 12,
                'distinct_grades' => 2,
                'last_activity_at' => $this->now->copy()->subHours(5),
                'computed_at' => $this->now->copy()->subHours(5),
                'trend' => 'steady',
                'slipping' => false,
            ],
        );

        // ------------------------------------------------------------------
        // 9. Classwork: one archived (finished) assignment the demo completed,
        //    one fresh active assignment the demo has NOT started.
        // ------------------------------------------------------------------
        $group = Group::updateOrCreate(
            ['teacher_id' => $teacher->id, 'name' => 'Grade 8 - Mercury'],
            ['join_code' => 'DEMO8', 'section_label' => 'Mercury'],
        );
        $group->addMember($demo);
        foreach ($classmates as $classmate) {
            $group->addMember($classmate);
        }

        $past = Assignment::firstOrCreate(
            ['teacher_id' => $teacher->id, 'story_id' => $stories[0]->id, 'group_id' => $group->id, 'scope' => 'group'],
            [
                'title' => 'Reading Check: '.$stories[0]->title,
                'instructions' => 'Read the story to the end and take the quiz. Take your time and reread any part you find tricky.',
                'due_at' => $this->day(-28)->copy()->setTime(15, 0),
                'assigned_at' => $this->day(-45)->copy()->setTime(9, 0),
                'status' => 'archived',
                'finished_at' => $this->day(-15)->copy()->setTime(16, 0),
            ],
        );
        $past->timestamps = false;
        $past->forceFill(['created_at' => $this->day(-45)->copy()->setTime(9, 0)])->save();

        $fresh = Assignment::firstOrCreate(
            ['teacher_id' => $teacher->id, 'story_id' => $stories[7]->id, 'group_id' => $group->id, 'scope' => 'group'],
            [
                'title' => 'Vocabulary Hunt: '.$stories[7]->title,
                'instructions' => 'Read this story, save 3 interesting words, and take the quiz when you finish.',
                'due_at' => $this->now->copy()->addDays(4)->setTime(15, 0),
                'assigned_at' => $this->now->copy()->subDays(2)->setTime(9, 0),
                'status' => 'active',
                'finished_at' => null,
            ],
        );
        $fresh->timestamps = false;
        $fresh->forceFill(['created_at' => $this->now->copy()->subDays(2)->setTime(9, 0)])->save();

        $demo->update(['last_viewed_assignment_at' => $this->now->copy()->subDays(6)]);
        NotificationService::assignmentNew($fresh);

        // ------------------------------------------------------------------
        // 10. Final polish: freezes back to the display cap, and print creds.
        // ------------------------------------------------------------------
        $demo->refresh();
        $demo->update(['streak_freezes' => (int) config('gamification.freeze.hold_cap')]);

        $this->command?->info('Demo student ready: '.self::DEMO_STUDENT_EMAIL.' / '.self::DEMO_STUDENT_PASSWORD);
        $this->command?->info(
            'Hub: '.$demo->points.' points, '.$demo->total_keys.' keys, '.$demo->current_streak.' day streak, '
            .$demo->longest_streak.' best streak, '.BadgeService::progressMap($demo, $this->now)['stories_5'].'% toward 5 stories.'
        );
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    private function upsertUser(string $email, array $attrs): User
    {
        $base = [
            'email' => $email,
            'email_verified_at' => $this->now->copy()->subMonths(2),
            'password' => Hash::make(self::DEMO_STUDENT_PASSWORD),
        ];

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $user->update(array_merge($base, $attrs));

            return $user->fresh();
        }

        return User::create(array_merge($base, $attrs));
    }

    /**
     * Published library stories (oldest first), topped up with tiny fallback
     * stories when the library is empty (fresh migrations / test DBs).
     *
     * @return Collection<int, Story>
     */
    private function ensureStories(): Collection
    {
        if (Story::query()->where('status', StoryStatus::Published)->count() < 8) {
            for ($n = 1; $n <= 8; $n++) {
                Story::firstOrCreate(
                    ['slug' => 'demo-reading-check-'.$n],
                    [
                        'title' => 'Demo Reading Check '.$n,
                        'author' => 'ALICE Team',
                        'license' => 'Public Domain',
                        'reading_level' => 8,
                        'word_count' => 480,
                        'difficulty_label' => 'Intermediate',
                        'status' => StoryStatus::Published,
                        'content' => implode("\n\n", array_fill(0, 8, 'This is a short paragraph of the demo reading check '.$n.' used to give the demo student something to read, quiz and progress through.')),
                    ],
                );
            }
        }

        return Story::query()->where('status', StoryStatus::Published)->orderBy('id')->take(8)->get()->values();
    }

    private function day(int $offset): Carbon
    {
        return $this->now->copy()->addDays($offset);
    }

    /**
     * The hour-correct stamp timestamp for a scheduled day (06:20 on the
     * freeze day, 22:10 on the night-owl day, 15:30 otherwise).
     */
    private function stampedAt(int $offset): Carbon
    {
        $time = match ($offset) {
            -43 => [6, 20],
            -24 => [22, 10],
            default => [15, 30],
        };

        return $this->day($offset)->setTime($time[0], $time[1]);
    }

    /**
     * The stamp calendar (offsets from today), each run sized to the streak
     * maths: run A (5) with a single covered gap that consumes one freeze --
     * the 06:20 stamp after a missed day -- run B (12) that sets the longest
     * streak, a few scattered bookends, then the final 6-day run ending today
     * (the current streak).
     */
    private function stampedOffsets(): array
    {
        return collect([...[-45, -43, -42, -41, -40], ...range(-35, -24), ...[-19, -16, -13, -10], ...range(-5, 0)])
            ->sort()
            ->values()
            ->all();
    }

    private function readingMinutesFor(int $offset): int
    {
        $minutes = [
            -45 => 14, -43 => 22, -42 => 18, -41 => 16, -40 => 20,
            -35 => 15, -34 => 18, -33 => 22, -32 => 12, -31 => 25,
            -30 => 17, -29 => 19, -28 => 24, -27 => 16, -26 => 21,
            -25 => 14, -24 => 28,
            -19 => 18, -16 => 23, -13 => 20, -10 => 15,
            -5 => 16, -4 => 19, -3 => 24, -2 => 13, -1 => 18,
            0 => 23,
        ];

        return $minutes[$offset] ?? 15;
    }

    private function saveWord(WordGymDeck $deck, string $word, int $i, Carbon $day, ?Carbon $savedAtOverride = null): SavedWord
    {
        $reviewed = $i < 14;
        $savedAt = $savedAtOverride ?? ($reviewed ? $day->copy()->setTime(7, 30) : $day->copy()->subDays(2));

        $entry = SavedWord::updateOrCreate(
            ['user_id' => $deck->user_id, 'word' => $word],
            [
                'deck_id' => $deck->id,
                'definition' => 'Definition of '.$word,
                'part_of_speech' => $i % 3 === 2 ? 'adjective' : 'noun',
                'phonetic' => null,
                'example_sentence' => null,
                'lookup_count' => 1 + ($i % 4),
                'times_correct' => $reviewed ? 1 + (($i * 5) % 4) : 0,
                'times_wrong' => 0,
                'mastery_level' => $reviewed ? (($i + 3) * 5) % 100 : 0,
                'last_drilled_at' => $reviewed ? $day->copy()->setTime(17, 0) : null,
                'due_at' => $reviewed ? $day->copy()->setTime(17, 0)->addDays(($i % 4) > 1 ? 3 : 1) : $day->copy()->addDays(2),
            ],
        );

        $entry->timestamps = false;
        $entry->forceFill(['created_at' => $savedAt, 'updated_at' => $savedAt])->save();

        return $entry;
    }
}
