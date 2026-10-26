<?php

use App\Enums\UserDailyTaskStatus;
use App\Models\Assignment;
use App\Models\GameRound;
use App\Models\KeyTransaction;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use App\Models\UserDailyTask;
use Database\Seeders\DemoStudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function demoStudent(): User
{
    $user = User::where('email', DemoStudentSeeder::DEMO_STUDENT_EMAIL)->firstOrFail();

    return $user->fresh();
}

test('demo seeder builds a lived-in, ledger-true student', function () {
    $this->seed(DemoStudentSeeder::class);

    $demo = demoStudent();

    expect($demo->points)->toBe(2450)
        ->and($demo->current_streak)->toBe(6)
        ->and($demo->longest_streak)->toBe(12)
        ->and($demo->rank_title)->toBe('Word Sage')
        ->and($demo->daily_reading_goal)->toBe(20)
        ->and($demo->daily_study_goal)->toBe(10);

    // Keys are only ever moved through RewardService: the balance equals the
    // signed sum of the whole ledger, exactly like a real student.
    $ledger = (int) KeyTransaction::query()->where('user_id', $demo->id)->sum('amount');
    expect((int) $demo->total_keys)->toBe($ledger);

    // Domain record sizes.
    expect(StoryProgress::where('user_id', $demo->id)->count())->toBe(7)
        ->and(StoryProgress::where('user_id', $demo->id)->where('status', 'completed')->count())->toBe(6)
        ->and(TestAttempt::where('user_id', $demo->id)->count())->toBe(10)
        ->and(GameRound::where('user_id', $demo->id)->count())->toBe(15)
        ->and(SavedWord::where('user_id', $demo->id)->count())->toBe(30)
        ->and(SavedWord::where('user_id', $demo->id)->whereNotNull('last_drilled_at')->count())->toBeGreaterThanOrEqual(10)
        ->and(SavedWord::where('user_id', $demo->id)->whereDate('created_at', now()->toDateString())->count())->toBe(5)
        ->and(UserDailyActivity::where('user_id', $demo->id)->whereNotNull('stamped_at')->count())->toBeGreaterThanOrEqual(20);

    // Badges: the shelf is real (world badges earned plus all 6 title badges
    // through Word Sage).
    expect(UserBadge::where('user_id', $demo->id)->count())->toBeGreaterThan(10);
    $wordSage = DB::table('badges')->where('key', 'title_word_sage')->value('id');
    expect(UserBadge::where('user_id', $demo->id)->where('badge_id', $wordSage)->exists())->toBeTrue();

    // Today's task mix: one claimed, one claimable, one pending.
    $todayTasks = UserDailyTask::where('user_id', $demo->id)->whereDate('task_date', now()->toDateString())->get();
    expect($todayTasks)->toHaveCount(3)
        ->and($todayTasks->where('status', UserDailyTaskStatus::Claimed))->toHaveCount(1)
        ->and($todayTasks->where('status', UserDailyTaskStatus::Completed))->toHaveCount(1)
        ->and($todayTasks->where('status', UserDailyTaskStatus::Pending))->toHaveCount(1);

    // Inbox: one unread row (the new assignment) above read achievements.
    expect($demo->notifications()->unread()->count())->toBe(1)
        ->and($demo->notifications()->unread()->first()->type)->toBe('assignment.new')
        ->and($demo->notifications()->where('type', 'like', 'achievement.%')->whereNull('read_at')->count())->toBe(0);

    // Classwork: active + finished assignments, full class of four students.
    expect($demo->activeAssignments())->toHaveCount(1)
        ->and($demo->finishedAssignments())->toHaveCount(1)
        ->and($demo->joinedGroups()->first()->members()->count())->toBe(4);

    // The demo student opens the Hub and its stats feed without error.
    $this->actingAs($demo)->get(route('hub'))->assertOk();

    $this->actingAs($demo)->getJson(route('hub.stats').'?days=thisweek')->assertOk();
});

test('demo hub stats are real and non-zero', function () {
    $this->seed(DemoStudentSeeder::class);

    $demo = demoStudent();

    // Live charts: this-week's series always contains today (23 min of reading
    // + today's stamp), so these are stable no matter which weekday it is.
    $series = collect($this->actingAs($demo)->getJson(route('hub.stats').'?days=thisweek')->assertOk()->json('series'));
    expect($series->isNotEmpty())->toBeTrue()
        ->and($series->sum('reading_seconds'))->toBeGreaterThan(0)
        ->and($series->sum('stamped'))->toBeGreaterThan(0);

    // The all-time calendar aggregates the whole lived-in history.
    $payload = $this->actingAs($demo)->getJson(route('hub.stats').'?days=all')->assertOk()->json();
    $total = collect($payload['monthlySeries']);
    expect($total->isNotEmpty())->toBeTrue()
        ->and($total->sum('reading_minutes'))->toBeGreaterThan(0)
        ->and($total->sum('quizzes'))->toBeGreaterThan(0)
        ->and($total->sum('games'))->toBeGreaterThan(0)
        ->and($total->sum('stamps'))->toBeGreaterThan(0);

    // And the numbers behind those charts live in real activity rows.
    $activityTotals = UserDailyActivity::where('user_id', $demo->id)
        ->selectRaw('COALESCE(SUM(reading_seconds_today), 0) as reading, COALESCE(SUM(quiz_submissions), 0) as quizzes, COALESCE(SUM(game_rounds), 0) as games, COUNT(stamped_at) as stamps')
        ->first();
    expect((int) $activityTotals->reading)->toBeGreaterThan(0)
        ->and((int) $activityTotals->quizzes)->toBe(10)
        ->and((int) $activityTotals->games)->toBe(15)
        ->and((int) $activityTotals->stamps)->toBe(27);
});

test('demo seeder is idempotent (no double-paid economy)', function () {
    $this->seed(DemoStudentSeeder::class);

    $before = demoStudent();
    $keysBefore = (int) $before->total_keys;
    $ledgerBefore = (int) KeyTransaction::query()->where('user_id', $before->id)->sum('amount');
    $countsBefore = [
        'badges' => UserBadge::where('user_id', $before->id)->count(),
        'attempts' => TestAttempt::where('user_id', $before->id)->count(),
        'rounds' => GameRound::where('user_id', $before->id)->count(),
        'words' => SavedWord::where('user_id', $before->id)->count(),
        'notifications' => DB::table('notifications')->where('user_id', $before->id)->count(),
        'stories' => Story::count(),
    ];

    $this->seed(DemoStudentSeeder::class);

    $after = demoStudent();
    $countsAfter = [
        'badges' => UserBadge::where('user_id', $after->id)->count(),
        'attempts' => TestAttempt::where('user_id', $after->id)->count(),
        'rounds' => GameRound::where('user_id', $after->id)->count(),
        'words' => SavedWord::where('user_id', $after->id)->count(),
        'notifications' => DB::table('notifications')->where('user_id', $after->id)->count(),
        'stories' => Story::count(),
    ];

    expect((int) $after->total_keys)->toBe($keysBefore)
        ->and((int) KeyTransaction::query()->where('user_id', $after->id)->sum('amount'))->toBe($ledgerBefore)
        ->and($countsAfter)->toBe($countsBefore);
});
