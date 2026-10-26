<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\GameRound;
use App\Models\KeyTransaction;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Services\RewardService;
use App\Support\ViewAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function devToolsStory(string $difficulty = 'Easy'): Story
{
    return Story::create([
        'title' => 'DevTools Trail',
        'author' => 'A. Walker',
        'synopsis' => 'Dev tools debug.',
        'reading_level' => 6,
        'word_count' => 300,
        'status' => 'published',
        'difficulty_label' => $difficulty,
        'content' => 'The morning walker went quickly down the old road and saw the old gate.',
    ]);
}

function devToolsAdmin(): User
{
    return User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);
}

test('non-admins are locked out of the dev tools endpoints', function () {
    $student = User::factory()->create(['role' => Role::Student, 'suspended_at' => null]);

    $this->actingAs($student)
        ->get(route('dev.state'))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('dev.run'), ['action' => 'add_keys', 'amount' => 5])
        ->assertForbidden();
});

test('teachers outside a preview are locked out', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher, 'suspended_at' => null]);

    $this->actingAs($teacher)
        ->get(route('dev.state'))
        ->assertForbidden();
});

test('a real admin can act on the scratch student through the endpoints', function () {
    $admin = devToolsAdmin();

    $run = $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 25]);
    $run->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message', 'Granted 25 key(s).');

    $scratch = ViewAs::scratchStudent();
    expect($scratch->total_keys)->toBe(25);

    $state = $this->actingAs($admin)->getJson(route('dev.state'))->assertOk()->json();
    expect($state['ok'])->toBeTrue()
        ->and($state['email'])->toBe($scratch->email)
        ->and($state['total_keys'])->toBe(25)
        ->and($state['scratch'])->toBeTrue();
});

test('the overlay endpoints work mid-preview and render the DEV TOOLS button', function () {
    $admin = devToolsAdmin();
    $this->actingAs($admin)->post(route('admin.preview.switch', 'student'));
    $scratch = ViewAs::scratchStudent();

    $this->postJson(route('dev.run'), ['action' => 'add_points', 'amount' => 100])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($scratch->fresh()->points)->toBe(100);

    $this->get(route('hub'))
        ->assertOk()
        ->assertSee('DEV TOOLS')
        ->assertSee('Scratch only');
});

test('unknown actions return 404 and invalid payloads are rejected', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'nope'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => -5])
        ->assertStatus(422)
        ->assertJsonPath('ok', false);

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 5000])
        ->assertStatus(422);

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'scenario_quiz', 'kind' => 'rbarbarian'])
        ->assertStatus(422);
});

test('granting keys is ledger-backed with reason dev_tool', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 40]);

    $scratch = ViewAs::scratchStudent();
    $row = KeyTransaction::query()->where('user_id', $scratch->id)->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->amount)->toBe(40)
        ->and($row->reason)->toBe('dev_tool')
        ->and($row->balance_after)->toBe(40);
});

test('spending keys is debited and short balances are refused untouched', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 10]);

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'spend_keys', 'amount' => 50])
        ->assertJsonPath('ok', false);

    $scratch = ViewAs::scratchStudent();
    expect($scratch->fresh()->total_keys)->toBe(10)
        ->and(KeyTransaction::query()->where('user_id', $scratch->id)->where('amount', '<', 0)->count())->toBe(0);

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'spend_keys', 'amount' => 3])
        ->assertJsonPath('ok', true);

    expect($scratch->fresh()->total_keys)->toBe(7)
        ->and(KeyTransaction::query()->where('user_id', $scratch->id)->where('amount', -3)->count())->toBe(1);
});

test('points can be granted and set to an exact value', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'add_points', 'amount' => 100]);
    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'set_points', 'points' => 8000]);

    $scratch = ViewAs::scratchStudent();

    expect($scratch->fresh()->points)->toBe(8000);
});

test('reading minutes drive the day row and the exact reading-points delta', function () {
    $admin = devToolsAdmin();
    $scratch = ViewAs::scratchStudent();
    $scratch->forceFill(['daily_reading_goal' => 5])->save();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'reading_minutes', 'minutes' => 5])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $activity = UserDailyActivity::query()
        ->where('user_id', $scratch->id)
        ->whereDate('activity_date', now()->toDateString())
        ->first();

    $expectedPoints = RewardService::readingPointsFor(300) - RewardService::readingPointsFor(0);

    expect($activity)->not->toBeNull()
        ->and($activity->reading_seconds_today)->toBe(300)
        ->and($scratch->fresh()->points)->toBe($expectedPoints);

    // Crossed the 5-minute goal exactly: the once-per-day goal reward fires.
    expect(KeyTransaction::query()
        ->where('user_id', $scratch->id)
        ->where('reason', 'reading_goal')
        ->count())->toBe(1);
});

test('set_farmable positions the B3 decay slot for scenarios', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'set_farmable', 'count' => 3]);

    expect(RewardService::farmableActionsToday(ViewAs::scratchStudent(), now()))->toBe(3);

    devToolsStory('Easy');

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'scenario_quiz', 'kind' => 'perfect'])
        ->assertJsonPath('ok', true);

    $expectedKeys = (int) round(8 * 1.25 * (float) config('gamification.same_day_multipliers')[3]);

    expect(ViewAs::scratchStudent()->fresh()->total_keys)->toBe($expectedKeys);
});

test('a perfect quiz first clear pays the band rewards at slot 0 and stamps the day', function () {
    $admin = devToolsAdmin();
    devToolsStory('Easy');
    $scratch = ViewAs::scratchStudent();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'scenario_quiz', 'kind' => 'perfect'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $expectedKeys = (int) round(8 * 1.25);
    $expectedXp = (int) round(15 * 1.25);

    expect($scratch->fresh()->total_keys)->toBe($expectedKeys)
        ->and($scratch->fresh()->points)->toBe($expectedXp)
        ->and($scratch->fresh()->current_streak)->toBe(1)
        // Documented deviation: scenarios settle the economy but never write a
        // TestAttempt, keeping aggregate attempt stats clean.
        ->and(TestAttempt::query()->where('user_id', $scratch->id)->count())->toBe(0);
});

test('quiz scenarios refuse when no published story exists', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'scenario_quiz', 'kind' => 'perfect'])
        ->assertJsonPath('ok', false);
});

test('game scenarios write a real GameRound and award points', function () {
    $admin = devToolsAdmin();
    $scratch = ViewAs::scratchStudent();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'scenario_game', 'game' => 'wordsearch', 'score' => 'excellent'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $round = GameRound::query()->where('user_id', $scratch->id)->first();

    expect($round)->not->toBeNull()
        ->and($round->game_type)->toBe('wordsearch')
        ->and($round->score)->toBe(85)
        ->and($round->words_count)->toBe(10);

    $expectedXp = RewardService::gameEarnings(85, 0)['xp'];

    expect($scratch->fresh()->points)->toBe($expectedXp)
        ->and($scratch->fresh()->current_streak)->toBe(1);
});

test('buy restore runs the real key sink and respects the cap', function () {
    $admin = devToolsAdmin();
    $scratch = ViewAs::scratchStudent()->refresh();
    $cost = (int) config('gamification.sinks.streak_freeze');
    $cap = (int) config('gamification.freeze.hold_cap');

    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 100]);
    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'buy_freeze']);

    expect($scratch->fresh()->total_keys)->toBe(100 - $cost)
        ->and($scratch->fresh()->streak_freezes)->toBe($cap);

    // Already at the cap — refused, keys untouched.
    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'buy_freeze'])
        ->assertJsonPath('ok', false);

    expect($scratch->fresh()->total_keys)->toBe(100 - $cost)
        ->and($scratch->fresh()->streak_freezes)->toBe($cap);
});

test('buy restore refuses without keys and at the cap', function () {
    $admin = devToolsAdmin();
    $scratch = ViewAs::scratchStudent();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'buy_freeze'])
        ->assertJsonPath('ok', false);

    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 100]);
    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'set_freezes', 'freezes' => 5]);

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'buy_freeze'])
        ->assertJsonPath('ok', false);

    expect($scratch->fresh()->total_keys)->toBe(100);
});

test('reset wipes scratch activity and zeroes the economy', function () {
    $admin = devToolsAdmin();
    $story = devToolsStory('Easy');
    $scratch = ViewAs::scratchStudent();

    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 20]);
    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'add_points', 'amount' => 50]);
    $this->actingAs($admin)->postJson(route('dev.run'), ['action' => 'scenario_game', 'game' => 'match', 'score' => 'good']);

    StoryProgress::create([
        'user_id' => $scratch->id,
        'story_id' => $story->id,
        'status' => 'completed',
        'time_spent_seconds' => 60,
    ]);

    ReadingProgress::create([
        'user_id' => $scratch->id,
        'live_score' => 72.5,
        'reading_grade' => 5,
    ]);

    $scratch->forceFill(['current_streak' => 3, 'streak_freezes' => 1])->save();

    expect(GameRound::query()->where('user_id', $scratch->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->postJson(route('dev.run'), ['action' => 'reset'])
        ->assertJsonPath('ok', true);

    expect(GameRound::query()->where('user_id', $scratch->id)->exists())->toBeFalse()
        ->and(StoryProgress::query()->where('user_id', $scratch->id)->exists())->toBeFalse()
        ->and(ReadingProgress::query()->where('user_id', $scratch->id)->exists())->toBeFalse()
        ->and(KeyTransaction::query()->where('user_id', $scratch->id)->exists())->toBeFalse()
        ->and($scratch->fresh()->total_keys)->toBe(0)
        ->and($scratch->fresh()->points)->toBe(0)
        ->and($scratch->fresh()->current_streak)->toBe(0)
        ->and($scratch->fresh()->streak_freezes)->toBe(0);
});

test('dev tool actions are audited against the real admin', function () {
    $admin = devToolsAdmin();

    $this->actingAs($admin)->post(route('admin.preview.switch', 'student'));
    $scratch = ViewAs::scratchStudent();

    $this->postJson(route('dev.run'), ['action' => 'add_keys', 'amount' => 10]);

    $log = AuditLog::query()->where('action', 'dev_tools.add_keys')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->target_id)->toBe($scratch->id)
        ->and($log->meta['view_as'] ?? false)->toBeTrue();
});
