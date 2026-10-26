<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Badge;
use App\Models\KeyTransaction;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\StreakLeaderboardSnapshot;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function activityStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'Ace Reader',
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
        'total_keys' => 83,
        'current_streak' => 6,
        'longest_streak' => 9,
        'streak_freezes' => 1,
        ...$overrides,
    ]);
}

function activityStory(string $title = 'The Silent River'): Story
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

function activityKey(User $user, int $amount, string $reason, int $balance, Carbon $at, ?int $subjectId = null): KeyTransaction
{
    $row = KeyTransaction::create([
        'user_id' => $user->id,
        'amount' => $amount,
        'reason' => $reason,
        'subject_type' => $subjectId !== null ? 'story' : null,
        'subject_id' => $subjectId,
        'balance_after' => $balance,
    ]);

    $row->forceFill(['created_at' => $at])->save();

    return $row;
}

test('guests are redirected from the activity log', function () {
    $this->get(route('activity'))->assertRedirect(route('login'));
});

test('a fresh student sees an encouraging empty state', function () {
    $this->actingAs(activityStudent())
        ->get(route('activity'))
        ->assertOk()
        ->assertSee('Activity')
        ->assertSee('Nothing here yet')
        ->assertSee('Open the library');
});

test('the keys tab surfaces key earnings with reasons and balances', function () {
    $user = activityStudent();
    $story = activityStory();

    activityKey($user, 5, 'story_clear', 88, now()->subMinutes(10), $story->id);
    activityKey($user, 3, 'daily_task', 91, now()->subMinutes(20));

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'keys']))
        ->assertOk()
        ->assertSee('Story finished')
        ->assertSee($story->title)
        ->assertSee('+5')
        ->assertSee('88 keys')
        ->assertSee('Daily task complete');
});

test('an unknown ledger reason falls back to a neutral row', function () {
    $user = activityStudent();

    activityKey($user, -2, 'legacy_spend', 5, now()->subMinutes(5));

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'keys']))
        ->assertSee('Keys spent')
        ->assertSee('-2');
});

test('the reading tab lists finished and in-progress books', function () {
    $user = activityStudent();
    $done = activityStory('Done Book');
    $open = activityStory('Open Book');

    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $done->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => now()->subDay(),
    ]);
    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $open->id,
        'status' => StoryProgressStatus::Reading,
        'current_page' => 42,
        'updated_at' => now()->subHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'reading']))
        ->assertOk()
        ->assertSee('Story finished')
        ->assertSee('Done Book')
        ->assertSee('Kept reading')
        ->assertSee('Open Book')
        ->assertSee('42%');
});

test('everything merges keys, reads, badges and stamps newest first', function () {
    $user = activityStudent();
    $this->seed(BadgeSeeder::class);
    $badge = Badge::query()->first();
    $story = activityStory('The Deep Forest');

    activityKey($user, 20, 'welcome_kit', 30, now()->subDays(4));
    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => now()->subDays(3),
    ]);
    UserBadge::create([
        'user_id' => $user->id,
        'badge_id' => $badge->id,
        'awarded_at' => now()->subDays(2),
    ]);
    UserDailyActivity::create([
        'user_id' => $user->id,
        'activity_date' => now()->subDays(1)->toDateString(),
        'read_sessions' => 2,
        'stamped_at' => now()->subDays(1)->startOfDay()->addHours(9),
    ]);

    $html = $this->actingAs($user)
        ->get(route('activity'))
        ->assertSee('Badge earned')
        ->assertSee($badge->name)
        ->assertSee('Daily stamp')
        ->assertSee('Welcome kit')
        ->assertSee('Story finished')
        ->assertSee('The Deep Forest')
        ->getContent();

    $positions = [
        'Daily stamp' => mb_strpos($html, 'Daily stamp'),
        'Badge earned' => mb_strpos($html, 'Badge earned'),
        'Story finished' => mb_strpos($html, 'Story finished'),
        'Welcome kit' => mb_strpos($html, 'Welcome kit'),
    ];

    expect($positions['Daily stamp'])->toBeLessThan($positions['Badge earned'])
        ->and($positions['Badge earned'])->toBeLessThan($positions['Story finished'])
        ->and($positions['Story finished'])->toBeLessThan($positions['Welcome kit']);
});

test('key history paginates', function () {
    $user = activityStudent();

    foreach (range(1, 30) as $i) {
        activityKey($user, 1, 'reading_goal', $i, now()->subMinutes(30 + $i));
    }

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'keys']))
        ->assertOk()
        ->assertSee('Page 1 of 2');
});

test('the streak tab shows the leaderboard and freeze shop', function () {
    $user = activityStudent();
    $rival = activityStudent(['nickname' => 'Bookworm', 'current_streak' => 12]);

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'streak']))
        ->assertOk()
        ->assertSee('Streak leaderboard')
        ->assertSee('Bookworm')
        ->assertSee('Buy 15 keys');
});

test('the streak leaderboard excludes scratch, demo and test accounts', function () {
    $user = activityStudent(['nickname' => 'Real Student', 'current_streak' => 3]);
    $scratch = activityStudent(['nickname' => 'Preview Student', 'email' => 'preview.student@scratch.alice.local', 'current_streak' => 99]);
    $demo = activityStudent(['nickname' => 'Demo Student', 'email' => 'demo.student@example.com', 'current_streak' => 50]);
    $test = activityStudent(['nickname' => 'CapstonePre', 'email' => 'capstone.pre@alice.test', 'current_streak' => 40]);

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'streak']))
        ->assertOk()
        ->assertSee('Real Student')
        ->assertDontSee('Preview Student')
        ->assertDontSee('Demo Student')
        ->assertDontSee('CapstonePre');
});

test('the streak leaderboard freezes a weekly snapshot of the top 10', function () {
    $user = activityStudent(['nickname' => 'Freeze Test', 'current_streak' => 5]);

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'streak']))
        ->assertOk();

    $snap = StreakLeaderboardSnapshot::query()
        ->where('week_start', now()->startOfWeek()->toDateString())
        ->get();

    expect($snap->count())->toBeGreaterThan(0)
        ->and($snap->pluck('display_name')->contains('Freeze Test'))->toBeTrue();

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'streak']))
        ->assertOk();

    expect(StreakLeaderboardSnapshot::query()->count())->toBe($snap->count());
});

test('tied streaks still freeze with distinct sequential ranks', function () {
    $a = activityStudent(['nickname' => 'Tie A', 'current_streak' => 4]);
    $b = activityStudent(['nickname' => 'Tie B', 'current_streak' => 4]);

    $this->actingAs($a)
        ->get(route('activity', ['tab' => 'streak']))
        ->assertOk();

    $ranks = StreakLeaderboardSnapshot::query()
        ->where('week_start', now()->startOfWeek()->toDateString())
        ->orderBy('rank')
        ->pluck('rank')
        ->map(fn ($rank) => (int) $rank)
        ->all();

    expect($ranks)->toBe([1, 2]);
});

test('the activity page no longer renders the stamp calendar', function () {
    $user = activityStudent();

    $this->actingAs($user)
        ->get(route('activity', ['tab' => 'streak']))
        ->assertOk()
        ->assertDontSee('stamped')
        ->assertDontSee('grid-cols-7');
});

test('the hub streak and keys cards link into the activity log', function () {
    $user = activityStudent();

    $this->actingAs($user)
        ->get(route('hub'))
        ->assertSee(route('activity', ['tab' => 'streak']), false)
        ->assertSee(route('activity', ['tab' => 'keys']), false);
});
