<?php

use App\Enums\Role;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
    ]);
});

function previewNotice(User $user, array $overrides = []): Notification
{
    return Notification::forceCreate(array_merge([
        'user_id' => $user->id,
        'type' => 'achievement.title',
        'title' => 'Preview Notice',
        'body' => 'A body line for the preview.',
        'link' => 'achievement:title:3',
    ], $overrides));
}

test('the bell preview requires an authenticated setup-complete student', function () {
    $this->get(route('inbox.preview'))->assertRedirect(route('login'));
});

test('the preview returns an html fragment and the live unread count', function () {
    previewNotice($this->student, ['title' => 'New title reached!']);

    $response = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->assertOk()
        ->assertJsonStructure(['html', 'unread']);

    expect($response->json('unread'))->toBe(1);
    expect($response->json('html'))
        ->toContain('New title reached!')
        ->toContain('>Title<')
        ->toContain('Mark all as read');
});

test('the preview never leaks another student notifications', function () {
    $other = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    previewNotice($this->student, ['title' => 'Mine only']);
    previewNotice($other, ['title' => 'Someone elses']);

    $html = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->json('html');

    expect($html)->toContain('Mine only')->not->toContain('Someone elses');
});

test('the preview caps at six rows with unread first', function () {
    foreach (range(1, 8) as $minute) {
        previewNotice($this->student, [
            'title' => "Notice {$minute}",
            'created_at' => now()->subMinutes(20 - $minute),
        ]);
    }

    // One of them was already read: it must never jump the unread queue.
    Notification::where('title', 'Notice 8')->update(['read_at' => now()]);

    $html = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->json('html');

    expect($html)
        ->toContain('Notice 7')->toContain('Notice 2')
        ->not->toContain('Notice 1')->not->toContain('Notice 8');
});

test('an empty inbox renders the empty state and no mark-all control', function () {
    $response = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->assertOk();

    expect($response->json('unread'))->toBe(0);
    expect($response->json('html'))
        ->toContain('No notifications')
        ->not->toContain('Mark all as read');
});

test('actionable rows link to the inbox and are never marked read by a preview', function () {
    $invite = previewNotice($this->student, [
        'type' => 'class.invite.teacher',
        'title' => 'Grade 8 - Mercury',
        'link' => 'class-invite:9',
    ]);
    $reward = previewNotice($this->student, [
        'type' => 'reward.welcome_kit',
        'title' => 'Starter reward ready!',
        'link' => 'reward:welcome-kit',
    ]);

    $html = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->json('html');

    expect($html)
        ->not->toContain(route('inbox.read', $invite))
        ->not->toContain(route('inbox.read', $reward))
        ->toContain(route('inbox'));

    expect($invite->fresh()->read_at)->toBeNull();
    expect($reward->fresh()->read_at)->toBeNull();
});

test('regular rows post to inbox.read from the preview', function () {
    $notice = previewNotice($this->student);

    $html = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->json('html');

    expect($html)
        ->toContain(route('inbox.read', $notice))
        ->toContain('method="POST"');
});

test('the bell keeps its no-javascript inbox link and declares the panel', function () {
    previewNotice($this->student);

    $this->actingAs($this->student)
        ->get(route('inbox'))
        ->assertOk()
        ->assertSee(route('inbox.preview'), false)
        ->assertSee('data-bell-toggle', false)
        ->assertSee('data-dropdown-toggle="bell-dropdown"', false)
        ->assertSee('id="bell-dropdown"', false)
        ->assertSee('>Inbox</h2>', false)
        ->assertSee('data-unread-badge', false);
});

test('mark all as read answers json for the bell panel and clears the rows', function () {
    previewNotice($this->student, ['title' => 'First']);
    previewNotice($this->student, ['title' => 'Second']);

    $this->actingAs($this->student)
        ->postJson(route('inbox.read-all'))
        ->assertOk()
        ->assertJson(['unread' => 0]);

    expect(Notification::where('user_id', $this->student->id)->unread()->count())->toBe(0);

    $html = $this->actingAs($this->student)
        ->getJson(route('inbox.preview'))
        ->json('html');

    expect($html)->not->toContain('Mark all as read');
});

test('mark all as read still redirects for the inbox page form', function () {
    previewNotice($this->student);

    $this->actingAs($this->student)
        ->post(route('inbox.read-all'))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'All notifications marked as read.');
});
