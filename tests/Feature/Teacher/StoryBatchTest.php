<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Genre;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function storyBatchTeacher(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

beforeEach(function () {
    $this->seed(GenreSeeder::class);
    $this->teacher = storyBatchTeacher();
    $this->actingAs($this->teacher);
});

function storyBatchData(array $overrides = []): array
{
    return array_merge([
        'title' => 'The Lighthouse Keeper',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'source_url' => 'https://example.com/lighthouse',
        'synopsis' => 'A keeper tends a lonely light.',
        'content' => 'The waves crashed against the rocks. The keeper trimmed the lamp and watched the horizon.',
        'genres' => [Genre::first()->id],
    ], $overrides);
}

test('batch publish publishes only complete stories and skips the rest', function () {
    $complete = Story::create(storyBatchData(['status' => StoryStatus::Draft]));
    $incomplete = Story::create(['title' => 'Bare Draft', 'status' => StoryStatus::Draft]);

    $this->post(route('teacher.stories.batch'), [
        'action' => 'publish',
        'ids' => [$complete->id, $incomplete->id],
    ])
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($complete->fresh()->status)->toBe(StoryStatus::Published)
        ->and($incomplete->fresh()->status)->toBe(StoryStatus::Draft);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'story.published',
        'target_type' => 'story',
        'target_id' => $complete->id,
    ]);
});

test('batch archive archives the selected stories', function () {
    $first = Story::create(storyBatchData());
    $second = Story::create(storyBatchData(['title' => 'Another Tale']));

    $this->post(route('teacher.stories.batch'), [
        'action' => 'archive',
        'ids' => [$first->id, $second->id],
    ])->assertRedirect();

    expect($first->fresh()->status)->toBe(StoryStatus::Archived)
        ->and($second->fresh()->status)->toBe(StoryStatus::Archived);
});

test('batch duplicate creates fresh drafts for each selected story', function () {
    $first = Story::create(storyBatchData());
    $second = Story::create(storyBatchData(['title' => 'Another Tale']));

    $this->post(route('teacher.stories.batch'), [
        'action' => 'duplicate',
        'ids' => [$first->id, $second->id],
    ])->assertRedirect();

    expect(Story::where('title', 'The Lighthouse Keeper (copy)')->exists())->toBeTrue()
        ->and(Story::where('title', 'Another Tale (copy)')->exists())->toBeTrue();
});

test('batch delete permanently removes the selected stories', function () {
    $first = Story::create(storyBatchData());
    $second = Story::create(storyBatchData(['title' => 'Another Tale']));

    $this->post(route('teacher.stories.batch'), [
        'action' => 'delete',
        'ids' => [$first->id, $second->id],
    ])->assertRedirect();

    $this->assertDatabaseMissing('stories', ['id' => $first->id]);
    $this->assertDatabaseMissing('stories', ['id' => $second->id]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'story.deleted']);
});

test('batch rejects unknown actions and more than one hundred ids', function () {
    $story = Story::create(storyBatchData());

    $this->post(route('teacher.stories.batch'), ['action' => 'nuke', 'ids' => [$story->id]])
        ->assertSessionHasErrors(['action']);

    $this->post(route('teacher.stories.batch'), [
        'action' => 'archive',
        'ids' => range(1, 101),
    ])->assertSessionHasErrors(['ids']);
});

test('guests and students cannot use the batch endpoint', function () {
    auth()->logout();

    $this->post(route('teacher.stories.batch'), ['action' => 'archive', 'ids' => [1]])->assertRedirect(route('login'));

    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)->post(route('teacher.stories.batch'), ['action' => 'archive', 'ids' => [1]])->assertForbidden();
});
