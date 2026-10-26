<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function collectionStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
        ...$overrides,
    ]);
}

function collectionStory(array $overrides = []): Story
{
    return Story::create([
        'title' => 'A Collection Tale',
        'author' => 'Jane Doe',
        'synopsis' => 'A keeper tends a lonely light.',
        'content' => 'The waves crashed against the rocks.',
        'word_count' => 400,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Intermediate',
        'status' => StoryStatus::Published,
        ...$overrides,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('collection'))->assertRedirect(route('login'));
});

test('favourites shelf is the default and shows resume-free cards', function () {
    $student = collectionStudent();
    $story = collectionStory(['title' => 'Heart Keeper']);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => 'reading',
        'is_favorite' => true,
    ]);

    $this->actingAs($student)
        ->get(route('collection'))
        ->assertOk()
        ->assertSee('Heart Keeper')
        ->assertSee('READ STORY')
        ->assertSee('FAVORITES');
});

test('in-progress shelf shows percent chip and excludes never-opened favourites', function () {
    $student = collectionStudent();
    $reading = collectionStory(['title' => 'Halfway House', 'word_count' => 1000]);
    $idled = collectionStory(['title' => 'Only Hearted']);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $reading->id,
        'status' => 'reading',
        'current_page' => 50,
    ]);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $idled->id,
        'status' => 'reading',
        'is_favorite' => true,
    ]);

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'progress']))
        ->assertOk()
        ->assertSee('Halfway House')
        ->assertSee('50% DONE')
        ->assertSee('RESUME STORY')
        ->assertDontSee('Only Hearted');
});

test('finished shelf shows completed stories only', function () {
    $student = collectionStudent();
    $done = collectionStory(['title' => 'Tale Finished']);
    $reading = collectionStory(['title' => 'Tale Ongoing']);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $done->id,
        'status' => 'completed',
        'current_page' => 4,
        'completed_at' => now(),
    ]);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $reading->id,
        'status' => 'reading',
        'current_page' => 1,
    ]);

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'finished']))
        ->assertOk()
        ->assertSee('Tale Finished')
        ->assertSee('COMPLETED')
        ->assertSee('READ AGAIN')
        ->assertDontSee('Tale Ongoing');
});

test('every empty shelf renders its own message', function () {
    $student = collectionStudent();

    $this->actingAs($student)
        ->get(route('collection'))
        ->assertOk()
        ->assertSee('No favourites yet');

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'progress']))
        ->assertOk()
        ->assertSee('Nothing in progress');

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'finished']))
        ->assertOk()
        ->assertSee('No finished stories yet');
});

test('an unknown tab falls back to favourites instead of erroring', function () {
    $student = collectionStudent();
    $story = collectionStory(['title' => 'Heart Keeper']);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => 'reading',
        'is_favorite' => true,
    ]);

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'bogus']))
        ->assertOk()
        ->assertSee('Heart Keeper');
});

test('shelves only ever show the current student progress', function () {
    $student = collectionStudent();
    $other = collectionStudent();
    $story = collectionStory(['title' => 'Not My Shelf']);

    StoryProgress::create([
        'user_id' => $other->id,
        'story_id' => $story->id,
        'status' => 'reading',
        'is_favorite' => true,
        'current_page' => 2,
    ]);

    $this->actingAs($student)
        ->get(route('collection'))
        ->assertOk()
        ->assertDontSee('Not My Shelf');

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'progress']))
        ->assertOk()
        ->assertDontSee('Not My Shelf');
});

test('the profile dropdown links to the collection', function () {
    $student = collectionStudent();

    $this->actingAs($student)
        ->get(route('library'))
        ->assertOk()
        ->assertSee(route('collection'));
});
