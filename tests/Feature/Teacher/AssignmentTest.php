<?php

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Question;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Support\ViewAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => Role::Teacher]);
    $this->student = User::factory()->create([
        'role' => Role::Student,
        'section' => 'Diamond',
    ]);
    $this->story = Story::create([
        'title' => 'The Lost Coin',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'source_url' => 'https://example.com/lost-coin',
        'synopsis' => 'A coin goes missing.',
        'content' => 'The coin was lost in the market square. A child found it.',
        'status' => StoryStatus::Published,
        'created_by' => $this->teacher->id,
    ]);

    $this->group = Group::create([
        'teacher_id' => $this->teacher->id,
        'name' => '7 Diamond',
        'join_code' => Group::makeJoinCode(),
    ]);
    $this->group->addMember($this->student);
});

test('teacher can open assignments index', function () {
    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.index'))
        ->assertOk()
        ->assertSee('Assignments');
});

test('student cannot access assignments', function () {
    $this->actingAs($this->student)
        ->get(route('teacher.assignments.index'))
        ->assertForbidden();
});

test('teacher can create assignment', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.store'), [
            'title' => 'Read The Lost Coin',
            'story_id' => $this->story->id,
            'scope' => 'group',
            'group_id' => $this->group->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assignments', [
        'title' => 'Read The Lost Coin',
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'scope' => 'group',
        'group_id' => $this->group->id,
        'status' => 'active',
    ]);
});

test('share code is generated on create', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.store'), [
            'title' => 'Test Assignment',
            'story_id' => $this->story->id,
            'scope' => 'group',
            'group_id' => $this->group->id,
            'status' => 'draft',
        ]);

    $assignment = Assignment::where('title', 'Test Assignment')->first();
    $this->assertNotNull($assignment->share_code);
    $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $assignment->share_code);
});

test('creating a class assignment redirects back to that class', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.store'), [
            'title' => 'Back To Class',
            'story_id' => $this->story->id,
            'scope' => 'group',
            'group_id' => $this->group->id,
            'status' => 'draft',
        ])
        ->assertRedirect(route('teacher.classes.show', $this->group));
});

test('teacher cannot edit another teacher assignment', function () {
    $otherTeacher = User::factory()->create(['role' => Role::Teacher]);
    $assignment = Assignment::create([
        'teacher_id' => $otherTeacher->id,
        'title' => 'Other Assignment',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'TESTCODE1',
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.edit', $assignment))
        ->assertForbidden();
});

test('admin can edit any assignment', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'title' => 'Teacher Assignment',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'ADMINCOD',
    ]);

    $this->actingAs($admin)
        ->get(route('teacher.assignments.edit', $assignment))
        ->assertOk();
});

test('active share code redirects to the assignment detail page', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Share Test',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'SHARE123',
    ]);

    $this->actingAs($this->student)
        ->get(route('assignment.share', 'SHARE123'))
        ->assertRedirect(route('student.assignments.show', $assignment));

    $this->assertDatabaseHas('assignments', [
        'share_code' => 'SHARE123',
        'clicks_count' => 1,
    ]);
});

test('inactive share code returns 404', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Draft Assignment',
        'scope' => 'all',
        'status' => 'draft',
        'share_code' => 'DRAFT001',
    ]);

    $this->actingAs($this->student)
        ->get(route('assignment.share', 'DRAFT001'))
        ->assertNotFound();
});

test('share code requires authentication', function () {
    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'title' => 'Auth Test',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'AUTHCODE',
    ]);

    $this->get(route('assignment.share', 'AUTHCODE'))
        ->assertRedirect(route('login'));
});

test('scratch preview students are excluded from the audience', function () {
    $real = User::factory()->create(['role' => Role::Student, 'section' => 'Emerald']);
    $scratch = User::factory()->create([
        'role' => Role::Student,
        'section' => 'Emerald',
        'email' => ViewAs::SCRATCH_STUDENT_EMAIL,
    ]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'title' => 'Scratch Excluded',
        'scope' => 'students',
        'status' => 'active',
        'share_code' => 'SCRATCH',
    ]);
    $assignment->targets()->createMany([
        ['user_id' => $real->id],
        ['user_id' => $scratch->id],
    ]);

    $this->assertSame(1, $assignment->audienceCount());
});

test('publishing a draft stamps assigned_at and notifies the audience', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Draft to Publish',
        'scope' => 'all',
        'status' => 'draft',
        'share_code' => 'PUBLISH',
    ]);

    $this->assertNull($assignment->assigned_at);

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.publish', $assignment))
        ->assertRedirect();

    $assignment->refresh();
    $this->assertNotNull($assignment->assigned_at);
    $this->assertSame('active', $assignment->status);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
    ]);
});

test('creating an assignment as active sets assigned_at and notifies immediately', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.store'), [
            'title' => 'Active right away',
            'story_id' => $this->story->id,
            'scope' => 'group',
            'group_id' => $this->group->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    $assignment = Assignment::where('title', 'Active right away')->first();
    $this->assertNotNull($assignment->assigned_at);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
    ]);
});

test('publishing again does not duplicate the new-assignment notice', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'No Dupes',
        'scope' => 'all',
        'status' => 'draft',
        'share_code' => 'NODUPE1',
    ]);

    $this->actingAs($this->teacher)->post(route('teacher.assignments.publish', $assignment));
    $this->actingAs($this->teacher)->post(route('teacher.assignments.publish', $assignment));

    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
    ]);
});

test('quiz-less assignment counts Reading after the gate as complete', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Quiz-less',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'QUIZLESS',
    ]);

    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => 'read',
        'completed_at' => now()->subHour(),
    ]);

    $this->assertTrue($assignment->completedFor($this->student));
    $this->assertSame(1, $assignment->completedCount());
});

test('prior reading before the assignment gate does not count as complete', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Gate Test',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'GATETST',
    ]);

    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => 'completed',
        'completed_at' => now()->subDays(5),
    ]);

    $this->assertFalse($assignment->completedFor($this->student));
    $this->assertSame(0, $assignment->completedCount());
});

test('quiz assignment requires reading AND a submitted attempt after the gate', function () {
    Question::create([
        'story_id' => $this->story->id,
        'question_text' => 'Where was the coin?',
        'type' => QuestionType::Mcq,
    ]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Quiz Story',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'QUIZSTO',
    ]);

    // Read but no attempt -> not complete.
    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => 'read',
        'completed_at' => now()->subHour(),
    ]);
    $this->assertFalse($assignment->completedFor($this->student));

    // Attempt before the gate -> still not complete (prior work excluded).
    $preAttempt = TestAttempt::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'score_pct' => 60,
        'passed' => false,
        'correct_count' => 1,
        'wrong_count' => 1,
        'time_spent_seconds' => 30,
    ]);
    $preAttempt->forceFill(['created_at' => now()->subDays(5)])->save();
    $this->assertFalse($assignment->completedFor($this->student));

    // Attempt after the gate -> complete (engagement, any score).
    $postAttempt = TestAttempt::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'score_pct' => 60,
        'passed' => false,
        'correct_count' => 1,
        'wrong_count' => 1,
        'time_spent_seconds' => 30,
    ]);
    $postAttempt->forceFill(['created_at' => now()->subMinutes(30)])->save();
    $this->assertTrue($assignment->completedFor($this->student));
    $this->assertSame(1, $assignment->completedCount());
});

test('teacher rollup renders student links with back-to-assignment context', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Rollup Render',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'ROLLUP',
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.show', $assignment))
        ->assertOk();

    $response->assertSee(route('teacher.student', [
        'user' => $this->student,
        'from' => 'assignment:'.$assignment->id,
    ]), false);
});

test('notify action dispatches a new-assignment notice to the audience and is idempotent', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Notify Me',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'NOTIFY99',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.notify', $assignment))
        ->assertRedirect();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
        'link' => route('student.assignments.show', $assignment),
    ]);

    $count = Notification::where('user_id', $this->student->id)
        ->where('type', 'assignment.new')
        ->where('link', route('student.assignments.show', $assignment))
        ->count();

    // Re-posting doesn't duplicate the notice for the same student.
    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.notify', $assignment))
        ->assertRedirect();

    $this->assertSame(1, Notification::where('user_id', $this->student->id)
        ->where('type', 'assignment.new')
        ->where('link', route('student.assignments.show', $assignment))
        ->count());
});

test('teacher can finish (archive) an active assignment and it logs an audit event', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'To Archive',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'ARCH987',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.archive', $assignment))
        ->assertRedirect();

    $assignment->refresh();
    $this->assertSame('archived', $assignment->status);
    $this->assertNotNull($assignment->finished_at);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'assignment.archived',
        'target_id' => $assignment->id,
    ]);
});

test('only the owning teacher (or admin) can archive an assignment', function () {
    $otherTeacher = User::factory()->create(['role' => Role::Teacher]);
    $assignment = Assignment::create([
        'teacher_id' => $otherTeacher->id,
        'story_id' => $this->story->id,
        'title' => 'Not Mine',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'NOTMINE',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.archive', $assignment))
        ->assertForbidden();

    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin)
        ->post(route('teacher.assignments.archive', $assignment))
        ->assertRedirect();

    $this->assertSame('archived', $assignment->fresh()->status);
});

test('archiving removes an assignment from active listings but keeps it in finished', function () {
    $active = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Stays Active',
        'scope' => 'group',
        'group_id' => $this->group->id,
        'status' => 'active',
        'share_code' => 'STAY332',
    ]);
    $archived = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Now Finished',
        'scope' => 'group',
        'group_id' => $this->group->id,
        'status' => 'archived',
        'finished_at' => now(),
        'share_code' => 'FINISHD',
    ]);

    $activeTitles = $this->student->activeAssignments()->pluck('title')->all();
    $this->assertContains('Stays Active', $activeTitles);
    $this->assertNotContains('Now Finished', $activeTitles);

    $finishedTitles = $this->student->finishedAssignments()->pluck('title')->all();
    $this->assertContains('Now Finished', $finishedTitles);
    $this->assertNotContains('Stays Active', $finishedTitles);
});

test('a student can still open a finished assignment detail page', function () {
    $this->student->forceFill(['is_setup_complete' => true])->save();

    $archived = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Past Work',
        'scope' => 'group',
        'group_id' => $this->group->id,
        'status' => 'archived',
        'finished_at' => now(),
        'share_code' => 'PASTW0K',
    ]);

    $this->actingAs($this->student)
        ->get(route('student.assignments.show', $archived))
        ->assertOk()
        ->assertSee('Past Work');
});

test('auto-archive command retires past-due active assignments and skips open-ended ones', function () {
    $pastDue = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Expired',
        'scope' => 'all',
        'status' => 'active',
        'due_at' => now()->subDay(),
        'share_code' => 'EXPIRE1',
    ]);
    $openEnded = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Open Ended',
        'scope' => 'all',
        'status' => 'active',
        'due_at' => null,
        'share_code' => 'OPENEND',
    ]);
    $futureDue = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Upcoming',
        'scope' => 'all',
        'status' => 'active',
        'due_at' => now()->addDays(3),
        'share_code' => 'FUTURED',
    ]);

    $this->artisan('alice:archive-expired-assignments')->assertExitCode(0);

    $this->assertSame('archived', $pastDue->fresh()->status);
    $this->assertSame('active', $openEnded->fresh()->status);
    $this->assertSame('active', $futureDue->fresh()->status);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'assignment.auto_archived',
        'target_id' => $pastDue->id,
    ]);
});

test('teacher index shows status tabs with counts and filters by tab', function () {
    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'The Active One',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'ACTVBAR',
    ]);
    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'The Finished One',
        'scope' => 'all',
        'status' => 'archived',
        'finished_at' => now(),
        'share_code' => 'FINBAR1',
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('The Active One')
        ->assertSee('Active');

    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.index', ['status' => 'archived']))
        ->assertOk()
        ->assertSee('The Finished One')
        ->assertSee('Finished');
});

test('assignment search matches assignment and story titles across tabs', function () {
    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Weekend Whitewater',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'WATER0A',
    ]);
    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'A Quiet Read',
        'scope' => 'all',
        'status' => 'draft',
        'share_code' => 'QUIET0B',
    ]);
    $secondStory = Story::create([
        'title' => 'The Golden Compass',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'synopsis' => 'A compass points north.',
        'content' => 'The needle always pointed north.',
        'status' => StoryStatus::Published,
        'created_by' => $this->teacher->id,
    ]);
    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $secondStory->id,
        'title' => 'Map Assignment',
        'scope' => 'all',
        'status' => 'active',
        'share_code' => 'COMPAS1',
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.index', ['status' => 'active', 'q' => 'Whitewater']))
        ->assertOk()
        ->assertSee('Weekend Whitewater')
        ->assertDontSee('A Quiet Read')
        ->assertDontSee('Map Assignment');

    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.index', ['status' => 'active', 'q' => 'The Golden Compass']))
        ->assertOk()
        ->assertSee('Map Assignment')
        ->assertDontSee('Weekend Whitewater');
});

test('rollup shows the student nickname and avatar, not blank first/last names', function () {
    User::factory()->create([
        'role' => Role::Student,
        'first_name' => '',
        'last_name' => '',
        'nickname' => 'nickonly',
        'avatar' => 'sprout.png',
    ]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Name Render',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'NAMESH',
    ]);

    $html = $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.show', $assignment))
        ->assertOk()
        ->getContent();

    $this->assertStringContainsString('nickonly', $html);
    $this->assertStringContainsString('image/avatar/sprout.png', $html);
});

test('rollup search narrows the audience by nickname', function () {
    $other = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'zestfinder',
    ]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Searchable',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'SEARCHQ',
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.show', [$assignment, 'q' => 'zestfinder']))
        ->assertOk()
        ->assertSee('zestfinder')
        ->assertDontSee($this->student->nickname);
});

test('rollup quiz score shows the raw X of Y correct beside the percentage', function () {
    Question::create([
        'story_id' => $this->story->id,
        'question_text' => 'Where was the coin?',
        'type' => QuestionType::Mcq,
    ]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Score Render',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'SCOREE',
    ]);

    TestAttempt::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'score_pct' => 60,
        'passed' => true,
        'correct_count' => 3,
        'wrong_count' => 2,
        'time_spent_seconds' => 30,
    ]);

    $html = $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.show', $assignment))
        ->assertOk()
        ->getContent();

    $this->assertStringContainsString('3 of 5 correct', $html);
    $this->assertStringContainsString('60%', $html);
});

test('teacher marks a student done; undo restores the not-started state', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Mark Me',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'MARKNG',
    ]);

    $this->assertFalse($assignment->completedFor($this->student));
    $this->assertSame(0, $assignment->completedCount());

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.mark', $assignment), ['student_id' => $this->student->id])
        ->assertRedirect();

    $this->assertDatabaseHas('assignment_marks', [
        'assignment_id' => $assignment->id,
        'user_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.marked', 'target_id' => $assignment->id]);
    $this->assertTrue($assignment->completedFor($this->student));
    $this->assertSame(1, $assignment->completedCount());

    $html = $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.show', $assignment))
        ->assertOk()
        ->getContent();
    $this->assertStringContainsString('Done (teacher)', $html);

    $this->actingAs($this->teacher)
        ->delete(route('teacher.assignments.mark-remove', [$assignment, $this->student]))
        ->assertRedirect();

    $this->assertDatabaseMissing('assignment_marks', [
        'assignment_id' => $assignment->id,
        'user_id' => $this->student->id,
    ]);
    $this->assertFalse($assignment->completedFor($this->student));
    $this->assertSame(0, $assignment->completedCount());
});

test('mark-done works for group-scope students with no targets row', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $this->group->id,
        'title' => 'Group Mark',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'GRPMRK',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.mark', $assignment), ['student_id' => $this->student->id])
        ->assertRedirect();

    $this->assertTrue($assignment->completedFor($this->student));
});

test('only the owning teacher marks; a non-audience student is rejected', function () {
    $intruder = User::factory()->create(['role' => Role::Teacher]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $this->group->id,
        'title' => 'Owner Mark',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'OWNMRK',
    ]);

    $outsider = User::factory()->create([
        'role' => Role::Student,
        'email' => 'excluded@example.test',
    ]);

    $this->actingAs($intruder)
        ->post(route('teacher.assignments.mark', $assignment), ['student_id' => $this->student->id])
        ->assertForbidden();

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.mark', $assignment), ['student_id' => $outsider->id])
        ->assertStatus(422);
});

test('results can be exported as a CSV honoring the search', function () {
    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'CSV Export',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'CSVEXP',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.assignments.mark', $assignment), ['student_id' => $this->student->id])
        ->assertRedirect();

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.assignments.export', ['assignment' => $assignment, 'q' => $this->student->nickname]))
        ->assertOk();

    $csv = $response->streamedContent();
    $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    $this->assertStringContainsString('Student,Status,"Quiz score",Percentage,Passed,"Teacher marked"', $csv);
    $this->assertStringContainsString($this->student->nickname.',"Done (teacher)"', $csv);
});
