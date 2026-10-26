<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\AuditLog;
use App\Models\Story;
use App\Models\StoryReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReport(User $user, array $overrides = []): StoryReport
{
    $story = Story::create([
        'title' => 'The Lighthouse Keeper',
        'status' => StoryStatus::Published,
        'reading_level' => 7,
    ]);

    return StoryReport::create(array_merge([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'category' => 'typo',
        'description' => 'The word "thier" should be "their" on the second page.',
        'status' => 'pending',
    ], $overrides));
}

test('admin sees the student report console with pending count and details', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['nickname' => 'Mira']);
    $report = makeReport($student);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('The Lighthouse Keeper')
        ->assertSee('typo')
        ->assertSee('The word "thier" should be "their" on the second page.')
        ->assertSee('Mira')
        ->assertSee('Pending');
});

test('reports filter by status', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create();
    makeReport($student);
    makeReport($student, ['category' => 'other', 'description' => 'A resolved problem.', 'status' => 'resolved']);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('The word "thier" should be "their" on the second page.')
        ->assertDontSee('A resolved problem.');

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['status' => 'resolved']))
        ->assertOk()
        ->assertSee('A resolved problem.');
});

test('reports search matches story title, description and student identity', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $student = User::factory()->create(['nickname' => 'Mira', 'email' => 'mira@example.test']);
    $other = User::factory()->create(['nickname' => 'Liam']);
    $report = makeReport($student);
    makeReport($other, ['description' => 'Something totally unrelated.']);
    $storyTitle = $report->story->title;

    $this->actingAs($admin)->get(route('admin.reports.index', ['q' => 'thier']))
        ->assertOk()
        ->assertSee('The word "thier" should be "their" on the second page.')
        ->assertDontSee('Something totally unrelated.');

    $this->actingAs($admin)->get(route('admin.reports.index', ['q' => $storyTitle]))
        ->assertOk()
        ->assertSee('The Lighthouse Keeper');

    $this->actingAs($admin)->get(route('admin.reports.index', ['q' => 'mira@example.test']))
        ->assertOk()
        ->assertSee('Mira')
        ->assertDontSee('Liam');

    $this->actingAs($admin)->get(route('admin.reports.index', ['q' => 'Liam']))
        ->assertOk()
        ->assertSee('Liam')
        ->assertDontSee('Mira');
});

test('resolving a report marks it resolved and writes an audit entry', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $report = makeReport(User::factory()->create());

    $this->actingAs($admin)
        ->post(route('admin.reports.resolve', $report))
        ->assertRedirect(route('admin.reports.index'));

    expect($report->fresh()->status)->toBe('resolved');

    expect(AuditLog::query()->where('action', 'report.resolved')->where('target_id', $report->id)->count())->toBe(1);
});

test('deleting a report removes it and writes an audit entry', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $report = makeReport(User::factory()->create());

    $this->actingAs($admin)
        ->delete(route('admin.reports.destroy', $report))
        ->assertRedirect(route('admin.reports.index'));

    expect(StoryReport::find($report->id))->toBeNull();

    expect(AuditLog::query()->where('action', 'report.deleted')->where('target_id', $report->id)->count())->toBe(1);
});

test('students cannot reach the admin report console', function () {
    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});
