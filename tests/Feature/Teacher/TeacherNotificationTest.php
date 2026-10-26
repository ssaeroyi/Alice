<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Support\NotificationService;
use App\Support\ViewAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => Role::Teacher]);
    $this->student = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    $this->story = Story::create([
        'title' => 'Finished Story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Story content with enough words to be valid.',
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

function completedAssignment(Group $group, Story $story, int $teacherId): Assignment
{
    return Assignment::create([
        'teacher_id' => $teacherId,
        'story_id' => $story->id,
        'group_id' => $group->id,
        'title' => 'Finish This',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'FIN0001',
    ]);
}

test('completing an assigned story notifies the owning teacher once', function () {
    $assignment = completedAssignment($this->group, $this->story, $this->teacher->id);

    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 100,
        'completed_at' => now(),
    ]);

    NotificationService::studentCompletedStory($this->student, $this->story->id);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->teacher->id,
        'type' => 'assignment.completed.teacher',
        'link' => 'assignment-complete:'.$assignment->id.':'.$this->student->id,
        'title' => 'Assignment finished: Finish This',
    ]);

    // Re-reads / re-submits never stack a second notice.
    NotificationService::studentCompletedStory($this->student, $this->story->id);

    $this->assertSame(1, Notification::where('user_id', $this->teacher->id)
        ->where('type', 'assignment.completed.teacher')
        ->count());
});

test('only active group-scoped assignments notify their own teacher', function () {
    $otherTeacher = User::factory()->create(['role' => Role::Teacher]);
    $otherGroup = Group::create([
        'teacher_id' => $otherTeacher->id,
        'name' => '9 Ruby',
        'join_code' => Group::makeJoinCode(),
    ]);
    $otherGroup->addMember($this->student);

    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $this->group->id,
        'title' => 'Draft One',
        'scope' => 'group',
        'status' => 'draft',
        'share_code' => 'DRFT01',
    ]);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $this->group->id,
        'title' => 'Active One',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'ACTV01',
    ]);

    StoryProgress::create([
        'user_id' => $this->student->id,
        'story_id' => $this->story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 100,
        'completed_at' => now(),
    ]);

    NotificationService::studentCompletedStory($this->student, $this->story->id);

    // Only the active assignment, and only this teacher, got the notice.
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->teacher->id,
        'type' => 'assignment.completed.teacher',
        'link' => 'assignment-complete:'.$assignment->id.':'.$this->student->id,
    ]);
    $this->assertDatabaseMissing('notifications', ['user_id' => $otherTeacher->id]);
    $this->assertSame(1, Notification::where('type', 'assignment.completed.teacher')->count());
});

test('scratch preview students never notify a teacher', function () {
    completedAssignment($this->group, $this->story, $this->teacher->id);

    $scratch = User::factory()->create([
        'role' => Role::Student,
        'email' => 'preview.student@'.ViewAs::SCRATCH_DOMAIN,
        'nickname' => 'Preview Student',
    ]);
    $this->group->addMember($scratch);

    StoryProgress::create([
        'user_id' => $scratch->id,
        'story_id' => $this->story->id,
        'status' => StoryProgressStatus::Read,
        'current_page' => 100,
        'completed_at' => now(),
    ]);

    NotificationService::studentCompletedStory($scratch, $this->story->id);

    $this->assertDatabaseCount('notifications', 0);
});

test('teacher bell badges the unread count', function () {
    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $i) {
        Notification::create([
            'user_id' => $this->teacher->id,
            'type' => 'class.joined.teacher',
            'title' => 'New student in 7 Diamond',
            'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
            'link' => 'class-joined:'.$this->group->id.':'.$this->student->id.$i,
        ]);
    }

    $this->actingAs($this->teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee(route('teacher.inbox'), false)
        ->assertSee('Inbox, 12 unread');
});

test('teacher bell caps the unread badge at 99+', function () {
    foreach (range(1, 100) as $i) {
        Notification::create([
            'user_id' => $this->teacher->id,
            'type' => 'class.joined.teacher',
            'title' => 'New student in 7 Diamond',
            'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
            'link' => 'class-joined:'.$this->group->id.':'.$this->student->id.$i,
        ]);
    }

    $this->actingAs($this->teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Inbox, 100 unread')
        ->assertSee('99+', false);
});

test('teacher sees and follows an assignment notification to its rollup', function () {
    $assignment = completedAssignment($this->group, $this->story, $this->teacher->id);

    $notification = Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'assignment.completed.teacher',
        'title' => 'Assignment finished: Finish This',
        'body' => 'This is a completion notice. '.$this->student->nickname.' finished this assignment.',
        'link' => 'assignment-complete:'.$assignment->id.':'.$this->student->id,
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox'))
        ->assertOk()
        ->assertSee('Assignment finished: Finish This')
        ->assertSee('Mark all as read');

    $this->actingAs($this->teacher)
        ->post(route('teacher.inbox.read', $notification))
        ->assertRedirect(route('teacher.assignments.show', $assignment));

    $this->assertNotNull($notification->fresh()->read_at);
});

test('teacher follows a class notification to the class page', function () {
    $notification = Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'class.joined.teacher',
        'title' => 'New student in 7 Diamond',
        'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
        'link' => 'class-joined:'.$this->group->id.':'.$this->student->id,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.inbox.read', $notification))
        ->assertRedirect(route('teacher.classes.show', $this->group));
});

test('marking all teacher notifications as read works', function () {
    Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'class.joined.teacher',
        'title' => 'New student in 7 Diamond',
        'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
        'link' => 'class-joined:'.$this->group->id.':'.$this->student->id,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.inbox.read-all'))
        ->assertRedirect();

    $this->assertSame(0, $this->teacher->notifications()->unread()->count());
});

test('teacher inbox tabs filter by type and unread state', function () {
    Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'assignment.completed.teacher',
        'title' => 'Assignment finished: Finish This',
        'body' => 'This is a completion notice. '.$this->student->nickname.' finished this assignment.',
        'link' => 'assignment-complete:1:'.$this->student->id,
    ]);
    Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'class.joined.teacher',
        'title' => 'New student in 7 Diamond',
        'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
        'link' => 'class-joined:'.$this->group->id.':'.$this->student->id,
        'read_at' => now(),
    ]);

    // All: both rows, tab strip, and the Finished pill.
    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox'))
        ->assertOk()
        ->assertSee('Assignment finished: Finish This')
        ->assertSee('New student in 7 Diamond')
        ->assertSee('Finished')
        ->assertSee('All')
        ->assertSee('Unread');

    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox', ['filter' => 'finished']))
        ->assertOk()
        ->assertSee('Assignment finished: Finish This')
        ->assertDontSee('New student in 7 Diamond');

    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox', ['filter' => 'class']))
        ->assertOk()
        ->assertSee('New student in 7 Diamond')
        ->assertDontSee('Assignment finished: Finish This');

    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox', ['filter' => 'unread']))
        ->assertOk()
        ->assertSee('Assignment finished: Finish This')
        ->assertDontSee('New student in 7 Diamond');

    // Unknown filter values fall back to the All tab.
    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox', ['filter' => 'banana']))
        ->assertOk()
        ->assertSee('Assignment finished: Finish This')
        ->assertSee('New student in 7 Diamond');
});

test('teacher inbox tab counts reflect the full inbox', function () {
    Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'assignment.completed.teacher',
        'title' => 'Assignment finished: Finish This',
        'body' => 'This is a completion notice. '.$this->student->nickname.' finished this assignment.',
        'link' => 'assignment-complete:1:'.$this->student->id,
    ]);
    Notification::create([
        'user_id' => $this->teacher->id,
        'type' => 'class.joined.teacher',
        'title' => 'New student in 7 Diamond',
        'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
        'link' => 'class-joined:'.$this->group->id.':'.$this->student->id,
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox'))
        ->assertOk()
        // All = 2; two notifications exist.
        ->assertSee('>2<', false);
});

function teacherNotice(Group $group, User $student, User $teacher): Notification
{
    return Notification::create([
        'user_id' => $teacher->id,
        'type' => 'assignment.completed.teacher',
        'title' => 'Assignment finished: Finish This',
        'body' => 'This is a completion notice. '.$student->nickname.' finished this assignment.',
        'link' => 'assignment-complete:1:'.$student->id,
    ]);
}

test('the bell preview returns compact rows and the page declares its endpoint', function () {
    $notice = teacherNotice($this->group, $this->student, $this->teacher);

    $this->actingAs($this->teacher)
        ->get(route('teacher.inbox'))
        ->assertOk()
        ->assertSee(route('teacher.inbox.preview'), false);

    $response = $this->actingAs($this->teacher)
        ->getJson(route('teacher.inbox.preview'))
        ->assertOk()
        ->assertJsonStructure(['html', 'unread'])
        ->assertJson(['unread' => 1]);

    expect($response->json('html'))->toContain(route('teacher.inbox.read', $notice));
    expect($response->json('html'))->toContain('data-bell-mark-all');
});

test('the preview endpoint requires auth and handles an empty inbox', function () {
    $this->get(route('teacher.inbox.preview'))->assertRedirect(route('login'));

    $response = $this->actingAs($this->teacher)
        ->getJson(route('teacher.inbox.preview'))
        ->assertOk()
        ->assertJson(['unread' => 0]);

    expect($response->json('html'))->toContain('No notifications');
});

test('mark all as read answers JSON when called from the bell', function () {
    teacherNotice($this->group, $this->student, $this->teacher);

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.inbox.read-all'))
        ->assertOk()
        ->assertJson(['unread' => 0]);
});
