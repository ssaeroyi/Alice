<?php

use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Badge;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BadgeSeeder::class);
});

test('guests are redirected from the badges page', function () {
    $this->get(route('badges'))->assertRedirect(route('login'));
});

test('the badges page renders every badge with earned vs locked state', function () {
    $student = User::factory()->create(['is_setup_complete' => true]);

    $first = Badge::where('key', 'first_story')->firstOrFail();
    UserBadge::create(['user_id' => $student->id, 'badge_id' => $first->id, 'awarded_at' => Carbon::now()]);
    $completeUrl = route('badges');

    $this->actingAs($student)
        ->get($completeUrl)
        ->assertOk()
        ->assertSee('My Badges')
        ->assertSee('1 of '.count(config('badges.badges')).' unlocked')
        ->assertSee('First Story')
        ->assertSee('Perfect Month');

    $this->assertDatabaseHas('user_badges', [
        'user_id' => $student->id,
        'badge_id' => $first->id,
    ]);
});

test('a completed story is not automatically mirrored into the badges page state', function () {
    $student = User::factory()->create(['is_setup_complete' => true]);

    $story = Story::create([
        'title' => 'Solo Story',
        'author' => 'A',
        'synopsis' => 's',
        'content' => 'Some story content here for the test.',
        'word_count' => 100,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Easy',
        'status' => StoryStatus::Published,
    ]);
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => Carbon::now(),
    ]);

    $this->actingAs($student)
        ->get(route('badges'))
        ->assertSee('0 of '.count(config('badges.badges')).' unlocked');
});

test('locked badges show current against target instead of a percentage', function () {
    $student = User::factory()->create(['is_setup_complete' => true, 'points' => 65]);

    $this->actingAs($student)
        ->get(route('badges'))
        ->assertOk()
        ->assertSee('65 / 150 pts')
        ->assertDontSee('% there');
});

test('titles always state the goal while count badges wait for real progress', function () {
    $student = User::factory()->create(['is_setup_complete' => true]);

    $this->actingAs($student)
        ->get(route('badges'))
        ->assertOk()
        ->assertSee('0 / 150 pts')
        // 500 is only reachable through word_collector_500, so this is not a
        // substring of any title's "x / y pts" label.
        ->assertDontSee('0 / 500');
});

test('every card opens a detail modal carrying the unlock moment and student reach', function () {
    $holder = User::factory()->create(['is_setup_complete' => true]);
    $badge = Badge::where('key', 'first_story')->firstOrFail();
    UserBadge::create([
        'user_id' => $holder->id,
        'badge_id' => $badge->id,
        'awarded_at' => Carbon::create(2026, 8, 14, 15, 42),
    ]);

    // One holder among sixteen students is 6% (the Steam-style reach figure).
    User::factory()->count(15)->create(['is_setup_complete' => true]);

    $this->actingAs($holder)
        ->get(route('badges'))
        ->assertOk()
        ->assertSee('id="badge-detail-modal"', false)
        ->assertSee('data-modal-open="badge-detail-modal"', false)
        // Blade escapes the payload's quotes for the attribute, and json_encode
        // escapes the middle dot, so assert the emitted form verbatim.
        ->assertSee('&quot;unlockedAt&quot;:&quot;Aug 14, 2026 \u00b7 3:42 PM&quot;', false)
        ->assertSee('&quot;percent&quot;:6', false);
});

test('a locked badge detail carries progress instead of an unlock moment', function () {
    $student = User::factory()->create(['is_setup_complete' => true]);

    $this->actingAs($student)
        ->get(route('badges'))
        ->assertOk()
        ->assertSee('&quot;unlockedAt&quot;:null', false)
        ->assertSee('&quot;hasProgress&quot;:true', false)
        ->assertSee('&quot;students&quot;:1', false);
});

test('reach counts only real students and only titles their points still earn', function () {
    $stale = User::factory()->create(['is_setup_complete' => true, 'points' => 2460]);
    User::factory()->create(['is_setup_complete' => true]);

    // Demo, preview and test accounts exist but never enter the reach figure.
    User::factory()->create(['email' => 'demo.student@example.com', 'is_setup_complete' => true]);
    User::factory()->create(['email' => 'preview.student@scratch.alice.local', 'is_setup_complete' => true]);
    User::factory()->create(['email' => 'capstone.student@alice.test', 'is_setup_complete' => true]);

    $firstStory = Badge::where('key', 'first_story')->firstOrFail();
    $literaryAce = Badge::where('key', 'title_literary_ace')->firstOrFail();

    // The demo account holds first_story; the Literary Ace row sits on a real
    // student whose 2460 points no longer reach the 3600 point tier.
    UserBadge::create([
        'user_id' => User::where('email', 'demo.student@example.com')->value('id'),
        'badge_id' => $firstStory->id,
        'awarded_at' => Carbon::now(),
    ]);
    UserBadge::create([
        'user_id' => $stale->id,
        'badge_id' => $literaryAce->id,
        'awarded_at' => Carbon::now(),
    ]);

    $stats = BadgeService::unlockStats();

    expect($stats['students'])->toBe(2)
        ->and($stats['holders'][$firstStory->id] ?? 0)->toBe(0)
        ->and($stats['holders'][$literaryAce->id] ?? 0)->toBe(0);
});

test('the badges page advertises reach from real students only', function () {
    $viewer = User::factory()->create(['is_setup_complete' => true]);

    User::factory()->create(['email' => 'demo.student@example.com', 'is_setup_complete' => true]);
    User::factory()->create(['email' => 'preview.student@scratch.alice.local', 'is_setup_complete' => true]);
    User::factory()->create(['email' => 'capstone.student@alice.test', 'is_setup_complete' => true]);

    $this->actingAs($viewer)
        ->get(route('badges'))
        ->assertOk()
        ->assertSee('&quot;students&quot;:1', false);
});

test('story badges spell out that finishing means passing the quiz', function () {
    $student = User::factory()->create(['is_setup_complete' => true]);

    $this->actingAs($student)
        ->get(route('badges'))
        ->assertOk()
        ->assertSee('Finish your very first story: read it, then pass its quiz.', false)
        ->assertSee('Read 5 stories and pass their quizzes.', false);
});
