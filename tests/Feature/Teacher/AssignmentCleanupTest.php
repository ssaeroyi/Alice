<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Story;
use App\Models\User;
use App\Support\AssignmentCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => Role::Teacher]);
    $this->story = Story::create([
        'title' => 'Sweep Story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Story content with enough words to be valid.',
        'status' => StoryStatus::Published,
        'created_by' => $this->teacher->id,
    ]);
});

function orphanedAssignment(User $teacher, Story $story, array $overrides = []): Assignment
{
    return Assignment::create(array_merge([
        'teacher_id' => $teacher->id,
        'story_id' => $story->id,
        'group_id' => null,
        'title' => 'Orphaned Group Assignment',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'ORPH01',
    ], $overrides));
}

test('group orphans are hard-deleted by the sweep', function () {
    $orphan = orphanedAssignment($this->teacher, $this->story);

    $this->assertSame(1, AssignmentCleanup::sweepGroupOrphans());

    $this->assertDatabaseMissing('assignments', ['id' => $orphan->id]);
});

test('the sweep is idempotent', function () {
    orphanedAssignment($this->teacher, $this->story);

    $this->assertSame(1, AssignmentCleanup::sweepGroupOrphans());
    $this->assertSame(0, AssignmentCleanup::sweepGroupOrphans());
});

test('soft-deleted group orphans are swept too', function () {
    $orphan = orphanedAssignment($this->teacher, $this->story);
    $orphan->delete();

    $this->assertSame(1, AssignmentCleanup::sweepGroupOrphans());

    $this->assertDatabaseMissing('assignments', ['id' => $orphan->id]);
    $this->assertSame(0, Assignment::withTrashed()->where('id', $orphan->id)->count());
});

test('the sweep never touches whole-class assignments', function () {
    $wholeClass = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => null,
        'title' => 'Whole Class Assignment',
        'scope' => 'all',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'WCA0001',
    ]);

    orphanedAssignment($this->teacher, $this->story);

    $this->assertSame(1, AssignmentCleanup::sweepGroupOrphans());

    $this->assertDatabaseHas('assignments', ['id' => $wholeClass->id]);
});

test('the sweep never touches group-scoped assignments that still have their group', function () {
    $group = Group::create([
        'teacher_id' => $this->teacher->id,
        'name' => 'Grade 7 – Diamond',
        'join_code' => Group::makeJoinCode(),
    ]);

    $live = Assignment::create([
        'teacher_id' => $this->teacher->id,
        'story_id' => $this->story->id,
        'group_id' => $group->id,
        'title' => 'Live Group Assignment',
        'scope' => 'group',
        'status' => 'active',
        'assigned_at' => now()->subDay(),
        'share_code' => 'LIVE01',
    ]);

    orphanedAssignment($this->teacher, $this->story);

    $this->assertSame(1, AssignmentCleanup::sweepGroupOrphans());

    $this->assertDatabaseHas('assignments', ['id' => $live->id]);
});
