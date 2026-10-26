<?php

use App\Enums\CorrectOption;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Genre;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Models\User;
use App\Support\ReadabilityAnalyzer;
use App\Support\StoryCompleteness;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function storyTeacher(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function longStoryText(): string
{
    return 'Once upon a time, there was a little girl named Ruby. She lived in a small house near the sea. '
        .'Every day she walked to the shore and watched the waves. The waves came and went, and she counted them one by one. '
        .'When the sun went down, she walked home with a happy heart.';
}

function fullStoryData(array $overrides = []): array
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

function questionData(array $overrides = []): array
{
    return array_merge([
        'type' => 'mcq',
        'question_text' => 'Why did Ruby walk to the shore every day?',
        'option_a' => 'To count the waves',
        'option_b' => 'To visit a friend',
        'option_c' => 'To find a treasure',
        'option_d' => 'To feed the birds',
        'correct_option' => 'a',
    ], $overrides);
}

function storySection(Story $story, string $name = 'Vocabulary'): QuestionSection
{
    return $story->sections()->create(['name' => $name, 'position' => $story->sections()->count() + 1]);
}

test('guests are redirected to login on story pages', function () {
    $this->get(route('teacher.stories.index'))->assertRedirect(route('login'));
    $this->get(route('teacher.stories.create'))->assertRedirect(route('login'));
    $this->post(route('teacher.stories.analyze'), ['text' => 'x'])->assertRedirect(route('login'));
});

test('students are forbidden from the story center', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)->get(route('teacher.stories.index'))->assertForbidden();
    $this->actingAs($student)->get(route('teacher.stories.create'))->assertForbidden();
});

test('teachers and admins can open the story center', function () {
    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index'))
        ->assertOk()
        ->assertSee('Story center');

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('teacher.stories.index'))
        ->assertOk();
});

test('genre seeder provides the ten genre shelf', function () {
    expect(Genre::count())->toBe(10)
        ->and(Genre::where('name', 'Fantasy')->exists())->toBeTrue()
        ->and(Genre::where('name', 'Science/Nature')->exists())->toBeTrue();
});

test('a teacher can create a story draft', function () {
    $teacher = storyTeacher();

    $this->actingAs($teacher)
        ->post(route('teacher.stories.store'), [
            'title' => 'Draft Story',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $story = Story::first();

    expect($story->title)->toBe('Draft Story')
        ->and($story->status)->toBe(StoryStatus::Draft)
        ->and($story->created_by)->toBe($teacher->id);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'story.created',
        'target_type' => 'story',
        'target_id' => $story->id,
    ]);
});

test('story validation requires a title and valid genres', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            'genres' => [999],
        ])
        ->assertSessionHasErrors(['title', 'genres.0']);
});

test('license must be one of the presets', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            ...fullStoryData(),
            'license' => 'Made up license',
        ])
        ->assertSessionHasErrors('license');
});

test('a source URL must be a valid URL when filled', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            ...fullStoryData(),
            'source_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('source_url');
});

test('word count is computed from the content', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), fullStoryData());

    expect(Story::first()->word_count)->toBe(15);
});

test('reading level is derived from the story text on save', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), fullStoryData(['content' => longStoryText()]));

    $story = Story::first();

    expect($story->reading_level)->not->toBeNull()
        ->and($story->reading_level)->toBeBetween(7, 10);
});

test('a posted reading level is ignored — the analyzer decides', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            ...fullStoryData(['content' => longStoryText()]),
            'reading_level' => 15,
        ]);

    expect(Story::first()->reading_level)->toBeLessThan(10);
});

test('short content leaves the reading level empty', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), fullStoryData());

    expect(Story::first()->reading_level)->toBeNull();
});

test('completeness meter counts fields and flags what is missing', function () {
    $story = Story::create(fullStoryData());
    $story->genres()->attach(Genre::first());

    $complete = StoryCompleteness::evaluate($story);

    expect($complete->percent())->toBe(78)
        ->and($complete->publishable())->toBeTrue()
        ->and($complete->done())->toBe(7)
        ->and($complete->missing())->toContain('Cover')
        ->and($complete->missing())->toContain('Reading level (auto)');

    $bare = Story::create(['title' => 'Bare Story']);

    $evaluation = StoryCompleteness::evaluate($bare);

    expect($evaluation->publishable())->toBeFalse()
        ->and($evaluation->done())->toBe(1)
        ->and($evaluation->missing())->toContain('Author')
        ->and($evaluation->missingCore())->toContain('Story text');
});

test('an original story without a source URL can be published', function () {
    $teacher = storyTeacher();
    $story = Story::create([
        'title' => 'Original Tale',
        'author' => 'Class 4B',
        'license' => 'Original work',
        'content' => longStoryText(),
    ]);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.publish', $story))
        ->assertSessionHas('toast');

    expect($story->fresh()->status)->toBe(StoryStatus::Published);
});

test('publishing requires the core fields', function () {
    $teacher = storyTeacher();
    $story = Story::create(['title' => 'Incomplete']);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.publish', $story))
        ->assertSessionHas('toast.type', 'error');

    expect($story->fresh()->status)->toBe(StoryStatus::Draft);

    $story->update(fullStoryData());
    $story->genres()->attach(Genre::first());

    $this->actingAs($teacher)
        ->post(route('teacher.stories.publish', $story))
        ->assertSessionHas('toast');

    expect($story->fresh()->status)->toBe(StoryStatus::Published);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'story.published',
        'target_id' => $story->id,
    ]);
});

test('emptying the content of a published story reverts it to draft', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $story->genres()->attach(Genre::first());
    $story->update(['status' => StoryStatus::Published]);

    $this->actingAs($teacher)
        ->put(route('teacher.stories.update', $story), [
            ...fullStoryData(),
            'content' => '',
        ])
        ->assertSessionHas('toast');

    expect($story->fresh()->status)->toBe(StoryStatus::Draft);
});

test('a cover upload is stored on the public disk', function () {
    Storage::fake('public');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            ...fullStoryData(),
            'cover_mode' => 'upload',
            'cover_image' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ])
        ->assertSessionHasNoErrors();

    $story = Story::first();

    expect($story->cover_image)->toStartWith('covers/');

    Storage::disk('public')->assertExists($story->cover_image);
});

test('replacing a cover deletes the old file', function () {
    Storage::fake('public');

    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $old = UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg')->store('covers', 'public');
    $story->update(['cover_image' => $old]);

    $this->actingAs($teacher)
        ->put(route('teacher.stories.update', $story), [
            ...fullStoryData(),
            'cover_mode' => 'upload',
            'cover_image' => UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
        ])
        ->assertSessionHasNoErrors();

    expect($story->fresh()->cover_image)->toStartWith('covers/')
        ->and($story->fresh()->cover_image)->not->toBe($old);

    Storage::disk('public')->assertExists($story->fresh()->cover_image);
    Storage::disk('public')->assertMissing($old);
});

test('a cover can be pasted as a URL instead of uploaded', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            ...fullStoryData(),
            'cover_mode' => 'url',
            'cover_url' => 'https://example.com/cover.jpg',
        ])
        ->assertSessionHasNoErrors();

    expect(Story::first()->cover_image)->toBe('https://example.com/cover.jpg');
});

test('removing a cover clears it and deletes the stored file', function () {
    Storage::fake('public');

    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $cover = UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg')->store('covers', 'public');
    $story->update(['cover_image' => $cover]);

    $this->actingAs($teacher)
        ->put(route('teacher.stories.update', $story), [
            ...fullStoryData(),
            'remove_cover' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($story->fresh()->cover_image)->toBeNull();

    Storage::disk('public')->assertMissing($cover);
});

test('non-image cover uploads are rejected', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            ...fullStoryData(),
            'cover_mode' => 'upload',
            'cover_image' => UploadedFile::fake()->create('cover.txt', 10),
        ])
        ->assertSessionHasErrors('cover_image');
});

test('the analyze endpoint reports the reading breakdown', function () {
    $this->actingAs(storyTeacher())
        ->postJson(route('teacher.stories.analyze'), ['text' => longStoryText()])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.reading_level', ReadabilityAnalyzer::readingLevelFor(longStoryText()))
        ->assertJsonPath('data.tier', 'beginner');
});

test('the analyze endpoint refuses short text', function () {
    $this->actingAs(storyTeacher())
        ->postJson(route('teacher.stories.analyze'), ['text' => 'Too short.'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->actingAs(storyTeacher())
        ->postJson(route('teacher.stories.analyze'), ['text' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('the story edit page carries the grammar-check markup', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('data-grammar-apply-all', false)
        ->assertSee('data-grammar-filters', false)
        ->assertSee('data-grammar-busy', false)
        ->assertSee('spellcheck="false"', false)
        ->assertSee('data-grammar-binary="'.config('stories.grammar.binary').'"', false)
        ->assertSee('runs entirely in your browser', false);
});

test('archiving moves a story out of circulation', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());

    $this->actingAs($teacher)
        ->post(route('teacher.stories.archive', $story))
        ->assertSessionHas('toast');

    expect($story->fresh()->status)->toBe(StoryStatus::Archived);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'story.archived',
        'target_id' => $story->id,
    ]);
});

test('deleting a story removes it permanently and cleans up its cover', function () {
    Storage::fake('public');

    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $cover = UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg')->store('covers', 'public');
    $story->update(['cover_image' => $cover]);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.delete', $story))
        ->assertSessionHas('toast');

    $this->assertDatabaseMissing('stories', ['id' => $story->id]);

    Storage::disk('public')->assertMissing($cover);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'story.deleted',
        'target_type' => 'story',
        'target_id' => $story->id,
    ]);
});

test('deleting one story keeps a cover shared with another', function () {
    Storage::fake('public');

    $teacher = storyTeacher();
    $first = Story::create(fullStoryData());
    $second = Story::create(fullStoryData(['title' => 'Second Tale']));
    $cover = UploadedFile::fake()->create('shared.jpg', 100, 'image/jpeg')->store('covers', 'public');
    $first->update(['cover_image' => $cover]);
    $second->update(['cover_image' => $cover]);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.delete', $first))
        ->assertSessionHas('toast');

    Storage::disk('public')->assertExists($cover);
});

test('duplicating a story creates an editable draft copy', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData(['content' => longStoryText()]));
    $story->genres()->attach([Genre::first()->id, Genre::where('name', 'Adventure')->first()->id]);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.duplicate', $story))
        ->assertRedirect(route('teacher.stories.edit', Story::where('title', 'The Lighthouse Keeper (copy)')->first()));

    $copy = Story::where('title', 'The Lighthouse Keeper (copy)')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(StoryStatus::Draft)
        ->and($copy->content)->toBe($story->content)
        ->and($copy->author)->toBe($story->author)
        ->and($copy->license)->toBe($story->license)
        ->and($copy->reading_level)->toBe($story->reading_level)
        ->and($copy->created_by)->toBe($teacher->id)
        ->and($copy->genres->pluck('id')->sort()->values()->all())
        ->toBe($story->genres->pluck('id')->sort()->values()->all());

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'story.duplicated',
        'target_id' => $copy->id,
    ]);
});

test('saving with a duplicate title flashes a non-blocking warning', function () {
    Story::create(['title' => 'Same Title']);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            'title' => 'Same Title',
        ])
        ->assertSessionHas('story_title_duplicate');
});

test('saving the same story again does not warn about itself', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());

    $this->actingAs($teacher)
        ->put(route('teacher.stories.update', $story), fullStoryData())
        ->assertSessionMissing('story_title_duplicate');
});

test('save and add another returns to a fresh form', function () {
    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), [
            'title' => 'One More',
            'intent' => 'add_another',
        ])
        ->assertRedirect(route('teacher.stories.create'));

    expect(Story::where('title', 'One More')->exists())->toBeTrue();
});

test('the form shows the license popover, fill and import helpers', function () {
    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.create'))
        ->assertSee('About licenses')
        ->assertSee('Fill from opening')
        ->assertSee('Import .txt');
});

test('the duplicate-title warning banner renders when flashed', function () {
    $this->actingAs(storyTeacher())
        ->withSession(['story_title_duplicate' => true])
        ->get(route('teacher.stories.create'))
        ->assertSee('A story with this title already exists');
});

test('story list filters by status and genre and shows the meter', function () {
    $draft = Story::create(['title' => 'A Draft']);
    $published = Story::create(fullStoryData(['title' => 'Published Tale']));
    $published->genres()->attach(Genre::first());
    $published->update(['status' => StoryStatus::Published]);

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['status' => StoryStatus::Published->value]))
        ->assertSee('Published Tale')
        ->assertDontSee('A Draft');

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['genre' => Genre::first()->id]))
        ->assertSee('Published Tale')
        ->assertDontSee('A Draft');

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['q' => 'Draft']))
        ->assertSee('A Draft')
        ->assertDontSee('Published Tale');
});

test('the shelf shows needs chips: red gaps, amber polish and ready', function () {
    $bare = Story::create(['title' => 'Bare Draft']);

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index'))
        ->assertSee('No text')
        ->assertSee('Missing: Author')
        ->assertSee('Missing: License')
        ->assertSee('No quiz yet')
        ->assertSee('No cover')
        ->assertSee('No synopsis');

    $polished = Story::create([
        ...fullStoryData(),
        'cover_image' => 'https://example.com/cover.jpg',
    ]);
    $polished->genres()->attach(Genre::first());
    $polished->questions()->create([...questionData(), 'position' => 1]);

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['q' => 'Lighthouse']))
        ->assertSee('Ready')
        ->assertDontSee('No quiz yet');
});

test('the shelf uses always-visible checkboxes, a floating action bar and a details drawer', function () {
    $story = Story::create(fullStoryData());

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index'))
        ->getContent();

    expect($html)->toContain('data-auto-filter')
        ->and($html)->not->toContain('Apply filters')
        ->and($html)->toContain('data-batch-checkbox-cell')
        ->and($html)->not->toContain('hidden" data-batch-checkbox-cell')
        ->and($html)->toContain('data-batch-bar')
        ->and($html)->toContain('fixed bottom-4 left-1/2 -translate-x-1/2 z-40')
        ->and($html)->toContain('data-batch-edit')
        ->and($html)->toContain('data-batch-clear')
        ->and($html)->toContain('data-drawer-row-trigger="'.$story->id.'"')
        ->and($html)->toContain('data-story-drawer-open="'.$story->id.'"')
        ->and($html)->toContain('data-story-delete="http')
        ->and($html)->toContain('story-delete-modal')
        ->and($html)->toContain('text-sky-700 group-hover:text-sky-800')
        ->and($html)->toContain('data-story-drawer')
        ->and($html)->toContain('data-story-drawer-template="'.$story->id.'"')
        ->and($html)->not->toContain('sticky top-14 z-40');
});

test('the details drawer carries the full story detail per row', function () {
    $story = Story::create(fullStoryData());

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index'))
        ->getContent();

    expect($html)->toContain('Story details')
        ->and($html)->toContain('What it needs')
        ->and($html)->toContain('Edit story')
        ->and($html)->toContain('data-story-drawer-close')
        ->and($html)->toContain('Jane Doe')
        ->and($html)->toContain('A keeper tends a lonely light.');
});

test('the shelf defaults to needs-first sorting and honors the sort parameter', function () {
    $bare = Story::create(['title' => 'Alpha Needs Work']);
    $polished = Story::create([...fullStoryData(), 'title' => 'Zulu Ready']);
    $polished->genres()->attach(Genre::first());
    $polished->questions()->create([...questionData(), 'position' => 1]);

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index'))
        ->getContent();

    expect(strpos($html, 'Alpha Needs Work'))->toBeLessThan(strpos($html, 'Zulu Ready'));

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['sort' => 'title_asc']))
        ->getContent();

    expect(strpos($html, 'Alpha Needs Work'))->toBeLessThan(strpos($html, 'Zulu Ready'));

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['sort' => 'title_desc']))
        ->getContent();

    expect(strpos($html, 'Zulu Ready'))->toBeLessThan(strpos($html, 'Alpha Needs Work'));

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.index', ['sort' => 'bogus']))
        ->assertOk()
        ->assertSee('Needs attention first');
});

test('an mcq question can be added to a story quiz', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $section = storySection($story);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.questions.store', $story), questionData([
            'section_id' => $section->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    $question = $story->questions()->first();

    expect($question)->not->toBeNull()
        ->and($question->type)->toBe(QuestionType::Mcq)
        ->and($question->correct_option)->toBe(CorrectOption::A)
        ->and($question->option_b)->toBe('To visit a friend')
        ->and($question->position)->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.created',
        'target_type' => 'question',
        'target_id' => $question->id,
    ]);
});

test('true-false questions ignore the option fields and map A to true', function () {
    $story = Story::create(fullStoryData());
    $section = storySection($story);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData([
            'type' => 'true_false',
            'question_text' => 'Ruby lived in a house near the sea.',
            'correct_option' => 'a',
            'section_id' => $section->id,
        ]))
        ->assertRedirect();

    $question = $story->questions()->first();

    expect($question->option_a)->toBeNull()
        ->and($question->option_b)->toBeNull()
        ->and($question->correct_option)->toBe(CorrectOption::A)
        ->and($question->type)->toBe(QuestionType::TrueFalse);
});

test('a cloze question is rejected — only multiple choice, true-false and multi-select remain', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData([
            'type' => 'cloze',
            'question_text' => 'She ___ the waves every day.',
            'correct_option' => 'c',
        ]))
        ->assertStatus(422);

    expect($story->questions()->count())->toBe(0);
});

test('questions are validated per type', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData(['option_c' => '']))
        ->assertSessionHasErrors(['option_c']);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData([
            'type' => 'true_false',
            'question_text' => 'Ruby lived near the sea.',
            'correct_option' => 'c',
        ]))
        ->assertSessionHasErrors(['correct_option']);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData(['question_text' => 'Short']))
        ->assertSessionHasErrors(['question_text']);

    expect($story->questions()->count())->toBe(0);
});

test('questions can be edited and removed with audit entries', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $section = storySection($story);
    $question = $story->questions()->create([
        ...questionData(),
        'position' => 1,
        'section_id' => $section->id,
    ]);

    $this->actingAs($teacher)
        ->put(route('teacher.stories.questions.update', $question), questionData([
            'question_text' => 'Why did Ruby watch the waves?',
            'correct_option' => 'b',
            'section_id' => $section->id,
        ]))
        ->assertRedirect();

    expect($question->fresh()->question_text)->toBe('Why did Ruby watch the waves?')
        ->and($question->fresh()->correct_option)->toBe(CorrectOption::B);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.updated',
        'target_id' => $question->id,
    ]);

    $this->actingAs($teacher)
        ->delete(route('teacher.stories.questions.destroy', $question))
        ->assertRedirect();

    $this->assertDatabaseMissing('questions', ['id' => $question->id]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.deleted',
        'target_id' => $question->id,
    ]);
});

test('questions move up and down by swapping positions', function () {
    $story = Story::create(fullStoryData());
    $first = $story->questions()->create([...questionData(), 'position' => 1]);
    $second = $story->questions()->create([...questionData(['question_text' => 'Where did Ruby live?']), 'position' => 2]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.move', $second), ['direction' => 'up'])
        ->assertRedirect();

    expect($second->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.move', $first), ['direction' => 'down'])
        ->assertRedirect();

    expect($first->fresh()->position)->toBe(2)
        ->and($second->fresh()->position)->toBe(1);
});

test('questions reorder in bulk within a section', function () {
    $story = Story::create(fullStoryData());
    $first = $story->questions()->create([...questionData(), 'position' => 1]);
    $second = $story->questions()->create([...questionData(['question_text' => 'Where did Ruby live?']), 'position' => 2]);
    $third = $story->questions()->create([...questionData(['question_text' => 'Who helped Ruby?']), 'position' => 3]);

    $this->actingAs(storyTeacher())
        ->postJson(route('teacher.stories.questions.reorder', $story), [
            'ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($third->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2)
        ->and($second->fresh()->position)->toBe(3);
});

test('bulk reorder rejects ids that mix sections', function () {
    $story = Story::create(fullStoryData());
    $section = $story->sections()->create(['name' => 'Vocabulary', 'position' => 1]);
    $general = $story->questions()->create([...questionData(), 'position' => 1]);
    $grouped = $story->questions()->create([...questionData(['question_text' => 'Define meadow.']), 'position' => 2, 'section_id' => $section->id]);

    $this->actingAs(storyTeacher())
        ->postJson(route('teacher.stories.questions.reorder', $story), [
            'ids' => [$general->id, $grouped->id],
        ])
        ->assertStatus(422);

    expect($general->fresh()->position)->toBe(1)
        ->and($grouped->fresh()->position)->toBe(2);
});

test('bulk reorder refuses questions from another story', function () {
    $story = Story::create(fullStoryData());
    $other = Story::create(fullStoryData(['title' => 'A Different Book']));
    $mine = $story->questions()->create([...questionData(), 'position' => 1]);
    $theirs = $other->questions()->create([...questionData(['question_text' => 'Foreign question']), 'position' => 1]);

    $this->actingAs(storyTeacher())
        ->postJson(route('teacher.stories.questions.reorder', $story), [
            'ids' => [$mine->id, $theirs->id],
        ])
        ->assertStatus(422);

    expect($mine->fresh()->position)->toBe(1)
        ->and($theirs->fresh()->position)->toBe(1);
});

test('deleting a story removes its questions through the cascade', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1]);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.delete', $story))
        ->assertRedirect();

    $this->assertDatabaseMissing('stories', ['id' => $story->id]);
    $this->assertDatabaseMissing('questions', ['story_id' => $story->id]);
});

test('duplicating a story copies its quiz pool', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1]);
    $story->questions()->create([...questionData([
        'type' => 'true_false',
        'question_text' => 'Ruby lived in a small house.',
        'correct_option' => 'a',
    ]), 'position' => 2]);

    $this->actingAs($teacher)
        ->post(route('teacher.stories.duplicate', $story))
        ->assertRedirect();

    $copy = Story::where('title', 'The Lighthouse Keeper (copy)')->first();

    expect($copy->questions()->count())->toBe(2)
        ->and($copy->questions()->orderBy('position')->first()->question_text)
        ->toBe('Why did Ruby walk to the shore every day?')
        ->and($copy->questions()->orderBy('position')->get()->last()->type)
        ->toBe(QuestionType::TrueFalse);
});

test('the quiz pool is capped at the configured maximum', function () {
    $story = Story::create(fullStoryData());
    $story->questions()->createMany(
        collect(range(1, config('stories.questions_max')))
            ->map(fn ($i) => [...questionData(['question_text' => 'Question number '.$i]), 'position' => $i])
            ->all()
    );

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData())
        ->assertSessionHas('toast');

    expect(session('toast')['type'])->toBe('error')
        ->and($story->questions()->count())->toBe(config('stories.questions_max'));
});

test('the story form asks to save the story before adding questions', function () {
    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.create'))
        ->assertSee('Save the story first');
});

test('the edit page lists quiz questions with their correct answers', function () {
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1, 'section_id' => storySection($story)->id]);

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertSee('Story quiz')
        ->assertSee('Why did Ruby walk to the shore every day?')
        ->assertSee('A · To count the waves')
        ->assertSee('MCQ');
});

test('the quiz meter fills toward the recommended count', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertSee('0 of '.config('stories.quiz_recommended').'+ recommended');

    $story->questions()->create([...questionData(), 'position' => 1]);
    $story->questions()->create([...questionData(['question_text' => 'Where did Ruby live?']), 'position' => 2]);
    $story->questions()->create([...questionData(['question_text' => 'What color was the house?']), 'position' => 3]);
    $story->questions()->create([...questionData(['question_text' => 'When did she walk home?']), 'position' => 4]);
    $story->questions()->create([...questionData(['question_text' => 'Who lived in the house?']), 'position' => 5]);

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertSee('5 of '.config('stories.quiz_recommended').'+ recommended');
});

test('the quiz panel lives outside the story form so its forms are not nested', function () {
    $story = Story::create(fullStoryData());

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->getContent();

    $formStart = strpos($html, '<form');
    $firstFormClose = strpos($html, '</form>', $formStart);
    $quizCard = strpos($html, 'data-quiz-card');

    expect($firstFormClose)->toBeGreaterThan($formStart)
        ->and($quizCard)->toBeGreaterThan($formStart)
        ->and($firstFormClose)->toBeLessThan($quizCard);
});

test('the edit page renders the story tabs with quiz count and publish percent badges', function () {
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1]);

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->getContent();

    expect($html)->toContain('data-story-tabs')
        ->and($html)->toContain('data-story-tab="story"')
        ->and($html)->toContain('data-story-tab="quiz"')
        ->and($html)->toContain('data-story-tab="publish"')
        ->and($html)->toContain('data-story-tab-panel="story"')
        ->and($html)->toContain('data-story-tab-panel="quiz"')
        ->and($html)->toContain('data-story-tab-panel="publish"')
        ->and($html)->toContain('Save changes')
        ->and($html)->toContain('1 of '.config('stories.quiz_recommended').'+ recommended');
});

test('the story form ships completeness attention baselines for the tab dots', function () {
    $story = Story::create(fullStoryData(['title' => '', 'author' => '']));

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('data-tab-dot="story"', false)
        ->assertSee('data-tab-dot="publish"', false)
        ->assertSee('&quot;title&quot;:false', false)
        ->assertSee('&quot;author&quot;:false', false)
        ->assertSee('&quot;publishable&quot;:false', false)
        ->assertSee('&quot;archived&quot;:false', false)
        ->assertSee('&quot;state&quot;:&quot;missing&quot;', false);
});

test('a complete draft ships the ready-to-publish baseline and archived stories are quiet', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('&quot;publishable&quot;:true', false)
        ->assertSee('&quot;published&quot;:false', false)
        ->assertSee('&quot;archived&quot;:false', false)
        ->assertSee('&quot;state&quot;:&quot;missing&quot;', false);

    $story->update(['status' => StoryStatus::Archived]);

    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('&quot;archived&quot;:true', false);
});

test('create mode ships an empty tab-dots payload', function () {
    $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.create'))
        ->assertOk()
        ->assertSee('data-tab-status="[]"', false);
});

test('a question can be duplicated to the end of the quiz pool', function () {
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.duplicate', $story->questions()->first()))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($story->questions()->count())->toBe(2)
        ->and($story->questions()->orderBy('position')->get()->last()->question_text)
        ->toBe('Why did Ruby walk to the shore every day?')
        ->and($story->questions()->orderBy('position')->get()->last()->type)
        ->toBe(QuestionType::Mcq);

    $this->assertDatabaseHas('audit_logs', ['action' => 'question.duplicated']);
});

test('creating a story over the content word cap is rejected', function () {
    $over = str_repeat('word ', config('stories.content.max_words') + 1);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.store'), fullStoryData(['content' => $over]))
        ->assertSessionHasErrors('content');
});

test('an existing over-cap story can be saved untouched', function () {
    $long = str_repeat('word ', config('stories.content.max_words') + 1);
    $story = Story::create(fullStoryData([
        'content' => $long,
        'word_count' => config('stories.content.max_words') + 1,
    ]));

    $this->actingAs(storyTeacher())
        ->put(route('teacher.stories.update', $story), fullStoryData([
            'title' => 'Still Long',
            'content' => $long,
        ]))
        ->assertRedirect(route('teacher.stories.edit', $story));

    expect($story->fresh()->title)->toBe('Still Long');
});

test('editing an over-cap story into new over-cap content is rejected', function () {
    $story = Story::create(fullStoryData([
        'content' => str_repeat('word ', config('stories.content.max_words') + 1),
        'word_count' => config('stories.content.max_words') + 1,
    ]));

    $this->actingAs(storyTeacher())
        ->put(route('teacher.stories.update', $story), fullStoryData([
            'content' => str_repeat('different ', config('stories.content.max_words') + 1),
        ]))
        ->assertSessionHasErrors('content');
});

test('an identification question stores trimmed accepted answers', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'identification',
            'question_text' => 'Who lived in the house near the sea?',
            'acceptable_answers' => ['  Ruby  ', 'ruby the girl', '', 'Sherlock Holmes'],
            'section_id' => storySection($story)->id,
        ])
        ->assertRedirect();

    $question = $story->questions()->first();

    expect($question->type)->toBe(QuestionType::Identification)
        ->and($question->acceptable_answers)->toBe(['Ruby', 'ruby the girl', 'Sherlock Holmes'])
        ->and($question->option_a)->toBeNull()
        ->and($question->correct_option)->toBeNull()
        ->and($question->correct_options)->toBeNull();
});

test('identification requires at least one accepted answer and caps the list at ten', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'identification',
            'question_text' => 'Who lived in the house near the sea?',
            'acceptable_answers' => [],
        ])
        ->assertSessionHasErrors('acceptable_answers');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'identification',
            'question_text' => 'Who lived in the house near the sea?',
            'acceptable_answers' => range(1, 11),
        ])
        ->assertSessionHasErrors('acceptable_answers');

    expect($story->questions()->count())->toBe(0);
});

test('identification accepted answers must be unique case-insensitively', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'identification',
            'question_text' => 'Who lived in the house near the sea?',
            'acceptable_answers' => ['Ruby', 'ruby', 'RUBY'],
        ])
        ->assertSessionHasErrors('acceptable_answers');

    expect($story->questions()->count())->toBe(0);
});

test('the options of a question must be unique case-insensitively', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData([
            'option_a' => 'To count the waves',
            'option_b' => 'to count the waves',
        ]))
        ->assertSessionHasErrors('option_b');

    expect($story->questions()->count())->toBe(0);
});

test('a question text may not repeat within the same story', function () {
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), questionData([
            'question_text' => 'WHY DID RUBY WALK TO THE SHORE EVERY DAY?',
        ]))
        ->assertSessionHasErrors('question_text');

    expect($story->questions()->count())->toBe(1);
});

test('editing a question with its own text is allowed — the duplicate check skips itself', function () {
    $story = Story::create(fullStoryData());
    $section = storySection($story);
    $question = $story->questions()->create([...questionData(), 'position' => 1, 'section_id' => $section->id]);

    $this->actingAs(storyTeacher())
        ->put(route('teacher.stories.questions.update', $question), questionData([
            'question_text' => 'Why did Ruby walk to the shore every day?',
            'correct_option' => 'b',
            'section_id' => $section->id,
        ]))
        ->assertRedirect();

    expect($question->fresh()->correct_option)->toBe(CorrectOption::B);
});

test('an identification question round-trips through the edit screen', function () {
    $story = Story::create(fullStoryData());
    $section = storySection($story);
    $question = $story->questions()->create([
        'type' => 'identification',
        'question_text' => 'Who lived in the house near the sea?',
        'acceptable_answers' => ['Ruby', 'Sherlock Holmes'],
        'position' => 1,
        'section_id' => $section->id,
    ]);

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->getContent();

    expect($html)->toContain('data-question-type="identification"')
        ->and($html)->toContain('&quot;Ruby&quot;')
        ->and($html)->toContain('&quot;Sherlock Holmes&quot;')
        ->and($html)->toContain('&quot;Ruby&quot; · &quot;Sherlock Holmes&quot;')
        ->and($html)->toContain('Checked case-insensitively');

    $this->actingAs(storyTeacher())
        ->put(route('teacher.stories.questions.update', $question), [
            'type' => 'identification',
            'question_text' => 'Who lived in the house near the sea?',
            'acceptable_answers' => ['Ruby', 'the girl'],
            'section_id' => $section->id,
        ])
        ->assertRedirect();

    expect($question->fresh()->acceptable_answers)->toBe(['Ruby', 'the girl']);
});

test('duplicating a question or story carries the accepted answers', function () {
    $story = Story::create(fullStoryData());
    $question = $story->questions()->create([
        'type' => 'identification',
        'question_text' => 'Who lived in the house near the sea?',
        'acceptable_answers' => ['Ruby', 'Sherlock Holmes'],
        'position' => 1,
    ]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.duplicate', $question))
        ->assertRedirect();

    expect($story->questions()->get()->last()->acceptable_answers)->toBe(['Ruby', 'Sherlock Holmes']);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.duplicate', $story))
        ->assertRedirect();

    $copy = Story::whereKeyNot($story->id)->first();

    expect($copy->questions()->first()->acceptable_answers)->toBe(['Ruby', 'Sherlock Holmes']);
});

test('the quiz form offers the identification type and the correct-answer block', function () {
    $story = Story::create(fullStoryData());

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->getContent();

    expect($html)->toContain('value="identification"')
        ->and($html)->toContain('Identification')
        ->and($html)->not->toContain('value="multi_select"')
        ->and($html)->toContain('data-quiz-answers')
        ->and($html)->toContain('data-quiz-mcq-option')
        ->and($html)->toContain('data-quiz-tf-grid')
        ->and($html)->toContain('data-quiz-correct-block');
});

test('matching questions accept parallel-array form posts and store canonical pairs', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'matching',
            'question_text' => 'Match each place to what Ruby found there.',
            'match_pairs' => [
                'left' => ['the shore', 'the house', '  the sea  '],
                'right' => ['waves', 'a small room', 'salt water'],
            ],
            'section_id' => storySection($story)->id,
        ])
        ->assertRedirect();

    $question = $story->questions()->first();

    expect($question->type)->toBe(QuestionType::Matching)
        ->and($question->match_pairs)->toBe([
            ['left' => 'the shore', 'right' => 'waves'],
            ['left' => 'the house', 'right' => 'a small room'],
            ['left' => 'the sea', 'right' => 'salt water'],
        ])
        ->and($question->option_a)->toBeNull()
        ->and($question->correct_option)->toBeNull()
        ->and($question->sequence_items)->toBeNull();
});

test('matching pairs are validated for count and uniqueness', function () {
    $story = Story::create(fullStoryData());

    $payload = fn (array $pairs) => [
        'type' => 'matching',
        'question_text' => 'Match each place to what Ruby found there.',
        'match_pairs' => $pairs,
    ];

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), $payload([
            ['left' => 'the shore', 'right' => 'waves'],
        ]))
        ->assertSessionHasErrors('match_pairs');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), $payload([
            ['left' => 'one', 'right' => 'r1'],
            ['left' => 'two', 'right' => 'r2'],
            ['left' => 'three', 'right' => 'r3'],
            ['left' => 'four', 'right' => 'r4'],
            ['left' => 'five', 'right' => 'r5'],
        ]))
        ->assertSessionHasErrors('match_pairs');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), $payload([
            ['left' => 'the shore', 'right' => 'waves'],
            ['left' => 'The Shore', 'right' => 'sand'],
        ]))
        ->assertSessionHasErrors('match_pairs');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), $payload([
            ['left' => 'the shore', 'right' => 'waves'],
            ['left' => 'the house', 'right' => 'WAVES'],
        ]))
        ->assertSessionHasErrors('match_pairs');

    expect($story->questions()->count())->toBe(0);
});

test('sequence questions keep their order and enforce three to five unique steps', function () {
    $story = Story::create(fullStoryData());

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'sequence',
            'question_text' => 'Put the morning in order.',
            'sequence_items' => ['She wakes up', 'She packs the basket', 'She walks to the shore', 'She counts the waves'],
            'section_id' => storySection($story)->id,
        ])
        ->assertRedirect();

    $question = $story->questions()->first();

    expect($question->type)->toBe(QuestionType::Sequence)
        ->and($question->sequence_items)->toBe([
            'She wakes up',
            'She packs the basket',
            'She walks to the shore',
            'She counts the waves',
        ])
        ->and($question->acceptable_answers)->toBeNull()
        ->and($question->correct_option)->toBeNull();

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'sequence',
            'question_text' => 'Too few steps here.',
            'sequence_items' => ['One step', 'Another step'],
        ])
        ->assertSessionHasErrors('sequence_items');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'sequence',
            'question_text' => 'Too many steps here.',
            'sequence_items' => ['One', 'Two', 'Three', 'Four', 'Five', 'Six'],
        ])
        ->assertSessionHasErrors('sequence_items');

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            'type' => 'sequence',
            'question_text' => 'Repeated steps are rejected.',
            'sequence_items' => ['She wakes up', 'she wakes up', 'Third step'],
        ])
        ->assertSessionHasErrors('sequence_items');

    expect($story->questions()->count())->toBe(1);
});

test('sections can be created, renamed, reordered and deleted over HTTP', function () {
    $teacher = storyTeacher();
    $story = Story::create(fullStoryData());

    $this->actingAs($teacher)
        ->post(route('teacher.stories.sections.store', $story), ['name' => 'Vocabulary'])
        ->assertRedirect()
        ->assertSessionHas('toast');

    $this->actingAs($teacher)
        ->post(route('teacher.stories.sections.store', $story), ['name' => 'Comprehension'])
        ->assertRedirect();

    $sections = $story->sections()->orderBy('position')->get();

    expect($sections->pluck('name')->all())->toBe(['Vocabulary', 'Comprehension'])
        ->and($sections->pluck('position')->all())->toBe([1, 2]);

    [$vocabulary, $comprehension] = $sections;

    $this->actingAs($teacher)
        ->put(route('teacher.stories.sections.update', $vocabulary), ['name' => 'Word Bank'])
        ->assertRedirect();

    expect($vocabulary->fresh()->name)->toBe('Word Bank');

    $this->actingAs($teacher)
        ->post(route('teacher.stories.sections.move', $vocabulary), ['direction' => 'down'])
        ->assertRedirect();

    expect($vocabulary->fresh()->position)->toBe(2)
        ->and($comprehension->fresh()->position)->toBe(1);

    $question = $story->questions()->create([...questionData(), 'position' => 1, 'section_id' => $comprehension->id]);

    $this->actingAs($teacher)
        ->delete(route('teacher.stories.sections.destroy', $comprehension))
        ->assertRedirect();

    expect($comprehension->fresh())->toBeNull();

    $uncategorized = $story->sections()->firstWhere('name', 'Uncategorized');

    expect($uncategorized)->not->toBeNull()
        ->and($question->fresh()->section_id)->toBe($uncategorized->id);

    $this->assertDatabaseHas('audit_logs', ['action' => 'question_section.created']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'question_section.updated']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'question_section.deleted']);
});

test('section names are validated per story and capped at the maximum', function () {
    $story = Story::create(fullStoryData());

    $store = fn (array $payload) => $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.sections.store', $story), $payload);

    $store(['name' => 'V'])->assertSessionHasErrors('name');
    $store(['name' => 'general'])->assertSessionHasErrors('name');
    $store(['name' => 'Vocabulary'])->assertRedirect();
    $store(['name' => 'vocabulary'])->assertSessionHasErrors('name');

    expect($story->sections()->count())->toBe(1);

    $story->sections()->createMany(
        collect(range(2, config('stories.sections_max')))
            ->map(fn ($i) => ['name' => 'Section '.$i, 'position' => $i])
            ->all()
    );

    $store(['name' => 'One Section Too Many'])->assertSessionHas('toast');

    expect(session('toast')['type'])->toBe('error')
        ->and($story->sections()->count())->toBe(config('stories.sections_max'));
});

test('moving a question stays inside its own section', function () {
    $story = Story::create(fullStoryData());
    $section = $story->sections()->create(['name' => 'Vocabulary', 'position' => 1]);

    $general = $story->questions()->create([...questionData(['question_text' => 'General question one?']), 'position' => 1]);
    $inSectionA = $story->questions()->create([...questionData(['question_text' => 'Section question two?']), 'position' => 2, 'section_id' => $section->id]);
    $inSectionB = $story->questions()->create([...questionData(['question_text' => 'Section question three?']), 'position' => 3, 'section_id' => $section->id]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.move', $inSectionB), ['direction' => 'up'])
        ->assertRedirect();

    expect($inSectionB->fresh()->position)->toBe(2)
        ->and($inSectionA->fresh()->position)->toBe(3)
        ->and($general->fresh()->position)->toBe(1);
});

test("a question cannot be filed into another story's section", function () {
    $story = Story::create(fullStoryData());
    $other = Story::create(fullStoryData(['title' => 'Other Story']));
    $foreign = $other->sections()->create(['name' => 'Vocabulary', 'position' => 1]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.store', $story), [
            ...questionData(),
            'section_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('section_id');

    expect($story->questions()->count())->toBe(0);
});

test('duplicating a story copies sections and re-files copied questions', function () {
    $story = Story::create(fullStoryData());
    $section = $story->sections()->create(['name' => 'Vocabulary', 'position' => 1]);
    $story->questions()->create([...questionData(), 'position' => 1, 'section_id' => $section->id]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.duplicate', $story))
        ->assertRedirect();

    $copy = Story::where('title', 'The Lighthouse Keeper (copy)')->first();
    $copySection = $copy->sections()->first();

    expect($copy->sections()->count())->toBe(1)
        ->and($copySection->name)->toBe('Vocabulary')
        ->and($copySection->id)->not->toBe($section->id)
        ->and($copy->questions()->first()->section_id)->toBe($copySection->id);
});

test('duplicating a question carries pairs, steps and its section', function () {
    $story = Story::create(fullStoryData());
    $section = $story->sections()->create(['name' => 'Vocabulary', 'position' => 1]);
    $question = $story->questions()->create([
        'type' => 'matching',
        'question_text' => 'Match each place to what Ruby found there.',
        'match_pairs' => [
            ['left' => 'the shore', 'right' => 'waves'],
            ['left' => 'the house', 'right' => 'a small room'],
        ],
        'position' => 1,
        'section_id' => $section->id,
    ]);

    $this->actingAs(storyTeacher())
        ->post(route('teacher.stories.questions.duplicate', $question))
        ->assertRedirect();

    $copy = $story->questions()->orderBy('position')->get()->last();

    expect($copy->type)->toBe(QuestionType::Matching)
        ->and($copy->match_pairs)->toBe($question->match_pairs)
        ->and($copy->section_id)->toBe($section->id);
});

test('the quiz builder offers the new types, sections and pair-step inputs', function () {
    $story = Story::create(fullStoryData());
    $section = $story->sections()->create(['name' => 'Vocabulary', 'position' => 1]);
    $story->questions()->create([
        'type' => 'sequence',
        'question_text' => 'Put the morning in order.',
        'sequence_items' => ['She wakes up', 'She packs the basket', 'She walks to the shore'],
        'position' => 1,
        'section_id' => $section->id,
    ]);
    $story->questions()->create([
        'type' => 'matching',
        'question_text' => 'Match each place to what Ruby found there.',
        'match_pairs' => [
            ['left' => 'the shore', 'right' => 'waves'],
            ['left' => 'the house', 'right' => 'a small room'],
        ],
        'position' => 2,
        'section_id' => $section->id,
    ]);

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->getContent();

    expect($html)->not->toContain('value="fill_blank"')
        ->and($html)->toContain('value="matching"')
        ->and($html)->toContain('value="sequence"')
        ->and($html)->toContain('data-quiz-section')
        ->and($html)->toContain('name="match_pairs[left][]"')
        ->and($html)->toContain('name="sequence_items[]"')
        ->and($html)->toContain('data-quiz-add-section')
        ->and($html)->toContain('section-delete-modal')
        ->and($html)->toContain('Vocabulary')
        ->and($html)->toContain('the shore → waves')
        ->and($html)->toContain('She wakes up → She packs the basket')
        ->and($html)->toContain('data-question-section')
        ->and(substr_count($html, 'name="_method" value="DELETE"'))->toBeGreaterThanOrEqual(2);
});

test('no form is ever nested inside another form on the story pages', function () {
    $story = Story::create(fullStoryData());
    $story->questions()->create([...questionData(), 'position' => 1, 'section_id' => storySection($story)->id]);

    foreach ([route('teacher.stories.create'), route('teacher.stories.edit', $story)] as $url) {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML($this->actingAs(storyTeacher())->get($url)->getContent());
        libxml_clear_errors();

        $forms = $doc->getElementsByTagName('form');

        expect($forms->length)->toBeGreaterThan(0);

        foreach ($forms as $form) {
            for ($ancestor = $form->parentNode; $ancestor !== null; $ancestor = $ancestor->parentNode) {
                expect($ancestor->nodeName)->not->toBe('form');
            }
        }
    }
});

test('the edit-button attribute contract survives every question type with tricky characters', function () {
    $story = Story::create(fullStoryData());
    $section = storySection($story);

    $questions = collect([
        'mcq' => $story->questions()->create([
            'type' => 'mcq',
            'question_text' => 'Why did Ruby watch the "waves" & the bay?',
            'option_a' => 'To count "breakers" & foam',
            'option_b' => 'To visit <a friend>',
            'option_c' => 'To find a treasure',
            'option_d' => 'To feed the birds',
            'correct_option' => 'a',
            'position' => 1,
            'section_id' => $section->id,
        ]),
        'true_false' => $story->questions()->create([
            'type' => 'true_false',
            'question_text' => 'Ruby lived near "the sea" & loved it?',
            'correct_option' => 'b',
            'position' => 2,
            'section_id' => $section->id,
        ]),
        'identification' => $story->questions()->create([
            'type' => 'identification',
            'question_text' => 'Who lived in the "house" & watched the sea?',
            'acceptable_answers' => ['Ruby"s "fair" hair', '<waves> & more', 'Sherlock Holmes'],
            'position' => 3,
            'section_id' => $section->id,
        ]),
        'matching' => $story->questions()->create([
            'type' => 'matching',
            'question_text' => 'Match each "place" & sight to its pair?',
            'match_pairs' => [
                ['left' => 'the "shore" & bay', 'right' => 'waves > foam'],
                ['left' => 'the house', 'right' => 'a small room'],
            ],
            'position' => 4,
            'section_id' => $section->id,
        ]),
        'sequence' => $story->questions()->create([
            'type' => 'sequence',
            'question_text' => 'Put the "morning" & routine in order?',
            'sequence_items' => ['She wakes "early"', 'She packs & goes', 'She walks to the shore'],
            'position' => 5,
            'section_id' => $section->id,
        ]),
    ]);

    $html = $this->actingAs(storyTeacher())
        ->get(route('teacher.stories.edit', $story))
        ->getContent();

    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $buttons = [];

    foreach ($xpath->query('//button[@data-question-id]') as $button) {
        $buttons[$button->getAttribute('data-question-id')] = $button;
    }

    expect(count($buttons))->toBe(5);

    $jsonOf = fn (DOMElement $button, string $suffix) => json_decode(
        html_entity_decode($button->getAttribute('data-question-'.$suffix), ENT_QUOTES, 'UTF-8'),
        true,
    );

    foreach ($questions as $type => $question) {
        $fresh = $question->fresh();
        $button = $buttons[$fresh->id];

        $attrs = [
            'data-question-type' => $type,
            'data-question-text' => $fresh->question_text,
            'data-question-section' => (string) $fresh->section_id,
            'data-question-correct' => $fresh->correct_option?->value ?? '',
        ];

        foreach ($attrs as $name => $value) {
            expect($button->getAttribute($name))->toBe($value);
        }

        expect($jsonOf($button, 'answers'))->toBe(attributeJson($fresh->acceptable_answers))
            ->and($jsonOf($button, 'pairs'))->toBe(attributeJson($fresh->match_pairs))
            ->and($jsonOf($button, 'sequence'))->toBe(attributeJson($fresh->sequence_items));
    }

    $mcq = $questions['mcq']->fresh();

    foreach (['a', 'b', 'c', 'd'] as $letter) {
        expect($buttons[$mcq->id]->getAttribute('data-question-'.$letter))->toBe((string) $mcq->{"option_$letter"});
    }
});

function attributeJson(mixed $value): mixed
{
    return json_decode(json_encode($value ?? []), true);
}
