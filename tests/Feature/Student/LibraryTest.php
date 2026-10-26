<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Genre;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Support\QuestionWriter;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function libraryStudent(array $overrides = []): User
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

function publishStory(array $overrides = []): Story
{
    return Story::create([
        'title' => 'A Shelf Story',
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

function attachGenre(Story $story, string $name): void
{
    $story->genres()->sync([Genre::query()->where('name', $name)->value('id')]);
}

test('guests are redirected from the library', function () {
    $this->get(route('library'))->assertRedirect(route('login'));
    $this->get(route('library.all', 'all'))->assertRedirect(route('login'));
});

test('library index renders the canonical rows, genre shelf and hero', function () {
    $student = libraryStudent();
    $story = publishStory(['title' => 'Hero Tale']);
    attachGenre($story, 'Fantasy');

    // The shelf row only renders once the student has activity on it.
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => 'reading',
        'current_page' => 1,
    ]);

    $this->actingAs($student)
        ->get(route('library'))
        ->assertOk()
        ->assertSee('Featured Picks')
        ->assertSee('In Progress and Saved', false)
        ->assertSee('Recommended for Grade 8')
        ->assertSee('Explore by Genre')
        ->assertSee('Quick Reads')
        ->assertSee('Hero Tale')
        ->assertSee('Fantasy');
});

test('drafts and archived stories never appear on student shelves', function () {
    $student = libraryStudent();

    publishStory(['title' => 'Visible Tale']);

    Story::create([
        'title' => 'Hidden Draft',
        'author' => 'A',
        'synopsis' => 'S',
        'content' => 'C',
        'status' => StoryStatus::Draft,
    ]);

    Story::create([
        'title' => 'Hidden Archive',
        'author' => 'A',
        'synopsis' => 'S',
        'content' => 'C',
        'status' => StoryStatus::Archived,
    ]);

    $this->actingAs($student)
        ->get(route('library'))
        ->assertOk()
        ->assertSee('Visible Tale')
        ->assertDontSee('Hidden Draft')
        ->assertDontSee('Hidden Archive');
});

test('recommended row stays within one grade of the placement level', function () {
    $student = libraryStudent(['reading_level' => 8]);

    // Flag a hero pick so the band-diverse fallback (which would surface a
    // Very Hard story elsewhere on the page) never runs in this test.
    publishStory(['title' => 'Hero Anchor', 'featured' => true]);

    publishStory(['title' => 'Level Seven', 'reading_level' => 7]);
    publishStory(['title' => 'Level Nine', 'reading_level' => 9]);
    publishStory(['title' => 'Level Twelve', 'reading_level' => 12, 'duration_tier' => 'standard']);

    $response = $this->actingAs($student)->get(route('library'));

    $response->assertOk()
        ->assertSee('Level Seven')
        ->assertSee('Level Nine')
        ->assertDontSee('Level Twelve');

    expect($response->viewData('recommended')->contains(fn ($story) => $story->title === 'Level Twelve'))->toBeFalse();
});

test('quick reads row only carries flash and quick tiers ordered shortest first', function () {
    libraryStudent();

    $long = publishStory(['title' => 'Long Haul', 'duration_tier' => 'standard', 'word_count' => 2500]);
    $flash = publishStory(['title' => 'Flash Card', 'duration_tier' => 'flash', 'word_count' => 250]);
    $quick = publishStory(['title' => 'Quick Snack', 'duration_tier' => 'quick', 'word_count' => 800]);
    // Under the 200-word prominence floor — stays in the library but is not
    // pushed onto the front-and-center Quick Reads shelf.
    $micro = publishStory(['title' => 'Micro Fable', 'duration_tier' => 'flash', 'word_count' => 120]);

    $rows = $this->actingAs(libraryStudent())->get(route('library'))->viewData('quickReads');

    expect($rows->pluck('id')->toArray())->toBe([$flash->id, $quick->id])
        ->and($rows->contains($long->id))->toBeFalse()
        ->and($rows->contains($micro->id))->toBeFalse();
});

test('in-progress shelf shows resume state and percent chip', function () {
    $student = libraryStudent();
    $story = publishStory(['title' => 'Halfway House', 'word_count' => 1000]);

    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => 'reading',
        'current_page' => 50,
    ]);

    $this->actingAs($student)
        ->get(route('library'))
        ->assertOk()
        ->assertSee('50% DONE')
        ->assertSee('RESUME STORY');
});

test('fresh students get no dead shelf row at all', function () {
    $student = libraryStudent();

    $this->actingAs($student)
        ->get(route('library'))
        ->assertOk()
        ->assertDontSee('In Progress and Saved', false)
        ->assertDontSee('Nothing on your shelf yet');
});

test('the legacy in-progress landing redirects to the collection', function () {
    $this->actingAs(libraryStudent())
        ->get(route('library.all', 'in-progress'))
        ->assertRedirect(route('collection', ['tab' => 'progress']));
});

test('hero prefers flagged picks and falls back to band-diverse set', function () {
    libraryStudent();

    $flagged = publishStory(['title' => 'Flagged Star', 'featured' => true]);

    $hero = $this->actingAs(libraryStudent())
        ->get(route('library'))
        ->assertOk()
        ->assertSee('Flagged Star')
        ->viewData('hero');

    expect($hero->pluck('id'))->toContain($flagged->id);
});

test('hero fallback fills one slot per difficulty band when nothing is flagged', function () {
    libraryStudent();

    foreach ([7 => 'Easy', 8 => 'Intermediate', 9 => 'Hard', 10 => 'Very Hard'] as $level => $band) {
        publishStory(['title' => "Band {$band}", 'reading_level' => $level, 'difficulty_label' => $band]);
    }

    $hero = $this->actingAs(libraryStudent())
        ->get(route('library'))
        ->viewData('hero');

    expect($hero->count())->toBeGreaterThanOrEqual(4)
        ->and($hero->pluck('difficulty_label')->unique()->count())->toBe(4);
});

test('landings paginate at twelve per page', function () {
    libraryStudent();

    for ($index = 1; $index <= 15; $index++) {
        publishStory(['title' => 'Tale '.sprintf('%02d', $index)]);
    }

    $stories = $this->actingAs(libraryStudent())
        ->get(route('library.all', 'all'))
        ->assertOk()
        // House pager renders numbered page links PLUS chevron icon buttons
        // (aria-label "Previous page"/"Next page" with rel prev/next). On page 1
        // of 2 the current page is an active span and page 2 is a numbered
        // link, while PREVIOUS is a disabled span (so no rel="prev") and NEXT
        // is an active icon link.
        ->assertSee('aria-label="Next page"', false)
        ->assertDontSee('rel="prev"', false)
        ->assertSee('aria-label="Page 2"', false)
        ->assertSee('rel="next"', false)
        ->viewData('stories');

    expect($stories->count())->toBe(12)
        ->and($stories->total())->toBe(15)
        ->and($stories->hasMorePages())->toBeTrue();

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'page' => 2]))
        ->assertOk()
        ->assertSee(sprintf('%02d', 15))
        // On the final page (2 of 2) page 1 is a numbered link, PREVIOUS is an
        // active icon link and NEXT is a disabled span (so no rel="next" link).
        ->assertSee('aria-label="Page 1"', false)
        ->assertSee('rel="prev"', false)
        ->assertDontSee('rel="next"', false);
});

test('landing search narrows by title', function () {
    libraryStudent();

    publishStory(['title' => 'Lighthouse Keeper']);
    publishStory(['title' => 'Clockwork Cipher']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'Lighthouse']))
        ->assertOk()
        ->assertSee('Lighthouse Keeper')
        ->assertDontSee('Clockwork Cipher');
});

test('pager links keep the active search and stay plain GET links (no-reload swap contract)', function () {
    libraryStudent();

    // 25 stories / 12 per page = 3 pages, so a page-2 link exists while a
    // search is active. The no-reload pager (ajax-pager.js) swaps the region
    // marked data-search-results in place, so the server must keep rendering
    // that container and a `.pagination` nav whose links preserve the query.
    foreach (range(1, 25) as $index) {
        publishStory(['title' => 'Deep Sea '.sprintf('%02d', $index)]);
    }

    $response = $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'Deep']))
        ->assertOk();

    $response
        ->assertSee('data-search-results', false)
        ->assertSee('class="pagination', false)
        ->assertSee('aria-label="Page 2"', false)
        ->assertSee('q=Deep&amp;page=2', false);
});

test('landing search matches the author name', function () {
    libraryStudent();

    publishStory(['title' => 'A Tale', 'author' => 'Emily Brontë']);
    publishStory(['title' => 'Another Tale', 'author' => 'Mark Twain']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'Brontë']))
        ->assertOk()
        ->assertSee('A Tale')
        ->assertDontSee('Another Tale');
});

test('landing search matches the synopsis for terms of three or more characters', function () {
    libraryStudent();

    publishStory(['title' => 'Still Water', 'synopsis' => 'The waves crash on the shore.']);
    publishStory(['title' => 'Wave Runner']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'wave']))
        ->assertOk()
        ->assertSee('Wave Runner')
        ->assertSee('Still Water');
});

test('landing search ignores two-character terms so short noise does not flood results', function () {
    libraryStudent();

    publishStory(['title' => 'Empty Mirror', 'author' => 'Ada Lovelace', 'synopsis' => 'There was an island beyond the fog.']);
    publishStory(['title' => 'The Ant Queen']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'an']))
        ->assertOk()
        ->assertSee('The Ant Queen')
        ->assertDontSee('Empty Mirror');
});

test('landing search finds stories by genre name', function () {
    libraryStudent();

    $mystery = publishStory(['title' => 'Whodunit Manor']);
    attachGenre($mystery, 'Mystery');
    publishStory(['title' => 'Dragon Peak']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'mystery']))
        ->assertOk()
        ->assertSee('Whodunit Manor')
        ->assertDontSee('Dragon Peak');
});

test('landing search ANDs multiple words so a phrase still prunes to real matches', function () {
    libraryStudent();

    publishStory(['title' => 'The Space Ocean']);
    publishStory(['title' => 'Ocean Depths']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'q' => 'space ocean']))
        ->assertOk()
        ->assertSee('The Space Ocean')
        ->assertDontSee('Ocean Depths');
});

test('pagination shows a 4-chip window (first, current, last) instead of every page', function () {
    libraryStudent();

    // 145 stories / 12 per page = 13 pages. The window must never render every
    // page number; on page 1 only 1-4 and the last page (13) with an ellipsis.
    foreach (range(1, 145) as $index) {
        publishStory(['title' => 'Tome '.sprintf('%03d', $index)]);
    }

    $response = $this->actingAs(libraryStudent())
        ->get(route('library.all', 'all'))
        ->assertOk();

    $response
        ->assertSee('aria-label="Page 2"', false)
        ->assertSee('aria-label="Page 4"', false)
        ->assertSee('aria-label="Page 13"', false)
        ->assertSee('&#8943;', false)
        ->assertDontSee('aria-label="Page 5"', false)
        ->assertDontSee('aria-label="Page 12"', false);
});

test('finished shelf counts read-to-the-end books even without a passed quiz', function () {
    $student = libraryStudent();

    $read = publishStory(['title' => 'Read to the End']);
    StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $read->id,
        'status' => 'read', // reached the end, never passed the quiz
        'current_page' => 100,
    ]);

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'finished']))
        ->assertOk()
        ->assertSee('Read to the End')
        ->assertDontSee('No finished stories yet');
});

test('read-to-the-end books leave the in-progress shelf', function () {
    $student = libraryStudent();

    $reading = publishStory(['title' => 'Active Read']);
    $read = publishStory(['title' => 'Done Read']);
    StoryProgress::create(['user_id' => $student->id, 'story_id' => $reading->id, 'status' => 'reading', 'current_page' => 40]);
    StoryProgress::create(['user_id' => $student->id, 'story_id' => $read->id, 'status' => 'read', 'current_page' => 100]);

    $this->actingAs($student)
        ->get(route('collection', ['tab' => 'progress']))
        ->assertOk()
        ->assertSee('Active Read')
        ->assertDontSee('Done Read');
});

test('sort filter orders by length (shortest first)', function () {
    libraryStudent();

    $long = publishStory(['title' => 'A Long One', 'word_count' => 2000, 'duration_tier' => 'short']);
    $short = publishStory(['title' => 'A Brief One', 'word_count' => 300, 'duration_tier' => 'flash']);

    $stories = $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'sort' => 'length']))
        ->assertOk()
        ->viewData('stories');

    expect($stories->pluck('title')->toArray())->toBe(['A Brief One', 'A Long One']);
});

test('difficulty filter maps reader bands onto the recalibrated grade', function () {
    libraryStudent();

    publishStory(['title' => 'Grade Eight', 'reading_level' => 8]);
    publishStory(['title' => 'Seven Seas', 'reading_level' => 7]);
    publishStory(['title' => 'Nine Lives', 'reading_level' => 9]);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'difficulty' => 'intermediate']))
        ->assertOk()
        ->assertSee('Grade Eight')
        ->assertDontSee('Seven Seas')
        ->assertDontSee('Nine Lives');
});

test('the legacy grade param still filters for stale links', function () {
    libraryStudent();

    publishStory(['title' => 'Grade Eight', 'reading_level' => 8]);
    publishStory(['title' => 'Seven Seas', 'reading_level' => 7]);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'grade' => 8]))
        ->assertOk()
        ->assertSee('Grade Eight')
        ->assertDontSee('Seven Seas');
});

test('genre landings filter by genre and unknown slugs 404', function () {
    $student = libraryStudent();

    $mystery = publishStory(['title' => 'Whodunit Manor']);
    attachGenre($mystery, 'Mystery');
    $fantasy = publishStory(['title' => 'Dragon Peak']);
    attachGenre($fantasy, 'Fantasy');

    $this->actingAs($student)
        ->get(route('library.genre', 'mystery'))
        ->assertOk()
        ->assertSee('Whodunit Manor')
        ->assertDontSee('Dragon Peak');

    $this->actingAs($student)->get(route('library.genre', 'does-not-exist'))->assertNotFound();
});

test('the drawer genre filter on the all-stories landing applies the genre slug', function () {
    libraryStudent();

    $mystery = publishStory(['title' => 'Whodunit Manor']);
    attachGenre($mystery, 'Mystery');
    $fantasy = publishStory(['title' => 'Dragon Peak']);
    attachGenre($fantasy, 'Fantasy');

    // This mirrors the filter drawer's GET form, which posts genre=<slug> onto
    // library.all — the surface that previously ignored the genre entirely.
    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'genre' => 'mystery']))
        ->assertOk()
        ->assertSee('Whodunit Manor')
        ->assertDontSee('Dragon Peak');
});

test('an unknown genre slug on the all-stories landing is ignored, not fatal', function () {
    libraryStudent();

    $story = publishStory(['title' => 'Generic Tale']);

    $this->actingAs(libraryStudent())
        ->get(route('library.all', ['row' => 'all', 'genre' => 'not-a-real-genre']))
        ->assertOk()
        ->assertSee('Generic Tale');
});

test('unknown landing rows are rejected', function () {
    $this->actingAs(libraryStudent())
        ->get(route('library.all', 'made-up'))
        ->assertNotFound();
});

test('preview shows real metadata and hides the quiz button when no questions exist', function () {
    $student = libraryStudent();

    $story = publishStory([
        'title' => 'The Quiet Harbor',
        'provenance' => 'From the public-domain original.',
    ]);

    $this->actingAs($student)
        ->get(route('story.preview', $story))
        ->assertOk()
        ->assertSee('The Quiet Harbor')
        ->assertSee('Jane Doe')
        ->assertSee('GRADE 8')
        ->assertSee('From the public-domain original.')
        ->assertDontSee('TAKE QUIZ');
});

test('preview shows the quiz button only when questions exist and 404s non-published', function () {
    $student = libraryStudent();

    $withQuiz = publishStory(['title' => 'Quizzical']);
    $section = $withQuiz->sections()->create(['name' => 'Comprehension', 'position' => 1]);
    QuestionWriter::create($withQuiz, [
        'type' => 'mcq',
        'question_text' => 'What happened?',
        'option_a' => 'This',
        'option_b' => 'That',
        'option_c' => 'Other',
        'option_d' => 'None',
        'correct_option' => 'a',
        'section_id' => $section->id,
    ]);

    $this->actingAs($student)
        ->get(route('story.preview', $withQuiz))
        ->assertOk()
        ->assertSee('TAKE QUIZ');

    $draft = Story::create(['title' => 'Unfinished Business', 'status' => StoryStatus::Draft]);

    $this->actingAs($student)
        ->get(route('story.preview', $draft))
        ->assertNotFound();
});

test('duplicate titles get collision-safe stable slugs', function () {
    $first = publishStory(['title' => 'Same Title!']);
    $second = publishStory(['title' => 'Same Title!']);

    expect($first->refresh()->slug)->toBe('same-title')
        ->and($second->refresh()->slug)->toBe('same-title-2');
});

test('genre count cache stores plain arrays, never Eloquent models', function () {
    $student = libraryStudent();
    $story = publishStory(['title' => 'Genre Shelf']);
    attachGenre($story, 'Fantasy');

    // /library warms the stories_count sort; the all-landing warms the name sort.
    $this->actingAs($student)->get(route('library'))->assertOk();
    $this->actingAs($student)->get(route('library.all', 'all'))->assertOk();

    // Both named cache keys must hold plain scalar rows — serializing Eloquent
    // into the cache store is what caused the "incomplete object" crashes.
    foreach (['name', 'stories_count'] as $direction) {
        $cached = Cache::get("genres:with_counts:{$direction}");
        expect($cached)->toBeArray('cached genre rows must be a plain array');

        foreach ($cached as $row) {
            expect($row)->toBeArray('each cached genre row must be a plain array')
                ->toHaveKeys(['id', 'name', 'stories_count'])
                ->not->toBeInstanceOf(Genre::class);
        }

        $names = array_column($cached, 'name');
        expect($names)->toContain('Fantasy');
    }
});
