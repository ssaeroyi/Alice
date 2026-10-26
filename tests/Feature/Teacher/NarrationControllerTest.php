<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\User;
use App\Support\DetachedCommandLauncher;
use App\Support\Narration;
use App\Support\StoryPagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function narrTeacher(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

function narrOwnedStory(User $teacher, array $overrides = []): Story
{
    $content = "The peddler raised the tin whistle to his lips and played one long silver note that seemed to hang above the square like a thread of morning light. Every boot in the market began to tap whether its owner wished it or not.\n\n"
        ."Around noon the peddler lowered the whistle, bowed twice to no one in particular, and walked out through the east gate as if nothing had happened.\n\n";

    return Story::create([
        'title' => 'The Tin Whistle Tune',
        'author' => 'R. Caldecott',
        'synopsis' => 'A whistle, a dance, and a town that cannot stop moving.',
        'content' => $content,
        'word_count' => 124,
        'reading_level' => 7,
        'duration_tier' => 'short',
        'difficulty_label' => 'Intermediate',
        'license' => 'Public domain',
        'source_url' => 'https://www.gutenberg.org/ebooks/2',
        'provenance' => 'From the public-domain original.',
        'status' => StoryStatus::Published,
        'created_by' => $teacher->id,
        ...$overrides,
    ]);
}

function narrOwnedManifest(Story $story, bool $dropSecond = false): array
{
    $pages = StoryPagination::pages($story->content)['pages'];
    $sections = [];

    foreach ($pages as $index => $pageText) {
        $sections[] = [
            'n' => $index + 1,
            'hash' => hash('sha256', $pageText),
            'mp3' => 'section-'.($index + 1).'.mp3',
            'seconds' => 60,
            'sentences' => [['s' => 0, 'e' => mb_strlen(rtrim($pageText))]],
        ];
    }

    if ($dropSecond) {
        array_pop($sections);
    }

    return ['voice' => 'af_heart', 'model' => 'kokoro-v1.0', 'sections' => $sections];
}

function narrOwnedSeedAudio(Story $story, array $manifest, bool $withFiles = true): void
{
    Storage::disk('local')->put(
        "narration/manifests/{$story->slug}.json",
        json_encode($manifest, JSON_PRETTY_PRINT),
    );

    if ($withFiles) {
        foreach ($manifest['sections'] as $section) {
            Storage::disk('public')->put(
                "audio/stories/{$story->slug}/{$section['mp3']}",
                str_repeat('ID3-fake-audio', 128),
            );
        }
    }
}

test('status reports queued after a request and narrated once coverage is full', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))
        ->assertOk()
        ->assertJsonPath('state', 'missing')
        ->assertJsonPath('total', StoryPagination::pageCount($story->content));

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $story))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('status.state', 'queued');

    Storage::disk('local')->assertExists('narration/wanted.json');
    Storage::disk('local')->assertExists('narration/export.json');

    $wanted = json_decode(Storage::disk('local')->get('narration/wanted.json'), true);
    expect($wanted)->toHaveCount(1)
        ->and($wanted[0]['slug'])->toBe($story->slug)
        ->and($wanted[0]['pages'])->toHaveCount(StoryPagination::pageCount($story->content));
});

test('requested narration reads as queued until audio lands, then narrated', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $story))->assertOk();

    // Generator "running" heartbeat -> generating with page counters.
    Storage::disk('local')->put("narration/progress/{$story->slug}.json", json_encode([
        'slug' => $story->slug,
        'state' => 'running',
        'done' => 1,
        'total' => StoryPagination::pageCount($story->content),
        'current_page' => 1,
        'started_at' => now()->toIso8601String(),
        'updated_at' => now()->toIso8601String(),
    ]));

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))
        ->assertOk()
        ->assertJsonPath('state', 'generating');

    // Generator finished but the audio is not on this server yet.
    Storage::disk('local')->put("narration/progress/{$story->slug}.json", json_encode([
        'slug' => $story->slug,
        'state' => 'done',
        'done' => StoryPagination::pageCount($story->content),
        'total' => StoryPagination::pageCount($story->content),
        'current_page' => StoryPagination::pageCount($story->content),
        'started_at' => now()->toIso8601String(),
        'updated_at' => now()->toIso8601String(),
    ]));

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))
        ->assertOk()
        ->assertJsonPath('state', 'awaiting-deploy');

    // The usual deploy step lands manifests + MP3s; coverage catches up and
    // the leftover heartbeat + wanted entry are cleaned up by the reader.
    narrOwnedSeedAudio($story, narrOwnedManifest($story));

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))
        ->assertOk()
        ->assertJsonPath('state', 'narrated');

    Storage::disk('local')->assertMissing("narration/progress/{$story->slug}.json");
    expect(json_decode(Storage::disk('local')->get('narration/wanted.json'), true))->toBe([]);
});

test('staleness after an edit surfaces on the status endpoint', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))
        ->assertOk()
        ->assertJsonPath('state', 'narrated');

    $story->update(['content' => str_replace('peddler', 'traveller', $story->content)]);

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))
        ->assertOk()
        ->assertJsonPath('state', 'stale');
});

test('a draft or another teacher\'s story cannot be queued for narration', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher, ['status' => StoryStatus::Draft]);

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $story))
        ->assertStatus(422);

    $published = narrOwnedStory($teacher);

    $otherTeacher = narrTeacher();
    $this->actingAs($otherTeacher)->postJson(route('teacher.stories.narration.request', $published))
        ->assertForbidden();
});

test('deleting a story archives its narration so a re-import stays narrated', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));
    Storage::disk('local')->put("narration/progress/{$story->slug}.json", json_encode(['slug' => $story->slug, 'state' => 'running', 'done' => 1, 'total' => 2]));
    Narration::request($story);

    $this->actingAs($teacher)->post(route('teacher.stories.delete', $story))->assertRedirect(route('teacher.stories.index'));

    $this->assertDatabaseMissing('stories', ['id' => $story->id]);
    Storage::disk('local')->assertExists("narration/manifests/{$story->slug}.json");
    Storage::disk('public')->assertExists("audio/stories/{$story->slug}/section-1.mp3");
    Storage::disk('local')->assertMissing("narration/progress/{$story->slug}.json");
    expect(json_decode(Storage::disk('local')->get('narration/wanted.json'), true))->toBe([]);

    // Re-importing the same source reproduces the same slug -> instant Narrated.
    $reimport = narrOwnedStory($teacher);

    $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $reimport))
        ->assertOk()
        ->assertJsonPath('state', 'narrated');
});

test('tokenless narration feed routes are inert', function () {
    $this->get('/tools/narration/wanted-export')->assertNotFound();
    $this->post('/tools/narration/progress', ['slug' => 'x', 'state' => 'running'])->assertNotFound();
});

test('feed accepts the bearer token and writes progress when configured', function () {
    config(['stories.narration.feed_token' => 'sekret']);

    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    Storage::disk('local')->put('narration/wanted.json', json_encode([
        ['slug' => $story->slug, 'requested_at' => now()->toIso8601String(), 'pages' => ['aaaa']],
    ]));

    $this->withToken('sekret')->getJson('/tools/narration/wanted-export')
        ->assertOk()
        ->assertJsonCount(1, 'wanted')
        ->assertJsonCount(1, 'export.stories')
        ->assertJsonPath('export.stories.0.slug', $story->slug);

    $this->withToken('wrong')->postJson('/tools/narration/progress', [
        'slug' => $story->slug,
        'state' => 'done',
        'done' => 1,
        'total' => 1,
    ])->assertNotFound();

    $this->withToken('sekret')->postJson('/tools/narration/progress', [
        'slug' => $story->slug,
        'state' => 'done',
        'done' => 1,
        'total' => 1,
    ])->assertOk()->assertJsonPath('success', true);

    Storage::disk('local')->assertExists("narration/progress/{$story->slug}.json");

    // A finished feed run clears its wanted entry so nothing re-pulls it.
    expect(json_decode(Storage::disk('local')->get('narration/wanted.json'), true))->toBe([]);
});

test('sync --prune preserves archived narration and only prunes live-story orphans', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));
    Storage::disk('public')->put("audio/stories/{$story->slug}/section-99.mp3", 'orphan');

    // A story that no longer exists leaves its whole narration archive behind.
    Storage::disk('public')->put('audio/stories/gone-story/section-1.mp3', 'dead');
    Storage::disk('local')->put('narration/manifests/gone-story.json', '{}');

    // Stale wanted entry for the dead story + one live entry that survives.
    Storage::disk('local')->put('narration/wanted.json', json_encode([
        ['slug' => 'gone-story', 'requested_at' => now()->toIso8601String(), 'pages' => ['x']],
        ['slug' => $story->slug, 'requested_at' => now()->toIso8601String(), 'pages' => ['y']],
    ]));

    $this->artisan('alice:narration:sync', ['--prune' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('archived narration preserved: gone-story')
        ->expectsOutputToContain('pruned 1 stale wanted entr');

    Storage::disk('public')->assertExists('audio/stories/gone-story/section-1.mp3');
    Storage::disk('local')->assertExists('narration/manifests/gone-story.json');
    Storage::disk('public')->assertMissing("audio/stories/{$story->slug}/section-99.mp3");
    Storage::disk('public')->assertExists("audio/stories/{$story->slug}/section-1.mp3");

    $wanted = json_decode(Storage::disk('local')->get('narration/wanted.json'), true);

    expect($wanted)->toHaveCount(1)
        ->and($wanted[0]['slug'])->toBe($story->slug);
});

test('sync --prune --purge-audio hard-erases archived narration of dead stories', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));
    Storage::disk('public')->put("audio/stories/{$story->slug}/section-99.mp3", 'orphan');

    Storage::disk('public')->put('audio/stories/gone-story/section-1.mp3', 'dead');
    Storage::disk('local')->put('narration/manifests/gone-story.json', '{}');
    Storage::disk('local')->put('narration/wanted.json', json_encode([
        ['slug' => 'gone-story', 'requested_at' => now()->toIso8601String(), 'pages' => ['x']],
    ]));

    $this->artisan('alice:narration:sync', ['--prune' => true, '--purge-audio' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('purged narration of dead story: gone-story');

    Storage::disk('public')->assertMissing('audio/stories/gone-story');
    Storage::disk('local')->assertMissing('narration/manifests/gone-story.json');
    Storage::disk('public')->assertMissing("audio/stories/{$story->slug}/section-99.mp3");
    Storage::disk('public')->assertExists("audio/stories/{$story->slug}/section-1.mp3");
    expect(json_decode(Storage::disk('local')->get('narration/wanted.json'), true))->toBe([]);
});

test('sync --prune never touches a story the generator is actively writing', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));
    Storage::disk('public')->put("audio/stories/{$story->slug}/section-99.mp3", 'orphan');

    Storage::disk('local')->put("narration/progress/{$story->slug}.json", json_encode([
        'slug' => $story->slug,
        'state' => 'running',
        'done' => 1,
        'total' => 2,
        'updated_at' => now()->toIso8601String(),
    ]));

    $this->artisan('alice:narration:sync', ['--prune' => true])->assertSuccessful();

    // Active run: orphan survives.
    Storage::disk('public')->assertExists("audio/stories/{$story->slug}/section-99.mp3");

    // Heartbeat goes stale (generator machine offline) -> guard releases.
    Storage::disk('local')->put("narration/progress/{$story->slug}.json", json_encode([
        'slug' => $story->slug,
        'state' => 'running',
        'done' => 1,
        'total' => 2,
        'updated_at' => now()->subMinutes(20)->toIso8601String(),
    ]));

    $this->artisan('alice:narration:sync', ['--prune' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing("audio/stories/{$story->slug}/section-99.mp3");
});

test('the Publish tab auditions fresh pages with one chained native player', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));

    $this->actingAs($teacher)->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('Hear narration · saved text')
        ->assertSee('data-hear-narration', false)
        ->assertSee('data-hear-audio', false)
        ->assertSee('section-1.mp3', false)
        ->assertSee('Page 1 of 1 · 1:00 total');
});

test('edited text hides the audition rows until regeneration', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));
    $story->update(['content' => str_replace('peddler', 'traveller', $story->content)]);

    $this->actingAs($teacher)->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertDontSee('Hear narration');
});

test('narration state lights the publish tab attention-dot baseline', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    narrOwnedSeedAudio($story, narrOwnedManifest($story));

    $this->actingAs($teacher)->get(route('teacher.stories.edit', $story))
        ->assertOk()
        ->assertSee('data-tab-dot="publish"', false)
        ->assertSee('&quot;state&quot;:&quot;narrated&quot;', false)
        ->assertSee('&quot;narrated_text&quot;', false);

    // A second story whose text drifted from its audio renders stale with no
    // fresh-page baseline. (Coverage is memoized per slug for one render pass,
    // so the staleness check needs fresh state rather than the earlier render.)
    $drifted = narrOwnedStory($teacher);

    narrOwnedSeedAudio($drifted, narrOwnedManifest($drifted));
    $drifted->update(['content' => str_replace('peddler', 'traveller', $drifted->content)]);

    $this->actingAs($teacher)->get(route('teacher.stories.edit', $drifted))
        ->assertOk()
        ->assertSee('&quot;state&quot;:&quot;stale&quot;', false)
        ->assertSee('&quot;narrated_text&quot;:null', false);
});

test('status polling never exhausts the request limiter but the request stays bounded', function () {
    $teacher = narrTeacher();
    $story = narrOwnedStory($teacher);

    // 25 status polls is past the request route's old shared 20/min bucket.
    for ($i = 0; $i < 25; $i++) {
        $this->actingAs($teacher)->getJson(route('teacher.stories.narration.status', $story))->assertOk();
    }

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $story))
        ->assertOk()
        ->assertJsonPath('success', true);

    // The request route still guards: 20 more posts in the same minute trip it.
    $hit429 = false;

    for ($i = 0; $i < 20; $i++) {
        if ($this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $story))->status() === 429) {
            $hit429 = true;

            break;
        }
    }

    expect($hit429)->toBeTrue();
});

test('the local generator auto-spawns only for a real interpreter on an idle story', function () {
    $recorder = new stdClass;
    $recorder->commands = [];

    app()->instance(DetachedCommandLauncher::class, new class($recorder) extends DetachedCommandLauncher
    {
        public function __construct(public object $recorder) {}

        public function launch(string $command, string $logKey): bool
        {
            $this->recorder->commands[] = $command;

            return true;
        }
    });

    $teacher = narrTeacher();
    $idle = narrOwnedStory($teacher);
    $inFlight = narrOwnedStory($teacher, ['title' => 'The Whistle In Flight']);

    Storage::disk('local')->put("narration/progress/{$inFlight->slug}.json", json_encode([
        'slug' => $inFlight->slug,
        'state' => 'running',
        'done' => 1,
        'total' => StoryPagination::pageCount($inFlight->content),
        'current_page' => 1,
        'started_at' => now()->toIso8601String(),
        'updated_at' => now()->toIso8601String(),
    ]));

    // Unconfigured (deployed): pure queue handoff, nothing launched.
    config(['stories.narration.local_generator' => null]);

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $idle))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('spawned', false)
        ->assertJsonPath('status.state', 'queued');

    expect($recorder->commands)->toBe([]);

    // Missing interpreter: never popen (Windows would show an error dialog).
    config(['stories.narration.local_generator' => 'C:\\__no_such_interpreter__.exe']);

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $idle))
        ->assertOk()
        ->assertJsonPath('spawned', false);

    expect($recorder->commands)->toBe([]);

    // Active run: no pile-up, the heartbeat decides.
    config(['stories.narration.local_generator' => $python = base_path('artisan')]);

    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $inFlight))
        ->assertOk()
        ->assertJsonPath('spawned', false);

    expect($recorder->commands)->toBe([]);

    // Configured + idle: exactly one detached launch for that story.
    $this->actingAs($teacher)->postJson(route('teacher.stories.narration.request', $idle))
        ->assertOk()
        ->assertJsonPath('spawned', true);

    expect($recorder->commands)->toHaveCount(1);
    $command = $recorder->commands[0];

    expect($command)->toContain($python)
        ->toContain('--only '.$idle->slug)
        ->toContain($idle->slug.'.log');

    if (PHP_OS_FAMILY === 'Windows') {
        expect($command)->toStartWith('start /B ""');
    } else {
        expect($command)->toStartWith('nohup ')
            ->and($command)->toEndWith('&');
    }

    // The per-slug log was primed for tailing.
    Storage::disk('local')->assertExists("narration/generator/{$idle->slug}.log");
});
