<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => Role::Teacher]);
    $this->student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
    ]);
    $this->story = Story::create([
        'title' => 'Inbox Story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Some content for the story. Enough words to be valid.',
        'status' => StoryStatus::Published,
        'created_by' => $this->teacher->id,
    ]);
});

function inboxAssignment(User $teacher, int $storyId, array $overrides = []): Assignment
{
    return Assignment::create(array_merge([
        'teacher_id' => $teacher->id,
        'story_id' => $storyId,
        'title' => 'Inbox Assignment',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'INBOX001',
    ], $overrides));
}

test('assignment.new dispatches a notification to the audience', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id);

    $sent = NotificationService::assignmentNew($assignment);

    $this->assertSame(1, $sent);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
        'link' => route('student.assignments.show', $assignment),
    ]);
});

test('assignment.new is de-duplicated for the same assignment and student', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id);

    NotificationService::assignmentNew($assignment);
    NotificationService::assignmentNew($assignment);

    $this->assertDatabaseCount('notifications', 1);
});

test('reminders skip students who already completed the assignment', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id, [
        'due_at' => now()->addHour(),
    ]);

    // Student already read the story within the gate.
    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => 'read',
        'completed_at' => now(),
    ]);

    $sent = NotificationService::reminder($assignment, 'assignment.due_soon');

    $this->assertSame(0, $sent);
    $this->assertDatabaseCount('notifications', 0);
});

test('reminders dispatch to incomplete students once', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id, [
        'due_at' => now()->addHour(),
    ]);

    $first = NotificationService::reminder($assignment, 'assignment.due_soon');
    $second = NotificationService::reminder($assignment, 'assignment.due_soon');

    $this->assertSame(1, $first);
    $this->assertSame(0, $second);
    $this->assertDatabaseCount('notifications', 1);
});

test('assignment.updated coalesces to one notice per day', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id);

    NotificationService::assignmentUpdated($assignment);
    NotificationService::assignmentUpdated($assignment);

    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'assignment.updated',
    ]);
});

test('student inbox lists notifications newest first', function () {
    inboxAssignment($this->teacher, $this->story->id);
    NotificationService::assignmentNew(
        Assignment::where('title', 'Inbox Assignment')->first()
    );

    $this->actingAs($this->student)
        ->get(route('inbox'))
        ->assertOk()
        ->assertSee('Inbox Assignment');
});

test('student can mark a single notification read and follow its link', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id);
    NotificationService::assignmentNew($assignment);

    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($this->student)
        ->post(route('inbox.read', $notification))
        ->assertRedirect(route('student.assignments.show', $assignment));

    $this->assertNotNull($notification->fresh()->read_at);
});

test('student can mark all notifications read', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id);
    NotificationService::assignmentNew($assignment);
    NotificationService::assignmentUpdated($assignment);

    $this->actingAs($this->student)
        ->post(route('inbox.read-all'))
        ->assertRedirect();

    $this->assertSame(
        0,
        Notification::where('user_id', $this->student->id)->unread()->count()
    );
});

test('one student cannot mark another students notification read', function () {
    $other = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    $assignment = inboxAssignment($this->teacher, $this->story->id);
    NotificationService::assignmentNew($assignment);

    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($other)
        ->post(route('inbox.read', $notification))
        ->assertForbidden();
});

test('student assignment detail view shows the teacher and task', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id, [
        'instructions' => 'Read carefully and take the quiz.',
    ]);

    $this->actingAs($this->student)
        ->get(route('student.assignments.show', $assignment))
        ->assertOk()
        ->assertSee($this->teacher->first_name)
        ->assertSee('Read carefully and take the quiz')
        ->assertSee('Start reading');
});

test('active assignment detail is viewable by a non-member via the shared link', function () {
    $outsider = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Shared Link',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'SECTION',
    ]);

    $this->actingAs($outsider)
        ->get(route('student.assignments.show', $assignment))
        ->assertOk()
        ->assertSee($assignment->title);
});

test('cancelled assignment is not viewable', function () {
    $outsider = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'title' => 'Cancelled Work',
        'scope' => 'all',
        'status' => 'cancelled',
        'assigned_at' => now(),
        'share_code' => 'CANCEL',
    ]);

    $this->actingAs($outsider)
        ->get(route('student.assignments.show', $assignment))
        ->assertNotFound();
});

test('student inbox tabs filter by type and unread state', function () {
    inboxAssignment($this->teacher, $this->story->id);
    $assignment = Assignment::where('title', 'Inbox Assignment')->first();
    NotificationService::assignmentNew($assignment);
    NotificationService::assignmentUpdated($assignment);

    $new = Notification::where('user_id', $this->student->id)->where('type', 'assignment.new')->first();
    $new->update(['read_at' => now()]);

    Notification::create([
        'user_id' => $this->student->id,
        'type' => 'class.invite.teacher',
        'title' => 'You\'re invited to "7 Diamond"',
        'body' => 'Your teacher',
        'link' => 'class-invite:1',
    ]);

    // All: both the read assignment notice and the pending invite, with pills.
    $this->actingAs($this->student)
        ->get(route('inbox'))
        ->assertOk()
        ->assertSee('New assignment: Inbox Assignment')
        ->assertSee('Assignment updated: Inbox Assignment')
        ->assertSee('>New<', false)
        ->assertSee('>Updated<', false)
        ->assertSee('You\'re invited to');

    $this->actingAs($this->student)
        ->get(route('inbox', ['filter' => 'assignments']))
        ->assertOk()
        ->assertSee('New assignment: Inbox Assignment')
        ->assertDontSee('You\'re invited to');

    $this->actingAs($this->student)
        ->get(route('inbox', ['filter' => 'invites']))
        ->assertOk()
        ->assertSee('You\'re invited to')
        ->assertSee('Accept')
        ->assertDontSee('Inbox Assignment');

    $this->actingAs($this->student)
        ->get(route('inbox', ['filter' => 'unread']))
        ->assertOk()
        ->assertSee('Assignment updated: Inbox Assignment')
        ->assertDontSee('New assignment: Inbox Assignment');

    // Unknown filter values fall back to the All tab.
    $this->actingAs($this->student)
        ->get(route('inbox', ['filter' => 'banana']))
        ->assertOk()
        ->assertSee('New assignment: Inbox Assignment')
        ->assertSee('You\'re invited to');
});

test('student inbox tab counts reflect the full inbox', function () {
    inboxAssignment($this->teacher, $this->story->id);
    $assignment = Assignment::where('title', 'Inbox Assignment')->first();
    NotificationService::assignmentNew($assignment);

    Notification::create([
        'user_id' => $this->student->id,
        'type' => 'class.invite.teacher',
        'title' => 'You\'re invited to "7 Diamond"',
        'body' => 'Your teacher',
        'link' => 'class-invite:1',
    ]);

    $this->actingAs($this->student)
        ->get(route('inbox'))
        ->assertOk()
        // All = 2 (one assignment notice + one invite).
        ->assertSee('>2<', false);
});

test('following a stale assignment notice lands on the hub instead of 404', function () {
    inboxAssignment($this->teacher, $this->story->id, ['status' => 'cancelled']);
    $assignment = Assignment::where('title', 'Inbox Assignment')->first();

    $notification = Notification::create([
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
        'title' => 'New assignment: Inbox Assignment',
        'body' => $this->story->title,
        'link' => route('student.assignments.show', $assignment),
    ]);

    $this->actingAs($this->student)
        ->post(route('inbox.read', $notification))
        ->assertRedirect(route('hub'));

    $this->assertNotNull($notification->fresh()->read_at);
});

test('completing an assignment clears its bell-dot notifications but not invites or other stories', function () {
    $assignment = inboxAssignment($this->teacher, $this->story->id);

    Notification::create([
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
        'title' => 'New assignment: '.$assignment->title,
        'body' => $this->story->title,
        'link' => route('student.assignments.show', $assignment),
        'read_at' => null,
    ]);
    Notification::create([
        'user_id' => $this->student->id,
        'type' => 'assignment.due_soon',
        'title' => 'Due soon: '.$assignment->title,
        'body' => 'This assignment is due soon.',
        'link' => route('student.assignments.show', $assignment),
        'read_at' => null,
    ]);
    Notification::create([
        'user_id' => $this->student->id,
        'type' => 'class.invite.teacher',
        'title' => 'You\'re invited to "7 Diamond"',
        'body' => 'Your teacher',
        'link' => 'class-invite:1',
        'read_at' => null,
    ]);

    // Another story + assignment the student has NOT completed — its notice
    // must stay unread so the dot isn't over-cleared.
    $otherStory = Story::create([
        'title' => 'Other Story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Some content for the other story. Enough words.',
        'status' => StoryStatus::Published,
        'created_by' => $this->teacher->id,
    ]);
    $otherAssignment = inboxAssignment($this->teacher, $otherStory->id, [
        'title' => 'Pending Other',
        'share_code' => 'OTHERS2',
    ]);
    Notification::create([
        'user_id' => $this->student->id,
        'type' => 'assignment.new',
        'title' => 'New assignment: '.$otherAssignment->title,
        'body' => $otherStory->title,
        'link' => route('student.assignments.show', $otherAssignment),
        'read_at' => null,
    ]);

    // Mark the first story read after the assignment's gate — the assignment
    // is now genuinely completed.
    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => 'read',
        'completed_at' => now(),
    ]);

    NotificationService::markCompletedAssignmentsRead($this->student, (int) $this->story->id);

    // This story's assignment notices are read; the invite and the other
    // story's pending assignment stay unread.
    $this->assertNotNull(
        Notification::where('user_id', $this->student->id)
            ->where('type', 'assignment.new')
            ->where('link', route('student.assignments.show', $assignment))
            ->first()->read_at
    );
    $this->assertNotNull(
        Notification::where('user_id', $this->student->id)
            ->where('type', 'assignment.due_soon')
            ->where('link', route('student.assignments.show', $assignment))
            ->first()->read_at
    );
    $this->assertNull(
        Notification::where('user_id', $this->student->id)
            ->where('type', 'class.invite.teacher')
            ->first()->read_at
    );
    $this->assertNull(
        Notification::where('user_id', $this->student->id)
            ->where('type', 'assignment.new')
            ->where('link', route('student.assignments.show', $otherAssignment))
            ->first()->read_at
    );
});

test('deciding a class invite deletes the notification', function () {
    $group = Group::create([
        'teacher_id' => $this->teacher->id,
        'name' => '7 Diamond',
        'join_code' => Group::makeJoinCode(),
    ]);

    $notification = Notification::create([
        'user_id' => $this->student->id,
        'type' => 'class.invite.teacher',
        'title' => 'You\'re invited to "7 Diamond"',
        'body' => 'Your teacher',
        'link' => 'class-invite:'.$group->id,
    ]);

    $this->actingAs($this->student)
        ->post(route('student.classes.decide', $notification), ['decision' => 'accept'])
        ->assertRedirect();

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});
