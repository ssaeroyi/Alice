<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\AuditLog;
use App\Models\Story;
use App\Models\StoryReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->student = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    $this->story = Story::create([
        'title' => 'Bulk Report Story',
        'slug' => 'bulk-report-story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Some content for the story. Enough words to be valid.',
        'status' => StoryStatus::Published,
        'created_by' => $this->admin->id,
    ]);
});

function bulkReport(User $student, Story $story): StoryReport
{
    return StoryReport::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'category' => 'content',
        'description' => 'Something is off here.',
        'status' => 'pending',
    ]);
}

test('bulk resolve only touches pending reports and notifies the reporters', function () {
    $a = bulkReport($this->student, $this->story);
    $b = bulkReport($this->student, $this->story);
    $already = bulkReport($this->student, $this->story);
    $already->update(['status' => 'resolved']);

    $this->actingAs($this->admin)
        ->post(route('admin.reports.bulk-resolve', ['ids' => [$a->id, $b->id, $already->id]]))
        ->assertRedirect(route('admin.reports.index'));

    $this->assertSame('resolved', $a->fresh()->status);
    $this->assertSame('resolved', $b->fresh()->status);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->student->id,
        'type' => 'report.resolved',
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'report.resolved']);
    $this->assertSame(2, AuditLog::query()->where('action', 'report.resolved')->count());
});

test('bulk delete removes the reports, notifies reporters and audits each row', function () {
    $a = bulkReport($this->student, $this->story);
    $b = bulkReport($this->student, $this->story);

    $this->actingAs($this->admin)
        ->post(route('admin.reports.bulk-delete', ['ids' => [$a->id, $b->id]]))
        ->assertRedirect(route('admin.reports.index'));

    $this->assertDatabaseMissing('story_reports', ['id' => $a->id]);
    $this->assertDatabaseMissing('story_reports', ['id' => $b->id]);
    $this->assertDatabaseHas('notifications', ['user_id' => $this->student->id, 'type' => 'report.declined']);
    $this->assertSame(2, AuditLog::query()->where('action', 'report.deleted')->count());
});

test('bulk actions are admin-only and require at least one id', function () {
    $report = bulkReport($this->student, $this->story);

    $student = User::factory()->create(['role' => Role::Student]);
    $this->actingAs($student)
        ->post(route('admin.reports.bulk-resolve', ['ids' => [$report->id]]))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.reports.bulk-resolve'))
        ->assertSessionHasErrors('ids');
});
