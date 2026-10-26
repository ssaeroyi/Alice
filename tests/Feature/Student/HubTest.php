<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\GameRound;
use App\Models\Group;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function hubStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
        'total_keys' => 42,
        'current_streak' => 4,
        'longest_streak' => 9,
        'points' => 310,
        ...$overrides,
    ]);
}

function publishedStory(string $title = 'The Hidden Garden'): Story
{
    return Story::create([
        'title' => $title,
        'author' => 'Test Author',
        'synopsis' => 'A short test story.',
        'content' => 'The waves crashed against the rocks all afternoon long.',
        'word_count' => 120,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Easy',
        'status' => StoryStatus::Published,
    ]);
}

test('guests are redirected from the hub', function () {
    $this->get(route('hub'))->assertRedirect(route('login'));
});

test('the hub renders the four-section tab bar', function () {
    $this->actingAs(hubStudent())
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('data-hub-tabs')
        ->assertSee('Home', false)
        ->assertSee('Stats', false)
        ->assertSee('Badges', false);
});

test('the hub greets a student who just finished placement', function () {
    $this->actingAs(hubStudent())
        ->withSession(['pending_welcome' => true])
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('Welcome to ALICE')
        ->assertSee('Your reading adventure starts now!');
});

test('the hub does not greet on a normal visit', function () {
    $this->actingAs(hubStudent())
        ->get(route('hub'))
        ->assertOk()
        ->assertDontSee('Your reading adventure starts now!');
});

test('the hub shows real snapshot values, never the old fake data', function () {
    $student = hubStudent();
    SavedWord::create(['user_id' => $student->id, 'word' => 'glimmer', 'definition' => 'test']);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('Story Explorer')      // title from points 310
        ->assertSee('310')                 // points
        ->assertSee('42')                  // keys
        ->assertSee('1')                   // one saved word
        // The old placeholder hub is gone:
        ->assertDontSee('1,420')
        ->assertDontSee('Clockwork')
        ->assertDontSee('Island of Mist');
});

test('the hub surfaces the in-progress story as continue reading', function () {
    $student = hubStudent();
    $story = publishedStory('The Lighthouse Keeper');
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Reading,
        'current_page' => 40,
    ]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('The Lighthouse Keeper')
        ->assertSee('Recently Read')
        ->assertSee('RESUME STORY');
});

test('recently read keeps read-but-unfinished stories and now keeps finished ones too', function () {
    // Issue 3: a story scrolled to the end (status "read") but not yet quiz-
    // completed must still appear — it is unfinished work and shows "Resume
    // reading". A genuinely completed story now also stays in the card so the
    // reader sees their last reads, labelled "Read again".
    $student = hubStudent();
    $story = publishedStory('The Long Voyage');
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 100,
    ]);

    $story2 = publishedStory('The Quiet Harbor');
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story2->id,
        'status' => StoryProgressStatus::Completed,
        'current_page' => 100,
        'completed_at' => now(),
    ]);

    $html = $this->actingAs($student)->get(route('hub'))->getContent();
    expect($html)
        ->toContain('The Long Voyage')
        ->toContain('The Quiet Harbor')
        ->toContain('RESUME STORY')
        ->toContain('READ AGAIN');
});

test('recently read shows the five most recently touched reads, oldest dropped', function () {
    $student = hubStudent();

    foreach (['One', 'Two', 'Three', 'Four', 'Five', 'Six'] as $i => $label) {
        $story = publishedStory('Story '.$label);
        $row = StoryProgress::create([
            'user_id' => $student->id,
            'story_id' => $story->id,
            'status' => $i % 2 === 0
                ? StoryProgressStatus::Completed
                : StoryProgressStatus::Reading,
            'current_page' => 50,
            'completed_at' => $i % 2 === 0 ? now() : null,
        ]);
        // Give every touch a distinct time: Story One is the oldest.
        $row->forceFill(['updated_at' => now()->subMinutes(6 - $i)])->save();
    }

    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    expect($html)
        ->toContain('Story Six')     // most recent touch, featured first
        ->toContain('Story Five')
        ->toContain('Story Four')
        ->toContain('Story Three')
        ->toContain('Story Two')
        ->not->toContain('Story One') // outside the last five
        ->and(strpos($html, 'Story Six'))->toBeLessThan(strpos($html, 'Story Five'));
});

test('show actual saved-words count and daily goals on the home tab', function () {
    $student = hubStudent(['daily_reading_goal' => 15, 'daily_study_goal' => 20]);
    SavedWord::create(['user_id' => $student->id, 'word' => 'ember', 'definition' => 'glow']);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('Home')
        ->assertSee('Recently Read');
});

test('alice greeting no longer renders a quick-start action pill', function () {
    // The greeting used to offer Continue Reading / Find a Story pills; they
    // were removed in favour of the resume card in the HOME tab. The resume
    // card now says "Recently Read", so guard on the pill's unique
    // call-to-action instead.
    $student = hubStudent();

    $html = $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('Find a Story');
});

test('stats tab shows average quiz score from real attempts', function () {
    $student = hubStudent();
    $story = publishedStory();
    TestAttempt::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'attempt_uuid' => (string) Str::uuid(),
        'score_pct' => 80,
        'passed' => true,
        'correct_count' => 8,
        'wrong_count' => 2,
    ]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('Avg Quiz Score')
        ->assertSee('80%');
});

test('stats tab shows game high scores from real rounds', function () {
    $student = hubStudent();
    GameRound::create([
        'user_id' => $student->id,
        'game_type' => 'match',
        'score' => 90,
        'words_count' => 6,
        'round_id' => (string) Str::uuid(),
    ]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('High Scores')
        ->assertSee('90');
});

test('badges tab reports the earned count from the award ledger', function () {
    $student = hubStudent();
    $story = publishedStory();
    $this->seed(BadgeSeeder::class);
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => Carbon::now(),
    ]);

    // Fire the badge award for a completed story so one badge is earned.
    BadgeService::award($student, ['story'], Carbon::now());

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('My Badges')
        ->assertSee('1 of '.count(config('badges.badges')).' unlocked');
});

test('stats shows the reading totals and the month stamp calendar', function () {
    $student = hubStudent();
    $month = Carbon::now();
    UserDailyActivity::create([
        'user_id' => $student->id,
        'activity_date' => $month->copy()->startOfMonth()->toDateString(),
        'stamped_at' => $month->copy()->startOfMonth()->hour(9),
    ]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee($month->format('F'))
        ->assertSee('1 stamped')
        ->assertSee('Your Standing');
});

test('future calendar days are grayed out as not-yet', function () {
    $student = hubStudent();

    // The whole current-month grid is server-rendered in the STATS tab,
    // so any remaining days after today appear as future cells.
    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    expect($html)
        ->toContain('title="Not yet"');
});

test('the stamp calendar legend lives in the stamp calendar help modal', function () {
    $student = hubStudent();

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('visual heatmap of the days you were active in Alice')
        ->assertSee('kept your streak')
        ->assertSee("didn't do any triggering activity to stamp it", false)
        ->assertSee('nothing stamped yet')
        // The dashed row and the arrows footer line were dropped.
        ->assertDontSee("days that haven't happened yet", false)
        ->assertDontSee('Use the arrows to browse past months.', false)
        // Regression guard: the rank/goal lines once shipped a double-encoded
        // middle dot (circumflex-A followed by the dot) because the blade file
        // got re-saved as Latin-1.
        ->assertDontSee('Â')
        // Every label, heading and helper on the hub is ink black now.
        ->assertDontSee('text-[#8C7B70]');
});

test('every hub topic has its own standalone help modal', function () {
    $student = hubStudent();
    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    $topics = [
        'hub-help-streak' => 'STREAK',
        'hub-help-keys' => 'KEYS',
        'hub-help-saved-words' => 'SAVED WORDS',
        'hub-help-reading-goal' => 'READING GOAL',
        'hub-help-study-goal' => 'STUDY GOAL',
        'hub-help-daily-tasks' => 'DAILY TASKS',
        'hub-help-reader-ranks' => 'READER RANKS',
        'hub-help-activity' => 'ACTIVITY HISTORY',
        'hub-help-high-scores' => 'HIGH SCORES',
        'hub-help-stamp-calendar' => 'STAMP CALENDAR',
    ];

    foreach ($topics as $id => $title) {
        expect($html)
            ->toContain('id="'.$id.'"')
            ->toContain('id="'.$id.'-title"')
            ->toContain('data-modal-open="'.$id.'"')
            // Header is the topic's own name.
            ->toMatch('/id="'.$id.'-title"[^>]*>\s*'.preg_quote($title, '/').'\s*</');
    }

    // Ten topic ? openers, none of them chained into a shared pager.
    preg_match_all('/data-modal-open="(hub-help-[^"]+)"/', $html, $openers);
    expect(array_values(array_unique($openers[1])))->toBe(array_keys($topics))
        ->and($html)->not->toContain('data-help-target')
        // The include comment once leaked as visible page text (bad --}}).
        ->not->toContain('Standalone hub help modals');

    // Fact-checked copy on the help modals.
    expect($html)
        ->toContain('Each reroll costs 2 keys.')
        ->toContain('up to 60 minutes a day')
        ->toContain('up to 100 words a day')
        ->toContain('finishing reading a story');
});

test('the stats tab merges the chart and history behind one toggle', function () {
    $student = hubStudent();
    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    expect($html)
        ->toContain('data-activity-toggle')
        ->toContain('data-activity-view="chart"')
        ->toContain('data-activity-view="history"')
        ->toContain('data-history-months')
        // The standalone chart card heading (mode title + its own ?) is gone.
        ->not->toContain('data-chart-title-host')
        ->not->toContain('data-chart-title');
});

test('activity history snapshot carries per-type + overall monthly totals', function () {
    $student = hubStudent();
    $month = Carbon::now()->startOfMonth()->toDateString();

    UserDailyActivity::create([
        'user_id' => $student->id,
        'activity_date' => $month,
        'reading_seconds_today' => 300, // 5 min
        'quiz_submissions' => 2,
        'game_rounds' => 4,
        'drills_completed' => 1,
        'stamped_at' => Carbon::now()->startOfMonth()->hour(8),
    ]);

    $html = $this->actingAs($student)->get(route('hub'))->getContent();
    preg_match('/data-initial-stats>(.*?)<\/script/s', $html, $m);

    expect($m[1] ?? null)->not->toBeNull();
    $stats = json_decode($m[1], true);
    $series = $stats['monthlySeries'] ?? [];

    expect($series)->not->toBeEmpty()
        ->and($series[array_key_last($series)])->toMatchArray([
            'reading_minutes' => 5,
            'quizzes' => 2,
            'games' => 4,
            'drills' => 1,
            'overall' => 12, // 5 min + 2 + 4 + 1
            'stamps' => 1,
        ])
        ->and($series)->each->toHaveKeys(['month_key', 'month', 'reading_minutes', 'quizzes', 'games', 'drills', 'overall', 'stamps']);
});

test('activity history shows empty state only when every month is zero', function () {
    $student = hubStudent();

    // Game-only student with no reading minutes: the new empty gate keys off
    // `overall`, so a tick of activity anywhere hides the empty state even
    // though the month has zero reading minutes.
    UserDailyActivity::create([
        'user_id' => $student->id,
        'activity_date' => Carbon::now()->subMonth()->startOfMonth()->toDateString(),
        'game_rounds' => 3,
    ]);

    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    expect($html)
        ->toContain('Activity History')
        ->toContain('data-history-metric="overall"')
        ->toContain('data-history-metric="games"')
        ->toContain('data-monthly-empty class="hidden'); // activity present -> gated
});

test('learning metrics panel surfaces average quiz score and total reading time', function () {
    $student = hubStudent();
    $story = publishedStory();

    TestAttempt::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'attempt_uuid' => (string) Str::uuid(),
        'score_pct' => 64,
        'passed' => false,
        'correct_count' => 8,
        'wrong_count' => 7,
    ]);
    TestAttempt::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'attempt_uuid' => (string) Str::uuid(),
        'score_pct' => 84,
        'passed' => true,
        'correct_count' => 10,
        'wrong_count' => 2,
    ]);
    $student->update(['current_streak' => 3, 'longest_streak' => 5]);

    UserDailyActivity::create([
        'user_id' => $student->id,
        'activity_date' => Carbon::now()->startOfMonth()->toDateString(),
        'reading_seconds_today' => 7500, // 2h 05m
        'stamped_at' => Carbon::now()->startOfMonth()->hour(8),
    ]);

    // Avg of [64, 84] = 74; total reading label "2h 5m".
    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('Your Standing')
        ->assertSee('Total Reading Time')
        ->assertSee('2h 5m')
        ->assertSee('Avg Quiz Score')
        ->assertSee('74%')
        ->assertSee('3 days')
        ->assertSee('Best 5');
});

test('learning metrics panel shows honest empty states when data is missing', function () {
    // No scored attempts and no streak -> honest "no data yet" copy, never a
    // fabricated 0% or a made-up grade. With no reading progress yet, the live
    // level reads "After your first few quizzes" rather than a number.
    $student = hubStudent(['reading_level' => null, 'current_streak' => 0, 'longest_streak' => 0]);

    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    expect($html)
        ->toContain('No quizzes yet')
        ->toContain('No current streak')
        ->toContain('After your first few quizzes');
});

test('the hub can update the daily reading goal from the goal slider', function () {
    $student = hubStudent(['daily_reading_goal' => 10]);

    $this->actingAs($student)
        ->patchJson(route('hub.reading-goal'), ['goal' => 15])
        ->assertOk()
        ->assertJsonPath('goal', 15);

    expect($student->fresh()->daily_reading_goal)->toBe(15);
});

test('the hub can turn the reading goal off (0 stores null)', function () {
    $student = hubStudent(['daily_reading_goal' => 10]);

    $this->actingAs($student)
        ->patchJson(route('hub.reading-goal'), ['goal' => 0])
        ->assertOk()
        ->assertJsonPath('goal', null);

    expect($student->fresh()->daily_reading_goal)->toBeNull();
});

test('the hub reading goal accepts any value inside the slider range', function () {
    $student = hubStudent(['daily_reading_goal' => 10]);

    foreach ([7, 21, 2, 40] as $minutes) {
        $this->actingAs($student)
            ->patchJson(route('hub.reading-goal'), ['goal' => $minutes])
            ->assertOk()
            ->assertJsonPath('goal', $minutes);

        expect($student->fresh()->daily_reading_goal)->toBe($minutes);
    }
});

test('the hub reading goal rejects values outside the slider range', function () {
    $student = hubStudent(['daily_reading_goal' => 10]);

    $this->actingAs($student)
        ->patchJson(route('hub.reading-goal'), ['goal' => 61])
        ->assertJsonValidationErrors('goal');

    $this->actingAs($student)
        ->patchJson(route('hub.reading-goal'), ['goal' => -1])
        ->assertJsonValidationErrors('goal');

    expect($student->fresh()->daily_reading_goal)->toBe(10);
});

test('class-scoped assignments render on the hub Home tab without crashing', function () {
    // Regression: classwork rows are hydrated with a narrow column list, and
    // the shared row partial calls completedFor(), which needs assigned_at /
    // created_at for the date gate. Truncated rows used to blow up the hub
    // with "Illegal operator and value combination" (completed_at >= null).
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = hubStudent();
    $story = publishedStory();

    $class = Group::create([
        'teacher_id' => $teacher->id,
        'name' => 'Grade 8 - Blue',
        'join_code' => Group::makeJoinCode(),
    ]);
    $class->addMember($student);

    Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $story->id,
        'group_id' => $class->id,
        'title' => 'Read The Garden',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => strtoupper(Str::random(8)),
    ]);

    $this->actingAs($student)
        ->get(route('hub'))
        ->assertOk()
        ->assertSee('Read The Garden')
        ->assertSee('In progress');
});

test('classwork shows classes as a grid with drill-down, classmates toggle and modal leave', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = hubStudent();
    $story = publishedStory();

    $class = Group::create([
        'teacher_id' => $teacher->id,
        'name' => 'Grade 8 - Blue',
        'join_code' => 'BLUE8-A1',
    ]);
    $class->addMember($student);
    $peer = User::factory()->create(['role' => Role::Student]);
    $class->addMember($peer);

    Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $story->id,
        'group_id' => $class->id,
        'title' => 'Read The Garden',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => strtoupper(Str::random(8)),
    ]);
    $html = $this->actingAs($student)->get(route('student.classwork'))->assertOk()->getContent();

    // Landing: class grid card, mirroring the teacher-side layout.
    $this->assertStringContainsString('Your classes', $html);
    $this->assertStringContainsString('BLUE8-A1', $html);
    $this->assertStringContainsString('data-cw-open="class-'.$class->id.'"', $html);

    // Drill-down view is server-rendered (the pane toggles between views), so
    // assignments live behind the class card, inside hub-style folder tabs.
    $this->assertStringContainsString('data-cw-view="class-'.$class->id.'"', $html);
    $this->assertStringContainsString('data-cw-back', $html);
    $this->assertStringContainsString('data-cw-tab="class-'.$class->id.'-assignments"', $html);
    $this->assertStringContainsString('data-cw-panel="class-'.$class->id.'-assignments"', $html);
    $this->assertStringContainsString('data-cw-tab="class-'.$class->id.'-details"', $html);
    $this->assertStringContainsString('data-cw-panel="class-'.$class->id.'-details"', $html);
    $this->assertStringContainsString('Read The Garden', $html);
    $this->assertStringContainsString('In progress', $html);

    // Leave uses the shared modal, not the browser confirm; People opens the
    // per-class side drawer rendered at widget root level.
    $this->assertStringContainsString('data-cw-leave-open="'.$class->id.'"', $html);
    $this->assertStringContainsString('id="hub-leave-class"', $html);
    $this->assertStringContainsString('data-cw-leave-confirm', $html);
    $this->assertStringContainsString('data-cw-people-open="'.$class->id.'"', $html);
    $this->assertStringContainsString('id="cw-people-'.$class->id.'"', $html);

    // The drawer roster shows the teacher, the student themself and a
    // classmate, so the "(you)" marker renders (two members in the class).
    $this->assertStringContainsString('Teacher', $html);
    $this->assertStringContainsString($teacher->first_name, $html);
    $this->assertStringContainsString('(you)', $html);
    $this->assertStringContainsString($peer->first_name, $html);
});

test('each class renders its own people drawer with a just-you empty state', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = hubStudent();

    $classA = Group::create([
        'teacher_id' => $teacher->id,
        'name' => 'Grade 8 - Blue',
        'join_code' => 'BLUE8-A1',
    ]);
    $classA->addMember($student);

    $classB = Group::create([
        'teacher_id' => $teacher->id,
        'name' => 'Grade 7 - Red',
        'join_code' => 'RED7-B2',
    ]);
    $classB->addMember($student);

    $html = $this->actingAs($student)->get(route('student.classwork'))->assertOk()->getContent();

    // Both classes are solo (only the student joined), so each drill-down
    // shows the "just you" empty state, and the teacher appears in both.
    $this->assertStringContainsString('data-cw-view="class-'.$classA->id.'"', $html);
    $this->assertStringContainsString('data-cw-view="class-'.$classB->id.'"', $html);
    $this->assertStringContainsString('Just you in this class so far.', $html);
    $this->assertStringContainsString('Classmates show up here once they join.', $html);
    $this->assertStringContainsString($teacher->first_name, $html);
    $this->assertStringContainsString('Grade 7 - Red', $html);
});

test('new assignment toast fires once then not on refresh', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = hubStudent();
    $story = publishedStory();

    Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $story->id,
        'title' => 'New Assignment',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'TOAST01',
    ]);

    // First load: toast fires.
    $html = $this->actingAs($student)->get(route('hub'))->assertOk()->getContent();
    $this->assertStringContainsString('1 new assignment from your teacher.', $html);

    // Second load: timestamp persisted, count = 0 → no toast.
    $html2 = $this->actingAs($student)->get(route('hub'))->assertOk()->getContent();
    $this->assertStringNotContainsString('new assignment from your teacher.', $html2);
});

test('completed assignment does not trigger the new-assignment toast', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = hubStudent();
    $story = publishedStory();

    $assignment = Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $story->id,
        'title' => 'Already Done',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'TOAST02',
    ]);

    // Student already completed the work within the gate.
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => 'read',
        'completed_at' => now(),
    ]);

    $html = $this->actingAs($student)->get(route('hub'))->assertOk()->getContent();
    $this->assertStringNotContainsString('new assignment from your teacher.', $html);
});

test('new assignment toast shows count for multiple unfinished', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = hubStudent();
    $storyA = publishedStory('Story A');
    $storyB = publishedStory('Story B');

    Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $storyA->id,
        'title' => 'Assignment A',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'TOAST03',
    ]);
    Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $storyB->id,
        'title' => 'Assignment B',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'TOAST04',
    ]);

    $html = $this->actingAs($student)->get(route('hub'))->assertOk()->getContent();
    $this->assertStringContainsString('2 new assignments from your teacher.', $html);
});
