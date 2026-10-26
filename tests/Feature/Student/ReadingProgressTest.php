<?php

use App\Enums\ComprehensionLevel;
use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\ReadingProgressService;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function rpStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'comprehension_bar' => 72,
        'reading_level' => 8,
        'is_setup_complete' => true,
        ...$overrides,
    ]);
}

function rpStory(array $overrides = []): Story
{
    return Story::create([
        'title' => 'Reading Progress Story',
        'author' => 'J. Doe',
        'synopsis' => 'A story about reading.',
        'content' => 'The words gathered on the page.',
        'word_count' => 500,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Intermediate',
        'status' => StoryStatus::Published,
        ...$overrides,
    ]);
}

function rpAttempt(User $user, Story $story, int $pct, array $extra = []): TestAttempt
{
    return TestAttempt::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'attempt_uuid' => (string) Str::uuid(),
        'score_pct' => $pct,
        'passed' => $pct >= 70,
        'correct_count' => $pct,
        'wrong_count' => 100 - $pct,
        'time_spent_seconds' => 60,
        'answers_json' => ['items' => []],
        ...$extra,
    ]);
}

function rpFinish(User $user, Story $story): void
{
    StoryProgress::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Read,
        'time_spent_seconds' => 600,
    ]);
}

test('no evidence falls back to the placement snapshot', function () {
    $student = rpStudent(['comprehension_bar' => 60, 'comprehension_level' => 'frustration']);

    $progress = ReadingProgressService::recompute($student);

    expect($progress->live_score)->toBe(60)
        ->and($progress->comprehension_level)->toBe(ComprehensionLevel::Frustration)
        ->and($progress->sample_count)->toBe(0);
});

test('sample count is per distinct story, not per attempt (best-per-story)', function () {
    $student = rpStudent();
    $storyA = rpStory(['id' => 1]);
    $storyB = rpStory(['id' => 2]);

    rpAttempt($student, $storyA, 80);
    rpAttempt($student, $storyA, 90); // best attempt on the same story — must not double count
    rpAttempt($student, $storyB, 70);

    $progress = ReadingProgressService::recompute($student);

    expect($progress->sample_count)->toBe(2)  // two distinct stories
        ->and($progress->distinct_grades)->toBe(1); // both grade 8
});

test('a frustrated placement student never shows a lower level than they started', function () {
    $student = rpStudent(['comprehension_bar' => 40, 'comprehension_level' => 'critical']);

    $progress = ReadingProgressService::recompute($student);

    expect($progress->comprehension_level)->toBe(ComprehensionLevel::Critical)
        ->and($progress->live_score)->toBeGreaterThanOrEqual(40);
});

test('slipping is flagged only for instructional/independent placement students', function () {
    $independent = rpStudent(['comprehension_level' => 'independent']);
    $critical = rpStudent(['comprehension_level' => 'critical']);

    // 5 recent quizzes, several failing, newest NOT improving on the oldest ->
    // slipping for independent.
    $scores = [60, 55, 58, 52, 55];
    foreach ($scores as $i => $pct) {
        rpAttempt($independent, rpStory(['id' => $i + 1, 'reading_level' => 9]), $pct, [
            'created_at' => now()->subMinutes($i),
        ]);
    }
    // Critical students are always protected, even on the same pattern.
    foreach ($scores as $i => $pct) {
        rpAttempt($critical, rpStory(['id' => 100 + $i, 'reading_level' => 9]), $pct, [
            'created_at' => now()->subMinutes($i),
        ]);
    }

    expect(ReadingProgressService::recompute($independent)->slipping)->toBeTrue()
        ->and(ReadingProgressService::recompute($critical)->slipping)->toBeFalse();
});

test('slipping requires a full recent window of quizzes', function () {
    $student = rpStudent(['comprehension_level' => 'instructional']);

    // Only 3 quizzes — below the 5-quiz window, so never flagged.
    foreach ([55, 60, 50] as $i => $pct) {
        rpAttempt($student, rpStory(['id' => $i + 1, 'reading_level' => 8]), $pct);
    }

    expect(ReadingProgressService::recompute($student)->slipping)->toBeFalse();
});

test('teacher score decays while the student live score does not', function () {
    $student = rpStudent(['comprehension_bar' => 90, 'comprehension_level' => 'independent']);

    // Recent, strongly-passing quizzes across two grades.
    rpAttempt($student, rpStory(['id' => 1, 'reading_level' => 8]), 95);
    rpAttempt($student, rpStory(['id' => 2, 'reading_level' => 9]), 95);
    rpFinish($student, rpStory(['id' => 1, 'reading_level' => 8]));

    $progress = ReadingProgressService::recompute($student);
    expect($progress->live_score)->not->toBeNull();

    // Simulate total inactivity well past the decay window.
    $this->travel(config('placement.reading_progress.decay_inactive_days') + 40)->days();

    $decayed = ReadingProgressService::recompute($student->fresh());

    expect($decayed->teacher_score)->toBeLessThan($decayed->live_score)
        ->and($decayed->live_score)->toBe($progress->live_score);
});

test('recommended band falls back to placement level without evidence', function () {
    $student = rpStudent();
    $storyAt8 = rpStory(['title' => 'Grade Eight Tale', 'reading_level' => 8]);
    rpStory(['title' => 'Way Off Grade', 'reading_level' => 10]);

    $this->actingAs($student)->get(route('library'))
        ->assertOk()
        ->assertSee('Grade 8')
        ->assertSee('Grade Eight Tale');
});

test('the live reading level shows in the hub stats panel', function () {
    $student = rpStudent();

    // No reading-progress evidence yet: the hub falls back to the placement grade.
    $this->actingAs($student)->get(route('hub'))
        ->assertOk()
        ->assertSee('Reading Level')
        ->assertSee('Grade 8');

    // A real quiz drives a live level that the hub reflects.
    $storyA = rpStory(['id' => 1, 'reading_level' => 8]);
    $storyB = rpStory(['id' => 2, 'reading_level' => 9]);
    $storyC = rpStory(['id' => 3, 'reading_level' => 8]);
    rpAttempt($student, $storyA, 90);
    rpAttempt($student, $storyB, 92);
    rpAttempt($student, $storyC, 88);

    ReadingProgressService::recompute($student);

    $this->actingAs($student->fresh())->get(route('hub'))
        ->assertOk()
        ->assertSee('Reading Level')
        ->assertSee('Based on your quizzes')
        ->assertSee('Grade 8');
});
