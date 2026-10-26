<?php

use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Badge;
use App\Models\GameRound;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
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

function catalogBadgeStory(User $user): Story
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

    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => Carbon::now(),
    ]);

    return $story;
}

test('catalog integrity: every config badge key is seeded, handled by the service, and has a real icon asset', function () {
    $defs = config('badges.badges', []);
    $seedKeys = Badge::pluck('key')->all();

    expect(count($defs))->toBeGreaterThanOrEqual(45);

    foreach ($defs as $def) {
        expect($seedKeys)->toContain($def['key'])
            ->and($def['category'])->toBeString()
            ->and($def['rarity'])->toBeIn(['common', 'rare', 'epic'])
            ->and(public_path('image/game/icons/ui/'.$def['icon'].'.svg'))
            ->toBeFile()
            ->and(BadgeService::eligible($this->student, $def['key'], Carbon::now()))->toBeBool();

        $pct = BadgeService::progress($this->student, $def['key']);
        expect($pct)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
});

test('reading minutes progress moves with accumulated reading time', function () {
    $this->student->reading_seconds_today = null;
    UserDailyActivity::create([
        'user_id' => $this->student->id,
        'activity_date' => now()->toDateString(),
        'reading_seconds_today' => 3600,
    ]);

    expect(BadgeService::progress($this->student, 'reading_60'))->toBe(100)
        ->and(BadgeService::progress($this->student, 'reading_300'))->toBeLessThan(100);
});

test('story count progress reflects completed stories', function () {
    foreach (range(1, 3) as $i) {
        catalogBadgeStory($this->student);
    }

    expect(BadgeService::progress($this->student, 'stories_5'))->toBe(60)
        ->and(BadgeService::eligible($this->student, 'stories_5', Carbon::now()))->toBeFalse();
});

test('drill progress counts words that have been reviewed', function () {
    foreach (['dusk', 'dawn', 'gleam'] as $w) {
        SavedWord::create([
            'user_id' => $this->student->id,
            'word' => $w,
            'definition' => 'test',
            'last_drilled_at' => now(),
        ]);
    }
    SavedWord::create([
        'user_id' => $this->student->id,
        'word' => 'never',
        'definition' => 'test',
        'last_drilled_at' => null,
    ]);

    expect(BadgeService::progress($this->student, 'drill_10'))->toBe(30);
});

test('game progress counts all rounds and per-type rounds', function () {
    foreach (range(1, 3) as $i) {
        GameRound::create([
            'user_id' => $this->student->id,
            'game_type' => 'wordsearch',
            'score' => 120,
            'words_count' => 5,
            'round_id' => (string) Str::uuid(),
        ]);
    }

    expect(BadgeService::progress($this->student, 'game_10'))->toBe(30)
        ->and(BadgeService::progress($this->student, 'ws_5'))->toBe(60);
});
