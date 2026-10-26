<?php

use App\Enums\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportIssueStudent(): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'reading_level' => 8,
        'is_setup_complete' => true,
    ]);
}

function reportIssueStory(): Story
{
    return Story::create([
        'title' => 'Report Me',
        'author' => 'A. Author',
        'synopsis' => 'Testing the report flow.',
        'reading_level' => 7,
        'word_count' => 400,
        'status' => 'published',
        'content' => "First paragraph.\n\nSecond paragraph.",
    ]);
}

test('a student can report a story issue from the reader modal', function () {
    $student = reportIssueStudent();
    $story = reportIssueStory();

    $this->actingAs($student)
        ->postJson(route('reader.report.store', $story), [
            'category' => 'typo',
            'description' => 'The word "hte" should be "the" on page 2.',
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('story_reports', [
        'user_id' => $student->id,
        'story_id' => $story->id,
        'category' => 'typo',
        'status' => 'pending',
    ]);
});

test('a report with an invalid category is rejected', function () {
    $student = reportIssueStudent();
    $story = reportIssueStory();

    $this->actingAs($student)
        ->postJson(route('reader.report.store', $story), [
            'category' => 'banana',
            'description' => 'Nope.',
        ])
        ->assertStatus(422);

    $this->assertDatabaseCount('story_reports', 0);
});

test('the old standalone report page is gone', function () {
    $student = reportIssueStudent();
    $story = reportIssueStory();

    // The URI now only answers POST (the modal's fetch submit); the old
    // standalone GET form page no longer exists.
    $this->actingAs($student)
        ->get('/read/'.$story->slug.'/report')
        ->assertStatus(405);
});
