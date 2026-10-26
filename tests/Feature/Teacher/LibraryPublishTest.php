<?php

use App\Enums\StoryStatus;
use App\Models\Story;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function curatedLibraryStory(array $overrides = []): array
{
    return array_merge([
        'gutenberg_id' => 21,
        'title' => 'The Boy Who Cried Wolf',
        'author' => 'Aesop',
        'license' => 'Public domain',
        'synopsis' => 'A shepherd boy learns that a voice raised too often is heard too late.',
        'content' => 'A shepherd boy watched the sheep and cried wolf for sport. When the wolf truly came, nobody believed him, and the flock was lost.',
        'status' => StoryStatus::Draft,
    ], $overrides);
}

test('alice:library:publish flips matching curated drafts to published', function () {
    Story::create(curatedLibraryStory());

    $this->artisan('alice:library:publish')
        ->expectsOutput('Curated Alice Library: 1 published, 0 already published, 102 skipped.')
        ->assertSuccessful();

    expect(Story::where('title', 'The Boy Who Cried Wolf')->first()->status)->toBe(StoryStatus::Published);
});

test('alice:library:publish leaves already-published curated books untouched', function () {
    Story::create(curatedLibraryStory(['status' => StoryStatus::Published]));

    $this->artisan('alice:library:publish')
        ->expectsOutput('Curated Alice Library: 0 published, 1 already published, 102 skipped.')
        ->assertSuccessful();
});

test('alice:library:publish respects a deliberately archived curated book', function () {
    Story::create(curatedLibraryStory(['status' => StoryStatus::Archived]));

    $this->artisan('alice:library:publish')
        ->expectsOutput('Curated Alice Library: 0 published, 0 already published, 103 skipped.')
        ->assertSuccessful();

    expect(Story::where('title', 'The Boy Who Cried Wolf')->first()->status)->toBe(StoryStatus::Archived);
});
