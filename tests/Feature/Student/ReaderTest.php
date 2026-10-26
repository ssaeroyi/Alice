<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;
use App\Models\KeyTransaction;
use App\Models\QuestionSection;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Support\Dictionary;
use App\Support\QuestionWriter;
use App\Support\StoryPagination;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function readerStudent(): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'reading_level' => 8,
        'is_setup_complete' => true,
    ]);
}

function readerStory(array $overrides = []): Story
{
    $paragraph = fn (string $seed) => collect(range(1, 24))
        ->map(fn ($i) => "The {$seed} walker number {$i} went quickly down the old road.")
        ->implode(' ');

    return Story::create([
        'title' => 'Reader Trail',
        'author' => 'A. Walker',
        'synopsis' => 'Testing pagination.',
        'reading_level' => 7,
        'word_count' => 700,
        'status' => 'published',
        'content' => $paragraph('morning')."\n\n".$paragraph('evening'),
        ...$overrides,
    ]);
}

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

it('404s guests, drafts and archived stories', function () {
    $story = readerStory();

    $this->get(route('reader.show', $story))->assertRedirect(route('login'));

    $this->actingAs(readerStudent());

    foreach (['draft', 'archived'] as $status) {
        $blocked = readerStory(['title' => "Blocked {$status}", 'status' => $status]);
        $this->get(route('reader.show', $blocked))->assertNotFound();
    }

    expect(Story::count())->toBe(3);
});

it('paginates at sentence boundaries with a stable word budget', function () {
    $story = readerStory();
    $pages = StoryPagination::pages($story->content)['pages'];

    expect(count($pages))->toBeGreaterThan(1);

    foreach ($pages as $index => $page) {
        // No page may end mid-sentence.
        expect($page)->toMatch('/[.!?"”]$/');

        if ($index < count($pages) - 1) {
            expect(StoryPagination::countWords($page))->toBeLessThanOrEqual(300 + 60);
        }
    }

    // Deterministic: same content, same pages, byte for byte.
    expect(StoryPagination::pages($story->content)['pages'])->toBe($pages);
});

it('keeps paragraphs whole when they fit a single page', function () {
    $short = "First paragraph lives here.\n\nSecond paragraph also lives here.";
    $result = StoryPagination::pages($short);

    expect($result['pages'])->toHaveCount(1)
        ->and($result['pages'][0])->toBe("First paragraph lives here.\n\nSecond paragraph also lives here.")
        ->and($result['total_words'])->toBeGreaterThan(0);
});

it('flags pages that resume an oversized paragraph so reader spacing stays even', function () {
    // Paragraph gaps come from `p + p`, which page section divs break; the
    // reader re-applies the gap per page unless the page is a continuation.
    $story = readerStory();
    $result = StoryPagination::pages($story->content);

    expect($result['continues'])->toHaveCount(count($result['pages']))
        ->and($result['continues'][0])->toBeFalse()
        ->and(array_filter($result['continues']))->toBe([]);

    $long = collect(range(1, 60))
        ->map(fn ($i) => "Sentence number {$i} fills the line with plenty of words to read.")
        ->implode(' ');
    $longResult = StoryPagination::pages($long);

    expect(count($longResult['pages']))->toBeGreaterThan(1)
        ->and($longResult['continues'][0])->toBeFalse()
        ->and($longResult['continues'][1])->toBeTrue();

    // The rendered page marks the resuming section for the CSS gap rule.
    $paged = readerStory(['title' => 'Paged Resume', 'content' => $long]);
    $this->actingAs(readerStudent())
        ->get(route('reader.show', $paged))
        ->assertOk()
        ->assertSee('data-narr-section="2" class="is-continuation"', false)
        ->assertDontSee('data-narr-section="1" class="is-continuation"', false);
});

it('opens and creates progress once, and heartbeat advances scroll_pct', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student);

    $first = $this->get(route('reader.show', $story));
    $first->assertOk();

    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 50, 'seconds_delta' => 10])->assertOk();

    $progress = StoryProgress::query()->where('user_id', $student->id)->first();
    expect($progress->current_page)->toBe(50);

    // Repeat: progress only moves forward, never regresses.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 30, 'seconds_delta' => 5])->assertOk();
    expect($progress->refresh()->current_page)->toBe(50);

    expect(StoryProgress::query()->where('user_id', $student->id)->count())->toBe(1);
});

it('exposes the server-authoritative resume position for Continue Reading alignment', function () {
    $student = readerStudent();
    $story = readerStory();
    $this->actingAs($student);

    // A first open lazily seeds current_page = 1 (near-top resume).
    $html = $this->get(route('reader.show', $story))->getContent();
    expect($html)->toContain('data-initial-pct="1"');

    // A heartbeat moves the server position; a later open aligns to it.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 60, 'seconds_delta' => 10])->assertOk();

    $html = $this->actingAs($student)->get(route('reader.show', $story))->getContent();
    expect($html)->toContain('data-initial-pct="60"')
        ->and($html)->toContain('data-initial-pct', false);
});

it('accumulates clamped heartbeat time and never double-bills replays', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student);
    $this->get(route('reader.show', $story));

    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 10, 'seconds_delta' => 30])->assertOk();
    // Replay the same beat: fresh delta means nothing doubles.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 10, 'seconds_delta' => 30])->assertOk();
    // A lying client claiming an hour gets clamped to 60s.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 10, 'seconds_delta' => 3600])->assertOk();

    $progress = StoryProgress::query()->where('user_id', $student->id)->where('story_id', $story->id)->first();

    expect($progress->time_spent_seconds)->toBe(120);
});

it('banks reading seconds in real, sub-minute increments from a fresh session', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student);
    $this->get(route('reader.show', $story));

    // A normal first beat reports only the seconds actually spent in the pane
    // (a few seconds), never a full minute. This locks the real-seconds
    // contract: the 60s server ceiling is an anti-cheat cap, not a first-grant.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 5, 'seconds_delta' => 5])->assertOk();

    $activity = UserDailyActivity::query()
        ->where('user_id', $student->id)
        ->whereDate('activity_date', today())
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->reading_seconds_today)->toBe(5)
        ->and((int) StoryProgress::query()
            ->where('user_id', $student->id)
            ->where('story_id', $story->id)
            ->first()->time_spent_seconds)->toBe(5);

    // A second small real beat accrues on top; nothing gets inflated to 60.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 10, 'seconds_delta' => 4])->assertOk();
    expect(UserDailyActivity::query()
        ->where('user_id', $student->id)
        ->whereDate('activity_date', today())
        ->first()->reading_seconds_today)->toBe(9);
});

it('stamps the day after 60 seconds of reading time', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student);
    $this->get(route('reader.show', $story));

    // 45 seconds: below the stamp bar — the beat banks the seconds but never
    // stamps (the row is created immediately so reading time is never dropped).
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 20, 'seconds_delta' => 45]);
    $activity = UserDailyActivity::query()->where('user_id', $student->id)->whereDate('activity_date', today())->first();
    expect($activity)->not->toBeNull()
        ->and($activity->reading_seconds_today)->toBe(45)
        ->and($activity->stamped_at)->toBeNull()
        ->and($activity->read_sessions)->toBe(0);

    // Past 60 total: stamp lands.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 50, 'seconds_delta' => 20])->assertOk();
    expect(UserDailyActivity::query()->where('user_id', $student->id)->whereDate('activity_date', today())->first()->read_sessions)->toBe(1);

    // Same day, another qualifying window: no second stamp ceremony call.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 80, 'seconds_delta' => 60]);
    expect(UserDailyActivity::query()->where('user_id', $student->id)->whereDate('activity_date', today())->first()->read_sessions)->toBe(1);
});

it('credits reading points at 1.5 per full minute up to the daily cap', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student);
    $this->get(route('reader.show', $story));

    // The first positive beat already banks its seconds (no pre-stamp drop),
    // so full minutes accrue exactly as read.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 30, 'seconds_delta' => 59])->assertOk();
    expect($student->refresh()->points)->toBe(0); // 59s is still shy of one full minute

    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 40, 'seconds_delta' => 1])->assertOk();
    expect($student->refresh()->points)->toBe(1); // 60s = first full minute (1.5 -> 1)

    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 50, 'seconds_delta' => 60])->assertOk();
    expect($student->refresh()->points)->toBe(3); // 120s = two full minutes (3.0)
});

it('marks the story read when scroll reaches 95% but not completed', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student);
    $this->get(route('reader.show', $story));

    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 95, 'seconds_delta' => 5]);

    $progress = StoryProgress::query()->where('user_id', $student->id)->first();

    expect($progress->status->value)->toBe('read')
        ->and($progress->current_page)->toBe(95);
});

it('renders tappable word spans server-side without changing the words', function () {
    $story = readerStory(['content' => "The lighthouse keeper's cat ran home."]);

    $this->actingAs(readerStudent());
    $html = $this->get(route('reader.show', $story))->getContent();

    expect(substr_count($html, 'class="tap-word"'))->toBe(6)
        ->and($html)->toContain('data-w="lighthouse"')
        ->and($html)->toContain('data-w="keeper&#039;s"');
});

it('renders the scroll reader layout with all story text and no page navigation', function () {
    $student = readerStudent();
    $story = readerStory();

    $html = $this->actingAs($student)->get(route('reader.show', $story))->getContent();

    expect($html)
        ->toContain('id="reader-stage"')
        ->toContain('id="reader-topbar"')
        ->toContain('data-aa-open')
        ->toContain('data-menu-open')
        ->toContain('id="aa-panel"')
        ->toContain('id="menu-panel"')
        ->toContain('id="scroll-progress"')
        ->toContain('data-narration-segments=')
        ->toContain('data-finish-btn')
        ->not->toContain('data-side-arrow')
        ->not->toContain('data-nav=')
        ->not->toContain('PAGE ')
        ->not->toContain('id="reader-end"')
        ->not->toContain('MARK AS READ');

    // Full story text is rendered — tap-word spans break continuous strings,
    // so check unique words from both morning and evening paragraphs.
    expect($html)->toContain('data-w="morning"')
        ->toContain('data-w="evening"')
        ->toContain('data-w="walker"')
        ->toContain('data-w="24"');
});

it('gates the quiz behind B2 and honest 404s', function () {
    $student = readerStudent();
    $story = readerStory();

    $section = QuestionSection::create(['story_id' => $story->id, 'name' => 'Beginning', 'position' => 1]);

    QuestionWriter::create($story, [
        'type' => 'mcq',
        'section_id' => $section->id,
        'question_text' => 'Where did the walker go?',
        'option_a' => 'Down the old road',
        'option_b' => 'Up the hill',
        'option_c' => 'Into the woods',
        'option_d' => 'Across the river',
        'correct_option' => 'a',
    ]);

    $this->actingAs($student);

    // Never opened the reader: bounce back to details with a toast.
    $this->get(route('reader.quiz', $story))
        ->assertRedirect(route('story.preview', $story))
        ->assertSessionHas('toast.message', fn ($message) => str_contains(strtolower($message), 'open the story first'));

    // Opened it: the real player renders with a minted attempt uuid.
    $this->get(route('reader.show', $story));
    $this->get(route('reader.quiz', $story))
        ->assertOk()
        ->assertSee('aria-label="Leave the quiz"', false)
        ->assertSee('data-attempt="', false);

    // No questions at all: honest 404.
    $empty = readerStory(['title' => 'Quizless']);
    $this->get(route('reader.show', $empty));
    $this->get(route('reader.quiz', $empty))->assertNotFound();
});

it('suggests a different same-band story on the summary page', function () {
    $student = readerStudent();
    $story = readerStory();
    $other = readerStory(['title' => 'Second Trail']);

    $this->actingAs($student)->get(route('reader.show', $story));
    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    expect($html)->toContain('You may like next')
        ->and($html)->toContain(e($other->slug));
});

it('marks the story read from the finish line, pins read to 100%, and lands on the summary', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    $response = $this->post(route('reader.finish', $story));

    $response->assertRedirect(route('reader.summary', $story));

    $progress = StoryProgress::query()->sole();
    expect($progress->status->value)->toBe('read')
        ->and((int) $progress->current_page)->toBe(100); // C1: finish pins to 100

    // Never downgrades a passed quiz.
    $progress->update(['status' => StoryProgressStatus::Completed, 'best_score_pct' => 88]);
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));
    expect(StoryProgress::query()->sole()->status->value)->toBe('completed');
});

it('pays the first story-clear reward once, and only on real reading time', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // Zero recorded time: a finish-URL skip pays nothing.
    $this->post(route('reader.finish', $story));

    expect($student->fresh()->total_keys)->toBe(0)
        ->and($student->fresh()->points)->toBe(0)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(0);

    // Rewind to Reading, bank a real session, then finish -> pays once.
    $progress = StoryProgress::query()->sole();
    $progress->update([
        'status' => StoryProgressStatus::Reading,
        'current_page' => 40,
        'time_spent_seconds' => 120,
    ]);

    $this->post(route('reader.finish', $story));

    expect($student->fresh()->total_keys)->toBe(3)
        ->and($student->fresh()->points)->toBe(5);

    KeyTransaction::where('reason', 'story_clear')->get()->tap(function ($rows) use ($story) {
        expect($rows)->toHaveCount(1)
            ->and($rows[0]->subject_id)->toBe($story->id)
            ->and($rows[0]->amount)->toBe(3);
    });

    // Re-finishing the same story never pays again.
    $this->post(route('reader.finish', $story));
    expect($student->fresh()->total_keys)->toBe(3)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(1);
});

it('pays no story-clear reward when a zero-second heartbeat reaches the end', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // A bare 95% scroll with zero banked time marks the story read but,
    // lacking any real reading time, settles no story-clear reward.
    $this->postJson(route('reader.heartbeat', $story), [
        'scroll_pct' => 99,
        'seconds_delta' => 0,
    ])->assertJsonPath('story_cleared', false);

    expect(StoryProgress::query()->sole()->status->value)->toBe('read')
        ->and($student->fresh()->total_keys)->toBe(0)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(0);
});

it('gates finish and summary behind opening the story first', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)
        ->post(route('reader.finish', $story))
        ->assertRedirect(route('story.preview', $story))
        ->assertSessionHas('toast.message', fn ($message) => str_contains(strtolower($message), 'open the story first'));

    $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->assertRedirect(route('story.preview', $story))
        ->assertSessionHas('toast.message');
});

it('renders a reading-only completion summary with streak state and no quiz hand-off', function () {
    // readerStudent() takes no overrides; build this one directly.
    $student = User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'reading_level' => 8,
        'is_setup_complete' => true,
        'current_streak' => 4,
        'longest_streak' => 9,
    ]);
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    $withQuiz = readerStory(['title' => 'Quizful Summary']);
    $section = QuestionSection::create(['story_id' => $withQuiz->id, 'name' => 'Checks', 'position' => 1]);
    QuestionWriter::create($withQuiz, [
        'type' => 'mcq',
        'section_id' => $section->id,
        'question_text' => 'Where did the walker go?',
        'option_a' => 'Down the old road',
        'option_b' => 'Up the hill',
        'option_c' => 'Into the woods',
        'option_d' => 'Across the river',
        'correct_option' => 'a',
    ]);

    $html = $this->get(route('reader.summary', ['story' => $story->slug]))->getContent();

    // Reading completion is its own moment — best streak, no day-stamp pill, no quiz hand-off.
    expect($html)
        ->not->toContain('Day stamped')
        ->not->toContain('streak 4')
        ->toContain('Best streak')
        ->toContain('>9<')
        ->not->toContain('4 DAYS STREAK')
        ->not->toContain('TAKE QUIZ')
        ->not->toContain('NO QUIZ')
        ->not->toContain('reader.quiz')
        ->not->toContain('GRADE')
        ->not->toContain('LENGTH')
        ->toContain('Find another story')
        ->not->toContain('Keep it going')
        ->not->toContain('That\'s the whole story');

    // A story that HAS questions still shows no quiz CTA on the reading summary.
    $this->get(route('reader.show', ['story' => $withQuiz->slug]));
    $quizHtml = $this->get(route('reader.summary', ['story' => $withQuiz->slug]))->getContent();
    expect($quizHtml)
        ->toContain('Story complete')
        ->not->toContain('TAKE QUIZ')
        ->not->toContain('reader.quiz');

    // The shell stays visible on the summary page...
    expect($quizHtml)->toContain('<header');

    // ...but hides completely while reading and quizzing.
    $readerHtml = $this->get(route('reader.show', ['story' => $story->slug]))->getContent();
    expect($readerHtml)->not->toContain('<header')->not->toContain('<footer');

    $this->get(route('reader.quiz', ['story' => $withQuiz->slug]));
    $quizPageHtml = $this->get(route('reader.quiz', ['story' => $withQuiz->slug]))->getContent();
    expect($quizPageHtml)->toContain('data-attempt="')
        ->and($quizPageHtml)->not->toContain('<header')
        ->and($quizPageHtml)->not->toContain('<footer');
});

/* ------------------------------ dictionary ------------------------------ */

function seedWordNetFixture(): void
{
    DictionaryEntry::create(['lemma' => 'run', 'pos' => 'verb', 'sense_num' => 1, 'definition' => 'move fast by using the feet', 'freq' => 240]);
    DictionaryEntry::create(['lemma' => 'run', 'pos' => 'noun', 'sense_num' => 1, 'definition' => 'a score in baseball', 'freq' => 240]);
    DictionaryEntry::create(['lemma' => 'wolf', 'pos' => 'noun', 'sense_num' => 1, 'definition' => 'a wild carnivorous mammal', 'freq' => 12]);
    DictionaryEntry::create(['lemma' => 'shepherd boy', 'pos' => 'noun', 'sense_num' => 1, 'definition' => 'a boy who tends sheep', 'freq' => 0]);
}

it('resolves exact lemmas, senses capped per pos, badge included', function () {
    seedWordNetFixture();

    $result = Dictionary::lookup('Run,');

    expect($result['found'])->toBeTrue()
        ->and($result['resolved_to'])->toBe('run')
        ->and($result['freq_badge'])->toBe('COMMON')
        ->and(collect($result['senses'])->pluck('pos')->all())->toBe(['noun', 'verb'])
        ->and($result['senses'][1]['list'])->toHaveCount(1);
});

it('walks the inflection ladder: irregulars, possessives, plurals, hyphens', function () {
    seedWordNetFixture();
    $runVerb = DictionaryEntry::query()->where('lemma', 'run')->where('pos', 'verb')->first();

    DictionaryForm::create(['form' => 'ran', 'entry_id' => $runVerb->id, 'irregular' => true]);
    DictionaryForm::create(['form' => 'running', 'entry_id' => $runVerb->id, 'irregular' => false]);
    DictionaryForm::create(['form' => 'wolves', 'entry_id' => DictionaryEntry::query()->where('lemma', 'wolf')->first()->id, 'irregular' => true]);

    $ran = Dictionary::lookup('ran');
    expect($ran['found'])->toBeTrue()
        ->and($ran['resolved_to'])->toBe('run')
        ->and($ran['form_label'])->toContain('form of run');

    $possessive = Dictionary::lookup("wolf's");
    expect($possessive['found'])->toBeTrue()->and($possessive['resolved_to'])->toBe('wolf');

    $plural = Dictionary::lookup('WOLVES');
    expect($plural['resolved_to'])->toBe('wolf');

    $hyphen = Dictionary::lookup('to-night');
    expect($hyphen['found'])->toBeFalse(); // unknown stays honest

    DictionaryEntry::create(['lemma' => 'tonight', 'pos' => 'adv', 'sense_num' => 1, 'definition' => 'during this evening']);
    expect(Dictionary::lookup('to-night')['resolved_to'])->toBe('tonight');
});

it('answers misses honestly and spots capitalized names', function () {
    seedWordNetFixture();

    $name = Dictionary::lookup('Peter');
    expect($name['found'])->toBeFalse()->and($name['looks_like_name'])->toBeTrue();

    $unknown = Dictionary::lookup('blorptastic');
    expect($unknown['found'])->toBeFalse()->and($unknown['looks_like_name'])->toBeFalse();
});

it('serves lookups over http within the throttle', function () {
    seedWordNetFixture();

    $this->actingAs(readerStudent());

    $this->getJson('/words/'.urlencode('run'))
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('resolved_to', 'run')
        ->assertJsonPath('freq_badge', 'COMMON');
});

it('saves resolved words once, dedupes, and refuses unknowns', function () {
    seedWordNetFixture();

    $student = readerStudent();
    $this->actingAs($student);

    $this->postJson('/words', ['word' => 'running'])
        ->assertStatus(422); // no form row yet: ladder misses entirely

    DictionaryForm::create([
        'form' => 'running',
        'entry_id' => DictionaryEntry::query()->where('lemma', 'run')->where('pos', 'verb')->first()->id,
        'irregular' => false,
    ]);

    $this->postJson('/words', ['word' => 'running'])->assertOk()->assertJsonPath('already_saved', false);
    $this->postJson('/words', ['word' => 'run'])->assertOk()->assertJsonPath('already_saved', true);

    expect(SavedWord::query()->where('user_id', $student->id)->count())->toBe(1)
        ->and(SavedWord::query()->where('user_id', $student->id)->first()->lookup_count)->toBe(2)
        ->and(SavedWord::query()->where('user_id', $student->id)->first()->part_of_speech)->toBe('noun, verb');

    $this->postJson('/words', ['word' => 'zzzqqq'])->assertStatus(422);
});

it('unsaves a saved word on delete and allows a fresh re-save', function () {
    seedWordNetFixture();

    $student = readerStudent();
    $this->actingAs($student);

    $this->postJson('/words', ['word' => 'run'])->assertOk()->assertJsonPath('already_saved', false);
    expect(SavedWord::query()->where('user_id', $student->id)->count())->toBe(1);

    $this->deleteJson('/words/run')
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('word', 'run');

    expect(SavedWord::query()->where('user_id', $student->id)->count())->toBe(0);

    // Idempotent: deleting again reports deleted=false rather than 404ing.
    $this->deleteJson('/words/run')->assertOk()->assertJsonPath('deleted', false);

    // Re-save is a brand-new filing again, so the reader would celebrate it.
    $this->postJson('/words', ['word' => 'run'])->assertOk()->assertJsonPath('already_saved', false);
});

it('never deletes another student saved word', function () {
    seedWordNetFixture();

    $owner = readerStudent();
    $other = readerStudent();
    $owner->savedWords()->create(['word' => 'run', 'part_of_speech' => 'verb']);

    $this->actingAs($other)
        ->deleteJson('/words/run')
        ->assertOk()
        ->assertJsonPath('deleted', false);

    expect(SavedWord::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('requires auth to delete a word', function () {
    $this->deleteJson('/words/run')->assertStatus(401);
});

it('returns to the story preview from the reader back button', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)
        ->get(route('reader.show', $story))
        ->assertOk()
        ->assertSee('aria-label="Back to the story preview"', false)
        ->assertSee('href="'.route('story.preview', $story).'"', false)
        ->assertDontSee('Back to the library', false);
});

it('pays the daily reading goal only through the hub claim, exactly once', function () {
    $student = readerStudent();
    $student->update(['daily_reading_goal' => 1]); // 1 minute = 60 seconds
    $story = readerStory();

    $this->actingAs($student);
    $this->get(route('reader.show', $story));

    $keysBefore = $student->refresh()->total_keys;
    $expectedKeys = (int) config('gamification.goal.reading_keys', 5);

    // Nothing read yet: the claim endpoint refuses.
    $this->postJson(route('hub.goal-claim'), ['goal' => 'reading'])
        ->assertUnprocessable();

    // Crossing the bar via heartbeat never auto-credits currency.
    $heartbeat = $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 30, 'seconds_delta' => 61])
        ->assertOk();

    expect($heartbeat->json())->not->toHaveKey('goal_rewarded');

    $activity = UserDailyActivity::query()->where('user_id', $student->id)->whereDate('activity_date', today())->first();

    expect($student->refresh()->total_keys)->toBe($keysBefore)
        ->and(KeyTransaction::where('user_id', $student->id)->where('reason', 'reading_goal')->count())->toBe(0)
        ->and($activity->reading_goal_claimed_at)->toBeNull();

    // The claim pays out exactly once.
    $this->postJson(route('hub.goal-claim'), ['goal' => 'reading'])
        ->assertOk()
        ->assertJsonPath('keys', $expectedKeys)
        ->assertJsonPath('already', false)
        ->assertJsonPath('total_keys', $keysBefore + $expectedKeys);

    expect($student->refresh()->total_keys)->toBe($keysBefore + $expectedKeys)
        ->and(KeyTransaction::where('user_id', $student->id)->where('reason', 'reading_goal')->count())->toBe(1)
        ->and($activity->refresh()->reading_goal_claimed_at)->not->toBeNull();

    // A second tap is acknowledged but never double-pays.
    $this->postJson(route('hub.goal-claim'), ['goal' => 'reading'])
        ->assertOk()
        ->assertJsonPath('keys', 0)
        ->assertJsonPath('already', true);

    expect($student->refresh()->total_keys)->toBe($keysBefore + $expectedKeys);

    // An unknown goal token is rejected outright.
    $this->postJson(route('hub.goal-claim'), ['goal' => 'streak'])->assertUnprocessable();
});

it('renders the reader-rank bar from the final balance, replaying a mid-session level-up', function () {
    $student = readerStudent();
    $student->update(['points' => 32]); // New Reader, 32/50 toward Bookworm
    $story = readerStory();

    // The baseline is snapshotted when the story opens.
    $this->actingAs($student)->get(route('reader.show', $story));

    // Reading + the first-clear reward push the student past 50 during the session.
    $student->update(['points' => 62]);

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    // The ladder always renders at the live, final state — never the stale baseline.
    expect($html)
        ->toContain('Bookworm')
        ->toContain('Page Turner')
        ->toContain('62 / 150 points to Page Turner');

    // The tween config carries the open-time baseline and the current balance.
    expect($html)
        ->toContain('baseline: 32')
        ->toContain('now: 62');
});

it('keeps the reread summary quiet — no first-clear award, no replay of points', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // Bank a real session so the finish line pays the first-clear reward.
    $progress = StoryProgress::query()->sole();
    $progress->update([
        'status' => StoryProgressStatus::Reading,
        'current_page' => 100,
        'time_spent_seconds' => 120,
    ]);
    $this->post(route('reader.finish', $story));

    $first = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();
    expect($first)->toContain('First finish');

    // A second visit shows the plain summary: one-time award already consumed.
    $second = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();
    expect($second)
        ->not->toContain('First finish')
        ->not->toContain('One time per story')
        ->toContain('Story complete')
        ->toContain('data-points-tally');
});

it('shows the suggestion chevrons only when a same-band row exists', function () {
    $student = readerStudent();
    $story = readerStory();
    $other = readerStory(['title' => 'Second Trail']);

    $this->actingAs($student)->get(route('reader.show', $story));

    $withSuggestions = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();
    expect($withSuggestions)
        ->toContain('data-suggest-controls')
        ->toContain('data-suggest-prev')
        ->toContain('data-suggest-next')
        ->toContain('data-suggest-scroll');

    // A story in a band with no peers renders no suggestions section at all.
    // (Grades 7 and 9 are separate bands, so this one has nothing to suggest.)
    $solo = readerStory(['title' => 'The Only Trail', 'reading_level' => 9]);
    $this->actingAs($student)->get(route('reader.show', $solo));

    $without = $this->actingAs($student)
        ->get(route('reader.summary', $solo))
        ->getContent();
    expect($without)
        ->not->toContain('data-suggest-prev')
        ->not->toContain('You may like next');
});

it('pays the story-clear on a later beat even after a zero-second heartbeat marked the story Read', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // Crossing the end with zero banked time marks the story Read but pays
    // nothing...
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 99, 'seconds_delta' => 0])
        ->assertJsonPath('story_cleared', false);
    expect($student->fresh()->total_keys)->toBe(0);

    // ...and beats keep firing while parked at the end: the first one with any
    // real reading time settles the clear, even though the Reading->Read
    // transition already gone.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 99, 'seconds_delta' => 7])
        ->assertJsonPath('story_cleared', true);

    expect($student->fresh()->total_keys)->toBe(3)
        ->and($student->fresh()->points)->toBe(5);

    // A later beat never double-pays.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 99, 'seconds_delta' => 30])
        ->assertJsonPath('story_cleared', false);

    expect($student->fresh()->total_keys)->toBe(3)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(1);
});

it('caps first-clear pays at three per day across stories', function () {
    $student = readerStudent();
    $this->actingAs($student);

    foreach (range(1, 4) as $index) {
        $story = readerStory(['title' => "Trail {$index}"]);
        $this->get(route('reader.show', $story));
        $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 99, 'seconds_delta' => 20]);
    }

    // The first three distinct stories of the day pay; the fourth stays capped.
    // (Keys only ever come from story-clear here, so 9 is airtight; the points
    // total also carries 1 reading point per full minute of the beats.)
    expect($student->fresh()->total_keys)->toBe(9)
        ->and($student->fresh()->points)->toBeGreaterThanOrEqual(15)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(3);

    $extra = readerStory(['title' => 'Fifth Trail']);
    $this->get(route('reader.show', $extra));
    $this->postJson(route('reader.heartbeat', $extra), ['scroll_pct' => 99, 'seconds_delta' => 20])
        ->assertJsonPath('story_cleared', false);

    expect($student->fresh()->total_keys)->toBe(9)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(3);

    // The 4th story of the day finished with a real reading time banks nothing:
    // the summary discloses the daily limit instead of implying any reading
    // would have been enough.
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    expect($html)
        ->not->toContain('First finish')
        ->not->toContain('at least 10 seconds')
        ->toContain('No new rewards')
        ->toContain('up to 3 earned a day');
});

it('surfaces a heartbeat-paid first-clear on the finish summary without re-paying', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // The heartbeat banks the clear (any real reading time) before finish is
    // clicked.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 99, 'seconds_delta' => 15])
        ->assertJsonPath('story_cleared', true);

    // Finish sees an already-Read story: no second payment, but the session
    // note rides a flash so the celebration card still shows.
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    expect($html)
        ->toContain('First finish')
        ->toContain('+3 keys')
        ->toContain('+5 points')
        ->toContain('One time per story')
        ->toContain('Story complete')
        ->not->toContain('Keep it going')
        ->not->toContain('at least 10 seconds')
        ->not->toContain('No new rewards');

    expect($student->fresh()->total_keys)->toBe(3)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(1);
});

it('pins an already-read story to 100% the moment the finish button is hit', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // A fast heartbeat flipped the story to Read while the scroll % was far
    // behind (the reported "reads 35%" case) - finish must pin it to 100
    // no matter how the Read state was reached.
    $progress = StoryProgress::query()->sole();
    $progress->update(['status' => StoryProgressStatus::Read, 'current_page' => 35]);

    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    expect($html)->toContain('>100%<');

    $progress->refresh();
    expect((int) $progress->current_page)->toBe(100)
        ->and($progress->status->value)->toBe('read');
});

it('keeps the session tally in sync with the first-clear when the reader is reopened after the clear', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // Fresh session: the baseline is snapshotted at 0. The heartbeat banks the
    // first-clear (+5 points, +3 keys) and stores the reveal note.
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 99, 'seconds_delta' => 15])
        ->assertJsonPath('story_cleared', true);
    expect($student->fresh()->points)->toBe(5);

    // Reopening the reader mid-session must NOT re-snapshot the baseline on
    // top of the banked +5 (the bug: the tally/bar read 0 while the card
    // still said "+5 points").
    $this->get(route('reader.show', $story))->assertOk();

    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    // The first-clear card...
    expect($html)
        ->toContain('First finish')
        ->toContain('+5 points')
        ->toContain('One time per story');

    // ...and the session point bar/tally agree: they start from the ORIGINAL
    // baseline (0), land on the live balance (5), and caption the real +
    // (5 / 50 toward Bookworm) — never a flat "+0".
    expect($html)
        ->toContain('baseline: 0')
        ->toContain('now: 5')
        ->toContain('5 / 50 points to Bookworm')
        ->toContain('data-points-tally');

    expect($student->fresh()->total_keys)->toBe(3)
        ->and($student->fresh()->points)->toBe(5)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(1);
});

it('disclaims why no reward landed when the finish skipped the reading', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // No heartbeats at all: time_spent stays 0. Finish pins the read to 100
    // but settles nothing and flashes no card.
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    expect($html)
        ->not->toContain('First finish')
        ->not->toContain('One time per story')
        ->toContain('Read to the end')
        ->toContain('Reading a story to the end for the first time earns 3 keys and 5 points')
        ->toContain('Story complete');

    expect($student->fresh()->total_keys)->toBe(0)
        ->and($student->fresh()->points)->toBe(0)
        ->and(KeyTransaction::where('reason', 'story_clear')->count())->toBe(0);
});

it('disclaims the once-per-story rule on a quiet cross-login reread that earns nothing', function () {
    $student = readerStudent();
    $story = readerStory();

    $this->actingAs($student)->get(route('reader.show', $story));

    // Bank the first clear (120s well past the 10s bar), celebrate it on the
    // finish summary...
    $progress = StoryProgress::query()->sole();
    $progress->update([
        'status' => StoryProgressStatus::Reading,
        'current_page' => 100,
        'time_spent_seconds' => 120,
    ]);
    $this->post(route('reader.finish', $story));

    $first = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();
    expect($first)->toContain('First finish');

    // ...then act as a brand-new login: the note, baseline, and auth are gone.
    // (Below the one-minute bar the reread banks 0 points and nothing else, so
    // the summary must explain silently instead of showing +0.)
    $this->flushSession();
    $this->actingAs($student);
    $this->get(route('reader.show', $story));
    $this->postJson(route('reader.heartbeat', $story), ['scroll_pct' => 50, 'seconds_delta' => 20]);
    $this->post(route('reader.finish', $story))->assertRedirect(route('reader.summary', $story));

    $html = $this->actingAs($student)
        ->get(route('reader.summary', $story))
        ->getContent();

    expect($html)
        ->not->toContain('First finish')
        ->not->toContain('at least 10 seconds')
        ->toContain('No new rewards')
        ->toContain('one time per story')
        ->toContain('Reading a story again earns 1 point per minute');
});

it('marks the word popup with save + dictionary actions and the dictionary round-trip URL', function () {
    $story = readerStory();

    $html = $this->actingAs(readerStudent())
        ->get(route('reader.show', $story))
        ->getContent();

    expect($html)
        ->toContain('data-dictionary-url="'.route('study.dictionary').'"')
        ->toContain('data-slot="dictionary"')
        ->toContain('data-slot="save"');
});
