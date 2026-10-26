<?php

use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Genre;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BadgeSeeder::class);
    $this->student = User::factory()->create(['is_setup_complete' => true]);
});

function badgeStory(User $user, ?Genre $genre = null): Story
{
    $story = Story::create([
        'title' => 'Story '.uniqid(),
        'author' => 'Test Author',
        'synopsis' => 'A short test story.',
        'content' => 'The waves crashed against the rocks all afternoon long.',
        'word_count' => 120,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Easy',
        'status' => StoryStatus::Published,
    ]);

    if ($genre !== null) {
        $story->genres()->attach($genre->id);
    }

    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => Carbon::now(),
    ]);

    return $story;
}

function saveWords(User $user, int $count): void
{
    foreach (range(1, $count) as $i) {
        SavedWord::create([
            'user_id' => $user->id,
            'word' => 'word'.$i.'-'.uniqid(),
            'definition' => 'test',
        ]);
    }
}

test('badge award is idempotent and only grants eligible badges for the fired events', function () {
    $now = Carbon::now();

    $first = BadgeService::award($this->student, ['story'], $now);
    expect($first)->toHaveCount(0);

    badgeStory($this->student);
    $awarded = BadgeService::award($this->student, ['story'], $now);
    expect($awarded->pluck('key'))->toContain('first_story');

    $again = BadgeService::award($this->student, ['story'], $now);
    expect($again)->toHaveCount(0);

    expect(UserBadge::where('user_id', $this->student->id)->where('badge_id', $awarded->first()->id)->count())->toBe(1);
});

test('event scoping: a word save never grants story badges', function () {
    saveWords($this->student, 25);

    $awarded = BadgeService::award($this->student, ['word'], Carbon::now());
    expect($awarded->pluck('key'))->toContain('word_collector_25')
        ->not->toContain('first_story');
});

test('stories count badges fire at 5, 10 and 25 completed stories', function () {
    $now = Carbon::now();

    foreach (range(1, 25) as $i) {
        badgeStory($this->student);
    }

    $awarded = BadgeService::award($this->student, ['story'], $now);
    expect($awarded->pluck('key'))
        ->toContain('stories_5')
        ->toContain('stories_10')
        ->toContain('stories_25');
});

test('genre explorer requires five distinct genres among completed stories', function () {
    foreach (['a', 'b', 'c', 'd', 'e'] as $g) {
        $genre = Genre::create(['name' => 'Genre '.$g]);
        badgeStory($this->student, $genre);
    }

    expect(BadgeService::eligible($this->student, 'genre_explorer', Carbon::now()))->toBeTrue();
});

test('word collector fires at 25 and 100 saved words', function () {
    saveWords($this->student, 30);
    $awarded = BadgeService::award($this->student, ['word'], Carbon::now());
    expect($awarded->pluck('key'))->toContain('word_collector_25')->not->toContain('word_collector_100');
});

test('perfect quiz fires on a passing 100% attempt', function () {
    $story = badgeStory($this->student);
    TestAttempt::create([
        'user_id' => $this->student->id,
        'story_id' => $story->id,
        'attempt_uuid' => (string) Str::uuid(),
        'score_pct' => 100,
        'passed' => true,
        'correct_count' => 5,
    ]);

    $awarded = BadgeService::award($this->student, ['quiz'], Carbon::now());
    expect($awarded->pluck('key'))->toContain('perfect_quiz');
});

test('streak badges fire at 7 and 30 days', function () {
    $this->student->current_streak = 30;
    $this->student->longest_streak = 30;
    $this->student->save();

    $awarded = BadgeService::award($this->student, ['stamp'], Carbon::now());
    expect($awarded->pluck('key'))->toContain('streak_7')->toContain('streak_30');
});

test('perfect month fires only when the previous month was fully stamped', function () {
    $now = Carbon::create(2026, 8, 15);
    $prev = $now->copy()->subMonthNoOverflow();

    foreach (range(1, $prev->daysInMonth) as $day) {
        UserDailyActivity::create([
            'user_id' => $this->student->id,
            'activity_date' => $prev->copy()->day($day)->toDateString(),
            'stamped_at' => $prev->copy()->day($day)->hour(10),
        ]);
    }

    expect(BadgeService::eligible($this->student, 'perfect_month', $now))->toBeTrue();
});

test('night owl fires when a daily stamp happened after 9pm', function () {
    UserDailyActivity::create([
        'user_id' => $this->student->id,
        'activity_date' => '2026-01-05',
        'stamped_at' => Carbon::create(2026, 1, 5, 22, 0),
    ]);

    expect(BadgeService::eligible($this->student, 'night_owl', Carbon::now()))->toBeTrue();
});

test('night owl stays locked without a late-night stamp', function () {
    UserDailyActivity::create([
        'user_id' => $this->student->id,
        'activity_date' => '2026-01-05',
        'stamped_at' => Carbon::create(2026, 1, 5, 8, 0),
    ]);

    expect(BadgeService::eligible($this->student, 'night_owl', Carbon::now()))->toBeFalse();
});

test('freeze usage badges fire on lifetime freezes consumed', function () {
    $this->student->streak_freezes_used = 12;
    $this->student->save();

    $awarded = BadgeService::award($this->student, ['freeze'], Carbon::now());
    expect($awarded->pluck('key'))
        ->toContain('freeze_first')
        ->toContain('freeze_10')
        ->not->toContain('freeze_25');

    $this->student->streak_freezes_used = 26;
    $this->student->save();
    expect(BadgeService::eligible($this->student, 'freeze_25', Carbon::now()))->toBeTrue();
});

test('freeze buy badge fires once a freeze has been bought', function () {
    expect(BadgeService::eligible($this->student, 'freeze_buy_1', Carbon::now()))->toBeFalse();

    $this->student->streak_freezes_bought = 1;
    $this->student->save();

    $awarded = BadgeService::award($this->student, ['freeze_buy'], Carbon::now());
    expect($awarded->pluck('key'))->toContain('freeze_buy_1');
});

test('a fresh reader starts at zero against every target', function () {
    $values = BadgeService::progressValues($this->student, Carbon::now());

    expect($values['title_page_turner']['current'])->toBe(0)
        ->and($values['title_page_turner']['target'])->toBe(150)
        ->and($values['title_page_turner']['unit'])->toBe('pts')
        ->and($values['stories_5']['current'])->toBe(0)
        ->and($values['stories_5']['target'])->toBe(5)
        ->and($values['stories_5']['unit'])->toBeNull();
});

test('progress values pair every key with what the reader has and still needs', function () {
    $this->student->points = 65;
    $this->student->save();
    badgeStory($this->student);

    $values = BadgeService::progressValues($this->student, Carbon::now());
    expect($values['title_page_turner']['current'])->toBe(65)
        ->and($values['stories_5']['current'])->toBe(1)
        // Points beyond a tier's minimum read as "reached", never "over".
        ->and($values['title_bookworm']['current'])->toBe(50);

    $map = BadgeService::progressMap($this->student, Carbon::now());
    expect($map['title_page_turner'])->toBe(43)
        ->and($map['stories_5'])->toBe(20)
        ->and(array_keys($map))->toBe(array_keys($values));
});
