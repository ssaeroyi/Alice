<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Notification;
use App\Models\Story;
use App\Models\StoryReport;
use App\Models\User;
use App\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
    ]);
    $this->story = Story::create([
        'title' => 'Admin Inbox Story',
        'slug' => 'admin-inbox-story',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'content' => 'Some content for the story. Enough words to be valid.',
        'status' => StoryStatus::Published,
        'created_by' => $this->admin->id,
    ]);
});

function adminReport(User $student, Story $story, string $category = 'typo'): StoryReport
{
    return StoryReport::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'category' => $category,
        'description' => 'A typo in the third paragraph.',
        'status' => 'pending',
    ]);
}

test('the inbox is admin-only and renders the filter tabs', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.inbox'))
        ->assertOk()
        ->assertSee('All')
        ->assertSee('Reports')
        ->assertSee('System');

    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('admin.inbox'))
        ->assertForbidden();
});

test('a new report notifies every admin once and surfaces the pending count', function () {
    $secondAdmin = User::factory()->create(['role' => Role::Admin]);
    $report = adminReport($this->student, $this->story);

    NotificationService::reportCreated($report);
    NotificationService::reportCreated($report);

    $this->assertDatabaseCount('notifications', 2);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->admin->id,
        'type' => 'report.new',
        'link' => 'report-new:'.$report->id,
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $secondAdmin->id,
        'type' => 'report.new',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.inbox.unread'))
        ->assertOk()
        ->assertJson(['unread' => 1, 'pendingReports' => 1]);
});

test('report verdicts notify the reporting student with a slug-based story link', function () {
    $report = adminReport($this->student, $this->story);

    NotificationService::reportResolved($report);
    $resolved = Notification::where('user_id', $this->student->id)->where('type', 'report.resolved')->first();

    $this->assertNotNull($resolved);
    $this->assertSame(route('story.preview', $this->story), $resolved->link);

    NotificationService::reportDeleted($report);
    $declined = Notification::where('user_id', $this->student->id)->where('type', 'report.declined')->first();
    $this->assertNotNull($declined);
    $this->assertSame(route('story.preview', $this->story), $declined->link);
});

test('report verdicts skip scratch reporters', function () {
    $scratch = User::factory()->create(['role' => Role::Student, 'email' => 'preview@scratch.alice.local']);
    $report = adminReport($scratch, $this->story);

    $this->assertSame(0, NotificationService::reportResolved($report));
    $this->assertDatabaseCount('notifications', 0);
});

test('read marks the notice read and redirects to its destination', function () {
    $report = adminReport($this->student, $this->story);
    NotificationService::reportCreated($report);
    $notice = Notification::where('user_id', $this->admin->id)->first();

    $this->actingAs($this->admin)
        ->post(route('admin.inbox.read', $notice))
        ->assertRedirect(route('admin.reports.index'));

    $this->assertNotNull($notice->fresh()->read_at);

    $other = Notification::first();
    $this->actingAs($this->student)
        ->post(route('admin.inbox.read', $other))
        ->assertForbidden();
});

test('mark all as read clears the unread badge', function () {
    adminReport($this->student, $this->story);
    NotificationService::reportCreated(adminReport($this->student, $this->story));
    $notice = Notification::where('user_id', $this->admin->id)->first();

    $this->actingAs($this->admin)
        ->post(route('admin.inbox.read-all'))
        ->assertRedirect(route('admin.inbox'));

    $this->assertNotNull($notice->fresh()->read_at);
});

test('the bell preview returns compact rows and the page declares its endpoint', function () {
    NotificationService::reportCreated(adminReport($this->student, $this->story));

    $this->actingAs($this->admin)
        ->get(route('admin.inbox'))
        ->assertOk()
        ->assertSee(route('admin.inbox.preview'), false);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.inbox.preview'))
        ->assertOk()
        ->assertJsonStructure(['html', 'unread'])
        ->assertJson(['unread' => 1]);

    $notice = Notification::where('user_id', $this->admin->id)->first();

    expect($response->json('html'))->toContain(route('admin.inbox.read', $notice));
    expect($response->json('html'))->toContain('data-bell-mark-all');
});

test('the preview endpoint requires auth and handles an empty inbox', function () {
    $this->get(route('admin.inbox.preview'))->assertRedirect(route('login'));

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.inbox.preview'))
        ->assertOk()
        ->assertJson(['unread' => 0]);

    expect($response->json('html'))->toContain('No notifications');
});

test('mark all as read answers JSON when called from the bell', function () {
    NotificationService::reportCreated(adminReport($this->student, $this->story));

    $this->actingAs($this->admin)
        ->postJson(route('admin.inbox.read-all'))
        ->assertOk()
        ->assertJson(['unread' => 0]);
});
