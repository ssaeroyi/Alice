<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\Genre;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Support\QuestionWriter;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function previewStudent(array $overrides = []): User
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

function previewStory(array $overrides = []): Story
{
    return Story::create([
        'title' => 'The Lighthouse Keeper',
        'author' => 'Howard Pyle',
        'synopsis' => 'A keeper tends a lonely light and finds a friend in the fog.',
        'content' => 'The waves crashed against the rocks.',
        'word_count' => 1200,
        'reading_level' => 8,
        'duration_tier' => 'short',
        'difficulty_label' => 'Intermediate',
        'license' => 'Public domain',
        'source_url' => 'https://www.gutenberg.org/ebooks/99',
        'provenance' => 'From the public-domain original: The Lighthouse Keeper by Howard Pyle.',
        'status' => StoryStatus::Published,
        ...$overrides,
    ]);
}

test('guests are redirected from previews and favorite toggles', function () {
    $story = previewStory();

    $this->get(route('story.preview', $story))->assertRedirect(route('login'));
    $this->post(route('story.favorite', $story))->assertRedirect(route('login'));
});

test('preview surfaces every metadata chip including provenance footer', function () {
    $student = previewStudent();
    $story = previewStory();
    $story->genres()->sync([
        Genre::query()->where('name', 'Adventure')->value('id'),
        Genre::query()->where('name', 'Historical Fiction')->value('id'),
    ]);

    $response = $this->actingAs($student)->get(route('story.preview', $story));

    $response->assertOk()
        ->assertSee('The Lighthouse Keeper')
        ->assertSee('by Howard Pyle')
        ->assertSee('Intermediate')
        ->assertSee('GRADE 8')
        ->assertSee('6 MIN READ')
        ->assertSee('Adventure')
        ->assertSee('Historical Fiction')
        ->assertSee('A keeper tends a lonely light')
        ->assertSee('License: Public domain.')
        ->assertSee('https://www.gutenberg.org/ebooks/99')
        ->assertSee('From the public-domain original: The Lighthouse Keeper by Howard Pyle.');

    expect($response->viewData('band'))->toBe('Intermediate')
        ->and($response->viewData('favorite'))->toBeFalse()
        ->and($response->viewData('hasQuiz'))->toBeFalse();
});

test('the difficulty pill follows the pure-function band, not a stale stored label', function () {
    $student = previewStudent();

    $story = previewStory([
        'title' => 'Mislabelled Tale',
        'reading_level' => 12,
        'difficulty_label' => 'Easy',
    ]);

    $response = $this->actingAs($student)->get(route('story.preview', $story));

    $response->assertOk()
        ->assertSee('GRADE 12')
        ->assertDontSee('>Easy<', false)
        ->assertSee('Very Hard');
});

test('take quiz button hides until questions exist', function () {
    $student = previewStudent();
    $story = previewStory();

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertDontSee('TAKE QUIZ');

    $section = $story->sections()->create(['name' => 'Chapter Checks', 'position' => 1]);
    QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'What kept the light burning?',
        'option_a' => 'Oil',
        'option_b' => 'Wood',
        'option_c' => 'Coal',
        'option_d' => 'Magic',
        'correct_option' => 'a',
        'position' => 1,
        'section_id' => $section->id,
    ]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('TAKE QUIZ');
});

test('preview buttons link into the reader instead of dead anchors', function () {
    $student = previewStudent();
    $story = previewStory();

    // READ STORY always targets the reader; TAKE QUIZ only once questions exist.
    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('href="'.route('reader.show', $story).'"', false)
        ->assertDontSee('href="#"', false)
        ->assertDontSee('TAKE QUIZ');

    $section = $story->sections()->create(['name' => 'Chapter Checks', 'position' => 1]);
    QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'What kept the light burning?',
        'option_a' => 'Oil',
        'option_b' => 'Wood',
        'option_c' => 'Coal',
        'option_d' => 'Magic',
        'correct_option' => 'a',
        'position' => 1,
        'section_id' => $section->id,
    ]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('href="'.route('reader.quiz', $story).'"', false);
});

test('favorite toggle creates one row and flips back and forth', function () {
    $student = previewStudent();
    $story = previewStory();

    $this->actingAs($student)->post(route('story.favorite', $story))->assertRedirect();

    expect(StoryProgress::query()->count())->toBe(1);

    $row = StoryProgress::query()->firstOrFail();
    expect($row->is_favorite)->toBeTrue()
        ->and($row->status)->toBe(StoryProgressStatus::Reading);

    $this->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('SAVED');

    $this->actingAs($student)->post(route('story.favorite', $story))->assertRedirect();

    expect(StoryProgress::query()->count())->toBe(1)
        ->and(StoryProgress::query()->firstOrFail()->is_favorite)->toBeFalse();

    $this->get(route('story.preview', $story))
        ->assertOk()
        ->assertDontSee('SAVED');
});

test('favoriting never disturbs existing reading progress', function () {
    $student = previewStudent();
    $story = previewStory();

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Completed,
        'current_page' => 4,
        'best_score_pct' => 80,
        'completed_at' => now(),
    ]);

    $this->actingAs($student)->post(route('story.favorite', $story))->assertRedirect();

    $row = StoryProgress::query()->firstOrFail();
    expect($row->is_favorite)->toBeTrue()
        ->and($row->status)->toBe(StoryProgressStatus::Completed)
        ->and($row->current_page)->toBe(4)
        ->and($row->best_score_pct)->toBe(80)
        ->and($row->completed_at)->not->toBeNull();
});

test('unpublished stories stay invisible on preview and cannot be favorited', function () {
    $student = previewStudent();

    $draft = previewStory(['title' => 'Hidden Draft', 'status' => StoryStatus::Draft]);
    $archived = previewStory(['title' => 'Hidden Archive', 'status' => StoryStatus::Archived]);

    foreach ([$draft, $archived] as $hidden) {
        $this->actingAs($student)->get(route('story.preview', $hidden))->assertNotFound();
        $this->actingAs($student)->post(route('story.favorite', $hidden))->assertNotFound();
    }

    expect(StoryProgress::query()->count())->toBe(0);
});

test('coverUrl resolves storage paths, passes URLs through and blanks out', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('covers/present.jpg', 'x');

    $seeded = previewStory(['title' => 'Seeded Cover', 'cover_image' => 'covers/present.jpg']);
    $missing = previewStory(['title' => 'Missing Cover', 'cover_image' => 'covers/gone.jpg']);
    $remote = previewStory(['title' => 'Remote Cover', 'cover_image' => 'https://covers.openlibrary.org/b/id/1-L.jpg']);
    $blank = previewStory(['title' => 'No Cover']);

    expect($seeded->coverUrl())->toBe(asset('storage/covers/present.jpg'))
        ->and($missing->coverUrl())->toBe(asset('images/placeholder-cover.svg'))
        ->and($remote->coverUrl())->toBe('https://covers.openlibrary.org/b/id/1-L.jpg')
        ->and($blank->coverUrl())->toBe('');
});

test('quest checklist reports read progress honestly at every stage', function () {
    $student = previewStudent();
    $story = previewStory();

    // Nothing touched: step one is open, and Step 2 does not exist without a quiz.
    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('To finish this story')
        ->assertSee('Read and finish the story')
        ->assertSee('data-tip="Not started"', false)
        ->assertSee('0 of 1 done', false)
        ->assertDontSee('bg-emerald-500')
        ->assertDontSee('Take and pass the quiz');

    // A favourite tap creates a row with no reading behind it: still not started.
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Reading,
        'current_page' => 0,
    ]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('data-tip="Not started"', false)
        ->assertDontSee('Last left off');

    // Halfway through the scroll: the exact figure, never a fabricated one.
    StoryProgress::query()->update(['current_page' => 80]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('data-tip="Last left off at 80%"', false);

    // Finished reading: green check ticks and with no quiz Step 2 stays hidden.
    StoryProgress::query()->update(['status' => StoryProgressStatus::Read->value, 'current_page' => 96]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('data-tip="Finished"', false)
        ->assertSee('1 of 1 done', false)
        ->assertSee('bg-emerald-500', false)
        ->assertSee('Done');
});

test('quest checklist walks the quiz step with the configured pass mark', function () {
    $student = previewStudent();
    $story = previewStory();

    $section = $story->sections()->create(['name' => 'Chapter Checks', 'position' => 1]);
    QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'What kept the light burning?',
        'option_a' => 'Oil',
        'option_b' => 'Wood',
        'option_c' => 'Coal',
        'option_d' => 'Magic',
        'correct_option' => 'a',
        'position' => 1,
        'section_id' => $section->id,
    ]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('Take and pass the quiz')
        ->assertSee('data-tip="Not attempted"', false);

    // A failed attempt shows the real best score against the 70% pass mark.
    TestAttempt::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'score_pct' => 22,
        'passed' => false,
        'correct_count' => 1,
        'wrong_count' => 4,
    ]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('data-tip="Best 22%, needs 70%"', false)
        ->assertDontSee('bg-emerald-500');

    // Passing ticks Step 2 and reports the score it passed with.
    TestAttempt::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'score_pct' => 84,
        'passed' => true,
        'correct_count' => 5,
        'wrong_count' => 1,
    ]);

    $this->actingAs($student)->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('data-tip="Passed with 84%"', false)
        ->assertSee('bg-emerald-500', false);
});
