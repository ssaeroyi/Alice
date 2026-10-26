<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Models\Assignment;
use App\Models\AssignmentMark;
use App\Models\Group;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Support\QuestionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The lifecycle that makes an assignment genuinely complete — and the
 * celebration that fires exactly once when it does. Reading evidence is stored
 * on story_progress.completed_at (heartbeat/finish), the quiz step accepts any
 * submitted attempt after the gate, and the one-shot "Assignment complete"
 * banner rides the PRG target only on the true false -> true transition.
 */
function assignmentStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'reading_level' => 8,
        'is_setup_complete' => true,
        ...$overrides,
    ]);
}

function assignmentStory(array $overrides = []): Story
{
    return Story::create([
        'title' => 'The Clockwork Garden',
        'author' => 'L. M. Everett',
        'synopsis' => 'A short test story.',
        'content' => 'Mira found the brass key beside the greenhouse door.',
        'word_count' => 120,
        'reading_level' => 8,
        'status' => 'published',
        ...$overrides,
    ]);
}

function assignmentFor(User $teacher, int $storyId, array $overrides = []): Assignment
{
    return Assignment::create([
        'teacher_id' => $teacher->id,
        'story_id' => $storyId,
        'title' => 'Read The Garden',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => strtoupper(Str::random(8)),
        ...$overrides,
    ]);
}

function mt_quizAttemptUuid($case, User $student, Story $story): string
{
    preg_match('/data-attempt="([0-9a-f-]{36})"/', $case->actingAs($student)->get(route('reader.quiz', $story))->getContent(), $m);

    return $m[1];
}

test('heartbeat stamps completed_at when the read threshold is reached', function () {
    $student = assignmentStudent();
    $story = assignmentStory();

    $this->actingAs($student)->get(route('reader.show', $story));
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 95, 'seconds_delta' => 5])->assertOk();

    $progress = StoryProgress::query()->sole();
    expect($progress->status->value)->toBe('read')
        ->and($progress->completed_at)->not->toBeNull();
});

test('finish stamps completed_at for the reader evidence', function () {
    $student = assignmentStudent();
    $story = assignmentStory();

    $this->actingAs($student)->get(route('reader.show', $story));
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $progress = StoryProgress::query()->sole();
    expect($progress->status->value)->toBe('read')
        ->and($progress->completed_at)->not->toBeNull();
});

test('a story finished before the assignment never counts; a post-gate reread does', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();

    $assignment = assignmentFor($teacher, $story->id, ['assigned_at' => now()->subDay()]);

    // Finished long before the assignment went live: the gate keeps it out.
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => now()->subDays(5),
    ]);
    expect($assignment->completedFor($student))->toBeFalse(); // "must re-engage"

    // A reread after the assignment: any real reading beat refreshes
    // completed_at past the gate, so the assignment can now be satisfied.
    $this->actingAs($student)->get(route('reader.show', $story));
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 80, 'seconds_delta' => 30])->assertOk();

    expect($assignment->completedFor($student))->toBeTrue();
});

test('finishing a story-only assignment completes it and flashes the banner once', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();

    $assignment = assignmentFor($teacher, $story->id);

    $this->actingAs($student)->get(route('reader.show', $story));

    $this->post(route('reader.finish', $story))
        ->assertRedirect(route('reader.summary', $story))
        ->assertSessionHas('assignment_complete.title', $assignment->title);

    expect($assignment->completedFor($student))->toBeTrue();

    // The PRG target carries the celebration banner exactly once.
    $html = $this->get(route('reader.summary', $story))->getContent();
    expect($html)
        ->toContain('Assignment complete!')
        ->toContain($assignment->title)
        ->toContain('You completed it.');

    // A second finish is a reread of already-done work: no new celebration
    // flash can ever ride out of the transition.
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));
    $html = $this->get(route('reader.summary', $story))->getContent();
    expect($html)->not->toContain('Assignment complete!');
});

test('a teacher mark-done never suppresses the student\'s genuine finish celebration', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();
    $assignment = assignmentFor($teacher, $story->id);

    // Teacher marks the student done before they ever finish: the rollup and
    // compliance see "Done", but genuine evidence has not happened yet.
    AssignmentMark::create([
        'assignment_id' => $assignment->id,
        'user_id' => $student->id,
        'teacher_id' => $teacher->id,
    ]);
    expect($assignment->completedFor($student))->toBeTrue()
        ->and($assignment->completedByEvidence($student))->toBeFalse();

    // The student then genuinely finishes: the celebration must still fire.
    $this->actingAs($student)->get(route('reader.show', $story));
    $this->post(route('reader.finish', $story))
        ->assertRedirect(route('reader.summary', $story))
        ->assertSessionHas('assignment_complete.title', $assignment->title);

    $html = $this->get(route('reader.summary', $story))->getContent();
    expect($html)
        ->toContain('Assignment complete!')
        ->toContain('You completed it.');
});

test('a quiz attempt after the gate — any score — completes the assignment and flashes on the results page', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();
    $section = QuestionSection::create(['story_id' => $story->id, 'name' => 'Checks', 'position' => 1]);
    $mcq = QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'Which word means nearly the same as forgotten?',
        'option_a' => 'Neglected',
        'option_b' => 'Celebrated',
        'option_c' => 'Rebuilt',
        'option_d' => 'Watered',
        'correct_option' => 'a',
        'section_id' => $section->id,
    ]);

    $assignment = assignmentFor($teacher, $story->id);

    // Read after the gate alone is not enough: the quiz step is pending.
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 90,
        'completed_at' => now()->subMinutes(30),
    ]);
    $this->actingAs($student);
    expect($assignment->completedFor($student))->toBeFalse();

    $uuid = mt_quizAttemptUuid($this, $student, $story);
    $this->postJson(route('reader.quiz.submit', $story), [
        'attempt_uuid' => $uuid,
        'answers' => [['question_id' => $mcq->id, 'value' => 'b']],
        'time_spent_seconds' => 30,
    ])->assertOk()->assertJsonPath('ok', true);

    // Any submitted attempt (even a failing one) is the engagement signal.
    expect(TestAttempt::query()->count())->toBe(1)
        ->and($assignment->completedFor($student))->toBeTrue();

    $html = $this->get(route('reader.quiz.results', [$story, $uuid]))->getContent();
    expect($html)->toContain('Assignment complete!');
});

test('the assignment page checklist shows live steps and a single next action', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();
    $assignment = assignmentFor($teacher, $story->id);
    $route = route('student.assignments.show', $assignment);

    // Nothing done yet: unread step, "0 of 1 done", one primary action.
    $html = $this->actingAs($student)->get($route)->getContent();
    expect($html)
        ->toContain('Your task')
        ->toContain('0 of 1 done')
        ->toContain('Start reading')
        ->not->toContain('Assignment complete');
    expect(substr_count($html, 'Not yet'))->toBe(1);

    // Read after the gate: step flips to Done and the celebration panel lands.
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 100,
        'completed_at' => now(),
    ]);

    $html = $this->actingAs($student)->get($route)->getContent();
    expect($html)
        ->toContain('1 of 1 done')
        ->toContain('Assignment complete')
        ->toContain('Read again');
    expect(substr_count($html, 'Not yet'))->toBe(0);
});

test('a read quiz assignment nudges the student to the quiz with a live checklist', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();
    QuestionSection::create(['story_id' => $story->id, 'name' => 'Checks', 'position' => 1]);
    QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'Which word means nearly the same as forgotten?',
        'option_a' => 'Neglected',
        'option_b' => 'Celebrated',
        'option_c' => 'Rebuilt',
        'option_d' => 'Watered',
        'correct_option' => 'a',
        'section_id' => QuestionSection::query()->where('story_id', $story->id)->first()->id,
    ]);
    $assignment = assignmentFor($teacher, $story->id);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 100,
        'completed_at' => now(),
    ]);

    $html = $this->actingAs($student)->get(route('student.assignments.show', $assignment))->getContent();
    expect($html)
        ->toContain('1 of 2 done')
        ->toContain('Take the quiz')
        ->toContain('Any score marks it done');
    expect(substr_count($html, 'Not yet'))->toBe(1);
});

test('the assignment page has a back link to classwork', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();
    $assignment = assignmentFor($teacher, $story->id);

    $html = $this->actingAs($student)->get(route('student.assignments.show', $assignment))->getContent();

    $this->assertStringContainsString('aria-label="Back to classwork"', $html);
    $this->assertStringContainsString(route('student.classwork'), $html);
    $this->assertStringNotContainsString('aria-label="Breadcrumb"', $html);
});

test('a class-scoped assignment back link lands inside its class drill-down', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();

    $class = Group::create([
        'teacher_id' => $teacher->id,
        'name' => 'Grade 9 - Beatrix',
        'join_code' => Group::makeJoinCode(),
    ]);
    $class->addMember($student);
    $assignment = assignmentFor($teacher, $story->id, ['group_id' => $class->id]);

    $html = $this->actingAs($student)->get(route('student.assignments.show', $assignment))->getContent();

    $this->assertStringContainsString(
        route('student.classwork') . '?class=' . $class->id,
        $html
    );
});

test('the hub shows assignments on Home and a consistent status pill on rows', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = assignmentStudent();
    $story = assignmentStory();
    $assignment = assignmentFor($teacher, $story->id);

    $html = $this->actingAs($student)->get(route('hub'))->getContent();

    expect($html)
        ->toContain('CLASSWORK')
        ->toContain('From your teacher')
        ->toContain($assignment->title)
        ->toContain('In progress')
        ->not->toContain('Class &amp; Assignments')
        ->not->toContain('Class & Assignments');

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'completed_at' => now(),
    ]);

    $html = $this->actingAs($student)->get(route('hub'))->getContent();
    expect($html)->toContain($assignment->title)
        ->and(substr_count($html, 'Done'))->toBeGreaterThanOrEqual(1);
});
