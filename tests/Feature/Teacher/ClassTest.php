<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Story;
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
        'title' => 'Class Story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Some content for the story. Enough words to be valid.',
        'status' => StoryStatus::Published,
        'created_by' => $this->teacher->id,
    ]);
});

function classGroup(User $teacher, array $overrides = []): Group
{
    return Group::create(array_merge([
        'teacher_id' => $teacher->id,
        'name' => 'Grade 7 - Diamond',
        'join_code' => Group::makeJoinCode(),
    ], $overrides));
}

test('teacher can create a class with a join code', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.classes.store'), [
            'name' => 'Grade 7 - Diamond',
        ])
        ->assertRedirect();

    $group = Group::query()->where('name', 'Grade 7 - Diamond')->first();
    $this->assertNotNull($group);
    $this->assertSame($this->teacher->id, $group->teacher_id);
    $this->assertStringStartsWith('CLS-', $group->join_code);
});

test('teacher can invite students to a class and a notification is dispatched', function () {
    $group = classGroup($this->teacher);

    $this->actingAs($this->teacher)
        ->post(route('teacher.classes.members.invite', $group), [
            'student_ids' => [$this->student->id],
        ])
        ->assertRedirect(route('teacher.classes.show', $group));

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'class.invite.teacher',
        'link' => 'class-invite:'.$group->id,
    ]);
});

test('the invite page shows students with their real avatar or a nickname initial', function () {
    $group = classGroup($this->teacher);
    $withAvatar = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'Wren',
        'first_name' => null,
        'last_name' => null,
        'avatar' => 'wren.png',
    ]);
    $initialOnly = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'Arlo',
        'first_name' => null,
        'last_name' => null,
        'avatar' => null,
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.classes.members.create', $group))
        ->assertOk()
        ->assertSee('wren.png')
        ->assertSee('Wren')
        ->assertSee($withAvatar->email)
        ->assertSee('Arlo')
        ->assertSee($initialOnly->email);

    preg_match_all('/<span class="shrink-0[^"]*">(.*?)<\/span>/s', $response->getContent(), $matches);
    $circles = array_map(
        fn ($circle) => trim(preg_replace('/\s+/', ' ', $circle)),
        $matches[1] ?? []
    );
    $this->assertContains('A', $circles);
    $this->assertNotEmpty(preg_grep('/image\/avatar\/wren\.png/', $circles));
});

test('students already in the class are not re-invited', function () {
    $group = classGroup($this->teacher);
    $group->addMember($this->student);

    $sent = NotificationService::classInvite($group, [$this->student->id]);
    $this->assertSame(0, $sent);
    $this->assertDatabaseCount('notifications', 0);
});

test('student can accept a class invite and becomes a member', function () {
    $group = classGroup($this->teacher);
    NotificationService::classInvite($group, [$this->student->id]);

    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($this->student)
        ->post(route('student.classes.decide', $notification), [
            'decision' => 'accept',
        ])
        ->assertRedirect(route('student.classwork'));

    $this->assertTrue($group->isMember($this->student));
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

test('student can decline a class invite without joining', function () {
    $group = classGroup($this->teacher);
    NotificationService::classInvite($group, [$this->student->id]);

    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($this->student)
        ->post(route('student.classes.decide', $notification), [
            'decision' => 'decline',
        ])
        ->assertRedirect(route('student.classwork'));

    $this->assertFalse($group->isMember($this->student));
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

test('student joins a class by entering its code', function () {
    $group = classGroup($this->teacher);

    $this->actingAs($this->student)
        ->post(route('student.classes.join'), [
            'join_code' => $group->join_code,
        ])
        ->assertRedirect(route('student.classwork'));

    $this->assertTrue($group->isMember($this->student));
});

test('joining a class notifies the teacher once', function () {
    $group = classGroup($this->teacher);

    $this->actingAs($this->student)
        ->post(route('student.classes.join'), [
            'join_code' => $group->join_code,
        ])
        ->assertRedirect(route('student.classwork'));

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->teacher->id,
        'type' => 'class.joined.teacher',
        'link' => 'class-joined:'.$group->id.':'.$this->student->id,
        'body' => 'This is a class update. '.$this->student->nickname.' joined your class.',
    ]);

    // Joining again is a no-op membership-wise and must not re-notify.
    $this->actingAs($this->student)
        ->post(route('student.classes.join'), [
            'join_code' => $group->join_code,
        ]);

    $this->assertSame(1, Notification::where('user_id', $this->teacher->id)->where('type', 'class.joined.teacher')->count());
});

test('accepting a class invite notifies the teacher', function () {
    $group = classGroup($this->teacher);
    NotificationService::classInvite($group, [$this->student->id]);

    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($this->student)
        ->post(route('student.classes.decide', $notification), [
            'decision' => 'accept',
        ])
        ->assertRedirect(route('student.classwork'));

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->teacher->id,
        'type' => 'class.invite.accepted.teacher',
        'link' => 'class-invite-accepted:'.$group->id.':'.$this->student->id,
    ]);
});

test('declining a class invite never notifies the teacher', function () {
    $group = classGroup($this->teacher);
    NotificationService::classInvite($group, [$this->student->id]);

    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($this->student)
        ->post(route('student.classes.decide', $notification), [
            'decision' => 'decline',
        ]);

    $this->assertDatabaseMissing('notifications', [
        'user_id' => $this->teacher->id,
        'type' => 'class.invite.accepted.teacher',
    ]);
});

test('join by code is case-insensitive', function () {
    $group = classGroup($this->teacher);

    $this->actingAs($this->student)
        ->post(route('student.classes.join'), [
            'join_code' => strtolower($group->join_code),
        ])
        ->assertRedirect(route('student.classwork'));

    $this->assertTrue($group->isMember($this->student));
});

test('student joins a class asynchronously and gets refreshed classwork html', function () {
    $group = classGroup($this->teacher);

    $response = $this->actingAs($this->student)
        ->postJson(route('student.classes.join'), [
            'join_code' => $group->join_code,
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'activeCount' => 0,
        ])
        ->assertJsonPath('toast.message', 'You joined "Grade 7 - Diamond".')
        ->assertJsonPath('html', fn ($html) => str_contains($html, 'Grade 7 - Diamond'));

    $this->assertTrue($group->isMember($this->student));
});

test('student leaves a class asynchronously and gets refreshed classwork html', function () {
    $group = classGroup($this->teacher);
    $group->addMember($this->student);

    $response = $this->actingAs($this->student)
        ->deleteJson(route('student.classes.leave', $group));

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ])
        ->assertJsonPath('toast.message', 'You left "Grade 7 - Diamond".');

    $this->assertFalse($group->isMember($this->student));
});

test('a student cannot leave a class they are not in', function () {
    $group = classGroup($this->teacher);

    $this->actingAs($this->student)
        ->delete(route('student.classes.leave', $group))
        ->assertForbidden();
});

test('joining with an unknown code reports an error', function () {
    $this->actingAs($this->student)
        ->post(route('student.classes.join'), [
            'join_code' => 'CLS-XXXX',
        ])
        ->assertSessionHasErrors('join_code');

    $this->assertDatabaseCount('group_members', 0);
});

test('student sees a class-scoped assignment after joining the class', function () {
    $group = classGroup($this->teacher);
    $group->addMember($this->student);

    Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $group->id,
        'title' => 'Class Assignment',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'GRP0001',
    ]);

    $titles = $this->student->activeAssignments()->pluck('title')->all();
    $this->assertContains('Class Assignment', $titles);
});

test('a student cannot accept another students class invite', function () {
    $other = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    $group = classGroup($this->teacher);

    NotificationService::classInvite($group, [$this->student->id]);
    $notification = Notification::where('user_id', $this->student->id)->first();

    $this->actingAs($other)
        ->post(route('student.classes.decide', $notification), [
            'decision' => 'accept',
        ])
        ->assertForbidden();
});

test('only the class owner can invite students', function () {
    $otherTeacher = User::factory()->create(['role' => Role::Teacher]);
    $group = classGroup($this->teacher);

    $this->actingAs($otherTeacher)
        ->post(route('teacher.classes.members.invite', $group), [
            'student_ids' => [$this->student->id],
        ])
        ->assertForbidden();
});

test('deleting a class deletes its assignments', function () {
    $group = classGroup($this->teacher);
    $group->addMember($this->student);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $group->id,
        'title' => 'Class Assignment',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'GRP0001',
    ]);

    $this->actingAs($this->teacher)
        ->delete(route('teacher.classes.destroy', $group))
        ->assertRedirect(route('teacher.classes.index'));

    $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
});

test('deleting a class does not touch whole-class assignments', function () {
    $group = classGroup($this->teacher);

    $assignment = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => null,
        'title' => 'Whole Class Assignment',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now(),
        'share_code' => 'WCA0001',
    ]);

    $this->actingAs($this->teacher)
        ->delete(route('teacher.classes.destroy', $group));

    $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
});

test('class view shows members with their avatar and nickname, not a blank tile', function () {
    $group = classGroup($this->teacher);

    $avatarOnly = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'Myling',
        'first_name' => null,
        'last_name' => null,
        'avatar' => 'sprout.png',
    ]);
    $group->members()->attach([$this->student->id, $avatarOnly->id]);

    $html = $this->actingAs($this->teacher)
        ->get(route('teacher.classes.show', $group))
        ->assertOk()
        ->getContent();

    $this->assertStringContainsString('image/avatar/sprout.png', $html);
    $this->assertStringContainsString('Myling', $html);
    $this->assertStringContainsString($avatarOnly->email, $html);
    $this->assertStringContainsString($this->student->nickname, $html);
    $this->assertStringContainsString($this->student->email, $html);
    $this->assertStringNotContainsString('bg-sky-100 text-sky-700 font-extrabold', $html);
});

test('class view provides a link to the assignments board', function () {
    $group = classGroup($this->teacher);

    $this->actingAs($this->teacher)
        ->get(route('teacher.classes.show', $group))
        ->assertOk()
        ->assertSee(route('teacher.assignments.index'), false);
});

test('class view shows only three assignments', function () {
    $group = classGroup($this->teacher);

    // Distinct created_at so the newest-three pick (Assignment 1, 2, 3) is
    // deterministic - equal timestamps made the DESC tie-break order flaky.
    foreach ([1, 2, 3, 4, 5] as $i) {
        Assignment::create([
            'teacher_id' => $this->teacher->id,
            'story_id' => $this->story->id,
            'group_id' => $group->id,
            'title' => 'Assignment '.$i,
            'scope' => 'group',
            'status' => 'draft',
            'share_code' => 'GRP000'.$i,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.classes.show', $group))
        ->assertOk();

    $html = $response->getContent();
    $this->assertStringContainsString('Assignment 1', $html);
    $this->assertStringNotContainsString('Assignment 4', $html);
});
