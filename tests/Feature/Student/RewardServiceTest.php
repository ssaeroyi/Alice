<?php

use App\Models\KeyTransaction;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Models\WordGymDeck;
use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function rewardUser(): User
{
    return User::create([
        'first_name' => 'Test',
        'last_name' => 'Student',
        'nickname' => 'Tester',
        'email' => uniqid().'@alice.test',
        'password' => 'password',
        'role' => 'student',
    ]);
}

test('same-day multiplier ladder then floor (B3)', function () {
    expect(RewardService::sameDayMultiplier(0))->toBe(1.0)
        ->and(RewardService::sameDayMultiplier(1))->toBe(0.75)
        ->and(RewardService::sameDayMultiplier(2))->toBe(0.5)
        ->and(RewardService::sameDayMultiplier(3))->toBe(0.3)
        ->and(RewardService::sameDayMultiplier(4))->toBe(0.15)
        ->and(RewardService::sameDayMultiplier(50))->toBe(0.15);
});

test('band rewards map the four live difficulty labels and fall back', function () {
    expect(RewardService::bandRewards('Easy'))->toBe(['keys' => 8, 'xp' => 15])
        ->and(RewardService::bandRewards('Intermediate'))->toBe(['keys' => 12, 'xp' => 25])
        ->and(RewardService::bandRewards('Hard'))->toBe(['keys' => 16, 'xp' => 40])
        ->and(RewardService::bandRewards('Very Hard'))->toBe(['keys' => 20, 'xp' => 60])
        ->and(RewardService::bandRewards(null))->toBe(['keys' => 8, 'xp' => 15])
        ->and(RewardService::bandRewards('Very Easy'))->toBe(['keys' => 8, 'xp' => 15])
        ->and(RewardService::bandRewards('Mystery Band'))->toBe(['keys' => 8, 'xp' => 15]);
});

test('accuracy tiers incl perfect bonus', function () {
    expect(RewardService::accuracyTier(30.0))->toBe(0.5)
        ->and(RewardService::accuracyTier(55.0))->toBe(0.75)
        ->and(RewardService::accuracyTier(85.0))->toBe(1.0)
        ->and(RewardService::accuracyTier(100.0))->toBe(1.25);
});

test('first attempt below pass pays partial XP and zero keys (B1)', function () {
    $e = RewardService::quizEarnings('Easy', 40.0, null, 0);

    expect($e['reason'])->toBe('quiz_first_attempt')
        ->and($e['keys'])->toBe(0)
        ->and($e['xp'])->toBe((int) round(15 * 0.5 * 1.0));
});

test('first clear pays keys plus XP with accuracy tier applied (B1)', function () {
    $e = RewardService::quizEarnings('Intermediate', 80.0, null, 0);

    expect($e['reason'])->toBe('quiz_first_clear')
        ->and($e['keys'])->toBe(12)
        ->and($e['xp'])->toBe(25);
});

test('perfect first clear pays the bonus tier on both currencies (B1)', function () {
    $e = RewardService::quizEarnings('Hard', 100.0, null, 0);

    expect($e['reason'])->toBe('quiz_first_clear')
        ->and($e['keys'])->toBe((int) round(16 * 1.25))
        ->and($e['xp'])->toBe((int) round(40 * 1.25));
});

test('same-day decay applies to first clears (B3)', function () {
    $fresh = RewardService::quizEarnings('Very Hard', 90.0, null, 0);
    $decayed = RewardService::quizEarnings('Very Hard', 90.0, null, 2);

    expect($fresh['multiplier'])->toBe(1.0)
        ->and($decayed['multiplier'])->toBe(0.5)
        ->and($decayed['keys'])->toBe((int) round(20 * 1.0 * 0.5))
        ->and($decayed['xp'])->toBe((int) round(60 * 1.0 * 0.5));
});

test('retake improvement pays only a quarter of the XP delta and never keys (B1)', function () {
    // Easy base xp = 15; improvement 60 -> 80 = 20 pct of base = 3.0 xp, x 0.25 share
    $e = RewardService::quizEarnings('Easy', 80.0, 60.0, 0);

    expect($e['reason'])->toBe('quiz_improvement')
        ->and($e['keys'])->toBe(0)
        ->and($e['xp'])->toBe(1);
});

test('retake without improvement pays nothing at all (B1)', function () {
    foreach ([100.0 => 100.0, 70.0 => 70.0, 90.0 => 95.0] as $score => $best) {
        $e = RewardService::quizEarnings('Intermediate', $score, $best, 0);

        expect($e['reason'])->toBe('quiz_no_reward')
            ->and($e['keys'])->toBe(0)
            ->and($e['xp'])->toBe(0);
    }
});

test('already-perfect retake can never be re-milked (B1 fatal-flaw guard)', function () {
    $e = RewardService::quizEarnings('Very Hard', 100.0, 100.0, 0);

    expect($e)->toMatchArray(['keys' => 0, 'xp' => 0]);
});

test('drills pay only on the first scored drill per deck per day (B4)', function () {
    $first = RewardService::drillEarnings(true, 0);
    $repeat = RewardService::drillEarnings(false, 0);

    expect($first)->toMatchArray(['keys' => 4, 'xp' => 8])
        ->and($repeat)->toMatchArray(['keys' => 0, 'xp' => 0]);
});

test('settleQuizAttempt credits quiz + promotion balances and writes one ledger row per key movement', function () {
    $user = rewardUser();
    $now = Carbon::parse('2026-08-24 10:00:00');

    $result = RewardService::settleQuizAttempt($user, 7, 'Hard', 100.0, null, $now);

    expect($result['reason'])->toBe('quiz_first_clear')
        ->and($result['keys'])->toBe(20)
        ->and($result['xp'])->toBe(50);

    // Hard perfect first clear = 16 keys x 1.25 perfect tier = 20 keys / 40 xp
    // x 1.25 = 50 xp. The 50 points cross New Reader -> Bookworm (tier 1), so
    // the title ceremony pays +5 promotion keys inside the same settle.
    expect($result['promotion'])->toMatchArray([
        'promoted' => true,
        'title' => 'Bookworm',
        'keys' => 5,
    ]);

    $user->refresh();
    expect($user->total_keys)->toBe(25)
        ->and($user->points)->toBe(50)
        ->and($user->rank_title)->toBe('Bookworm');

    $rows = KeyTransaction::query()->where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(2);

    $quiz = $rows->firstWhere('reason', 'quiz_first_clear');
    $promotion = $rows->firstWhere('reason', 'rank_promotion');

    expect($quiz->amount)->toBe(20)
        ->and($quiz->balance_after)->toBe(20)
        ->and($quiz->subject_type)->toBe('story')
        ->and($quiz->subject_id)->toBe(7)
        ->and($promotion->amount)->toBe(5)
        ->and($promotion->balance_after)->toBe(25)
        ->and($promotion->subject_type)->toBe('rank');
});

test('spend refuses to go negative and logs successful spends with running balance', function () {
    $user = User::create([
        'first_name' => 'Rich', 'last_name' => 'Kid', 'nickname' => 'Rich',
        'email' => uniqid().'@alice.test', 'password' => 'password', 'role' => 'student',
        'total_keys' => 10,
    ]);

    expect(RewardService::spend($user, 11, 'theme_purchase'))->toBeFalse();

    $user->refresh();
    expect($user->total_keys)->toBe(10)
        ->and(KeyTransaction::count())->toBe(0);

    expect(RewardService::spend($user, 4, 'hint_token'))->toBeTrue();

    $user->refresh();
    expect($user->total_keys)->toBe(6);

    $row = KeyTransaction::query()->sole();
    expect($row->amount)->toBe(-4)
        ->and($row->balance_after)->toBe(6)
        ->and($row->reason)->toBe('hint_token');
});

test('buyStreakFreeze spends keys and adds a freeze with a ledger row', function () {
    $cost = (int) config('gamification.sinks.streak_freeze');
    $user = rewardUser();
    $user->fill(['total_keys' => 20, 'streak_freezes' => 0])->save();

    $result = RewardService::buyStreakFreeze($user);

    expect($result)->toMatchArray(['ok' => true, 'reason' => null]);

    $user->refresh();
    expect($user->total_keys)->toBe(20 - $cost)
        ->and($user->streak_freezes)->toBe(1);

    $row = KeyTransaction::query()->sole();
    expect($row->amount)->toBe(-$cost)
        ->and($row->balance_after)->toBe(20 - $cost)
        ->and($row->reason)->toBe('streak_freeze');
});

test('buyStreakFreeze refuses at the hold cap without charging', function () {
    $cost = (int) config('gamification.sinks.streak_freeze');
    $user = rewardUser();
    $user->fill([
        'total_keys' => 50,
        'streak_freezes' => config('gamification.freeze.hold_cap'),
    ])->save();

    $result = RewardService::buyStreakFreeze($user);

    expect($result)->toMatchArray(['ok' => false, 'reason' => 'cap']);

    $user->refresh();
    expect($user->total_keys)->toBe(50)
        ->and($user->streak_freezes)->toBe(config('gamification.freeze.hold_cap'))
        ->and(KeyTransaction::count())->toBe(0);
});

test('buyStreakFreeze refuses without enough keys', function () {
    $cost = (int) config('gamification.sinks.streak_freeze');
    $user = rewardUser();
    $user->fill(['total_keys' => $cost - 1, 'streak_freezes' => 0])->save();

    $result = RewardService::buyStreakFreeze($user);

    expect($result)->toMatchArray(['ok' => false, 'reason' => 'keys']);

    $user->refresh();
    expect($user->total_keys)->toBe($cost - 1)
        ->and($user->streak_freezes)->toBe(0)
        ->and(KeyTransaction::count())->toBe(0);
});

test('stampDay stamps once, counts actions, and starts the streak', function () {
    $user = rewardUser();
    // Hold the cap so the monthly-grant branch stays inert here (it has its
    // own dedicated test below).
    $user->fill(['streak_freezes' => config('gamification.freeze.hold_cap')])->save();
    $monday = Carbon::parse('2026-08-24 09:00:00');

    $r1 = RewardService::stampDay($user, 'read', $monday);
    $r2 = RewardService::stampDay($user, 'quiz', $monday->copy()->addHours(2));

    expect($r1)->toMatchArray(['stamped' => true, 'freeze_used' => false, 'freeze_granted' => false])
        ->and($r1['streak'])->toBe(1)
        ->and($r2['stamped'])->toBeFalse()
        ->and($r2['streak'])->toBe(1);

    $user->refresh();
    $activity = UserDailyActivity::query()->whereDate('activity_date', '2026-08-24')->sole();

    expect($user->current_streak)->toBe(1)
        ->and($user->last_stamped_date->toDateString())->toBe('2026-08-24')
        ->and($activity->read_sessions)->toBe(1)
        ->and($activity->quiz_submissions)->toBe(1)
        ->and($activity->stamped_at)->not->toBeNull();
});

test('consecutive stamped days grow the streak and track the longest', function () {
    $user = rewardUser();

    RewardService::stampDay($user, 'read', Carbon::parse('2026-08-24 09:00:00'));
    RewardService::stampDay($user, 'drill', Carbon::parse('2026-08-25 09:00:00'));
    RewardService::stampDay($user, 'game', Carbon::parse('2026-08-26 21:00:00'));

    $user->refresh();
    expect($user->current_streak)->toBe(3)
        ->and($user->longest_streak)->toBe(3);
});

test('a single missed day consumes a held freeze instead of resetting (kind streaks)', function () {
    $user = rewardUser();

    RewardService::stampDay($user, 'read', Carbon::parse('2026-08-24 09:00:00'));
    RewardService::stampDay($user, 'read', Carbon::parse('2026-08-26 09:00:00'));

    $user->refresh();
    expect($user->current_streak)->toBe(2)
        ->and($user->streak_freezes)->toBe(0);
});

test('a missed day without any held freeze resets the streak to one', function () {
    $user = rewardUser();

    RewardService::stampDay($user, 'read', Carbon::parse('2026-08-24 09:00:00'));
    RewardService::stampDay($user, 'read', Carbon::parse('2026-08-26 09:00:00'));
    RewardService::stampDay($user, 'read', Carbon::parse('2026-08-28 09:00:00'));

    $user->refresh();
    expect($user->current_streak)->toBe(1);
});

test('monthly freeze grant lands on the first stamp of a month within the hold cap', function () {
    $user = rewardUser();
    $user->fill(['streak_freezes' => 0])->save();

    // First ever stamp is also the month's first: grant fires.
    $r1 = RewardService::stampDay($user, 'quiz', Carbon::parse('2026-08-03 09:00:00'));
    expect($r1['freeze_granted'])->toBeTrue();

    // Later stamps in the same month never re-grant, even after spending it.
    RewardService::stampDay($user, 'quiz', Carbon::parse('2026-08-04 09:00:00'));
    $user->fill(['streak_freezes' => 0])->save();

    $r3 = RewardService::stampDay($user, 'quiz', Carbon::parse('2026-08-05 09:00:00'));
    expect($r3['freeze_granted'])->toBeFalse()
        ->and($user->refresh()->streak_freezes)->toBe(0);

    // Next month's first stamp grants again.
    $user->fill(['streak_freezes' => 0])->save();
    $r4 = RewardService::stampDay($user, 'quiz', Carbon::parse('2026-09-01 08:00:00'));
    expect($r4['freeze_granted'])->toBeTrue()
        ->and($user->refresh()->streak_freezes)->toBe(1);
});

test('farmableActionsToday counts only farmable columns for the decay input', function () {
    $user = rewardUser();
    $day = Carbon::parse('2026-08-24 09:00:00');

    RewardService::stampDay($user, 'read', $day);          // not farmable
    RewardService::stampDay($user, 'quiz', $day);
    RewardService::stampDay($user, 'drill', $day);
    RewardService::stampDay($user, 'game', $day);

    expect(RewardService::farmableActionsToday($user, $day))->toBe(3);
});

test('settleDrill pays the deck-daily first drill through the ledger (B4)', function () {
    $user = rewardUser();
    $deckId = WordGymDeck::query()->create(['user_id' => $user->id, 'name' => 'Chapter 1 words'])->id;
    $now = Carbon::parse('2026-08-24 15:00:00');

    $first = RewardService::settleDrill($user, $deckId, true, $now);
    expect($first)->toMatchArray(['keys' => 4, 'xp' => 8]);

    $repeat = RewardService::settleDrill($user, $deckId, false, $now->copy()->addMinutes(10));
    expect($repeat)->toMatchArray(['keys' => 0, 'xp' => 0]);

    $user->refresh();
    expect($user->total_keys)->toBe(4)
        ->and(KeyTransaction::query()->where('reason', 'drill_first_daily')->count())->toBe(1);
});

test('readingPointsFor banks whole points at 1.5 per full minute up to the cap', function () {
    $rates = [
        0 => 0,
        59 => 0,
        60 => 1,
        119 => 1,
        120 => 3,
        180 => 4,
        599 => 13,
        600 => 15,
    ];

    foreach ($rates as $seconds => $expected) {
        expect(RewardService::readingPointsFor($seconds))->toBe($expected);
    }
});

test('readingPointsFor cannot exceed the daily reading-points cap', function () {
    $cap = (int) config('gamification.points.reading_daily_cap');

    expect(RewardService::readingPointsFor(60 * $cap))->toBe($cap)
        ->and(RewardService::readingPointsFor(60 * ($cap + 50)))->toBe($cap);
});

test('awardReadingPoints pays only the delta between beats, never more than a minute', function () {
    $user = rewardUser();

    // 0 -> 120s = two full minutes at 1.5/min = 3 points.
    expect(RewardService::awardReadingPoints($user, 120, 0))->toBe(3);
    expect($user->refresh()->points)->toBe(3);

    // 120 -> 180s crosses exactly one more full-minute boundary (2 -> 3 min).
    expect(RewardService::awardReadingPoints($user, 180, 120))->toBe(1);
    expect($user->refresh()->points)->toBe(4);

    // Sub-minute progress inside an already-paid minute pays nothing.
    expect(RewardService::awardReadingPoints($user, 200, 180))->toBe(0);
    expect($user->refresh()->points)->toBe(4);
});
