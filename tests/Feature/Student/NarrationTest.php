<?php

use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryPagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function narrStudent(): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
    ]);
}

function narrStory(array $overrides = []): Story
{
    $content = "The peddler raised the tin whistle to his lips and played one long silver note that seemed to hang above the square like a thread of morning light. Every boot in the market began to tap whether its owner wished it or not. The baker tapped behind his counter while flour rose in little clouds around his knees.\n\nNobody could say where the tune ended or the morning began. The blacksmith joined hands with the washerwoman and spun her past the fountain, laughing so hard that he had to sit down on his own anvil. Old Widow Crane waltzed her shopping basket around the well while the stone cat seemed to shift its weight from paw to paw.\n\nAround noon the peddler lowered the whistle, bowed twice to no one in particular, and walked out through the east gate as if nothing had happened. The mayor declared there had been no dancing at all, merely a small earth tremor, and formed a committee about it.\n\n"
        .str_repeat('The tune wandered on through every lane and nobody minded one bit. ', 16);

    return Story::create([
        'title' => 'The Tin Whistle Tune',
        'author' => 'R. Caldecott',
        'synopsis' => 'A whistle, a dance, and a town that cannot stop moving.',
        'content' => $content,
        'word_count' => 640,
        'reading_level' => 7,
        'duration_tier' => 'short',
        'difficulty_label' => 'Intermediate',
        'license' => 'Public domain',
        'source_url' => 'https://www.gutenberg.org/ebooks/2',
        'provenance' => 'From the public-domain original.',
        'status' => StoryStatus::Published,
        ...$overrides,
    ]);
}

function narrFirstPageText(Story $story): string
{
    return StoryPagination::pages($story->content)['pages'][0];
}

function narrManifestPayload(Story $story, ?array $overrides = []): array
{
    $text = narrFirstPageText($story);
    $pages = StoryPagination::pages($story->content)['pages'];
    $sections = [];

    foreach ($pages as $index => $pageText) {
        $sections[] = [
            'n' => $index + 1,
            'hash' => $index === 0 && isset($overrides['first_hash']) ? $overrides['first_hash'] : hash('sha256', $pageText),
            'mp3' => 'section-'.($index + 1).'.mp3',
            'seconds' => 90,
            'sentences' => [
                ['s' => 0, 'e' => mb_strlen(rtrim($pageText))],
            ],
        ];
    }

    if (isset($overrides['drop_second'])) {
        array_pop($sections);
    }

    return ['voice' => 'af_heart', 'model' => 'kokoro-v1.0', 'sections' => $sections];
}

function narrSeedAudio(Story $story, array $manifest, bool $withFiles = true): void
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

test('export writes every published story page as sections in a single handshake file', function () {
    $story = narrStory();
    narrStory(['title' => 'Draft Whisper', 'status' => StoryStatus::Draft]);

    $this->artisan('alice:narration:export')->assertSuccessful();

    Storage::disk('local')->assertExists('narration/export.json');

    $payload = json_decode(Storage::disk('local')->get('narration/export.json'), true);

    expect(count($payload['stories']))->toBe(1)
        ->and($payload['stories'][0]['slug'])->toBe($story->slug)
        ->and($payload['voice'])->toBe('af_heart')
        ->and(count($payload['stories'][0]['sections']))->toBe(StoryPagination::pageCount($story->content))
        ->and($payload['stories'][0]['sections'][0]['n'])->toBe(1)
        ->and($payload['stories'][0]['sections'][0]['text'])->toBe(narrFirstPageText($story));
});

test('export honours the story filter for targeted regeneration runs', function () {
    narrStory();
    $other = narrStory(['title' => 'Only This One']);

    $this->artisan('alice:narration:export', ['--story' => $other->slug])->assertSuccessful();

    $payload = json_decode(Storage::disk('local')->get('narration/export.json'), true);

    expect(count($payload['stories']))->toBe(1)
        ->and($payload['stories'][0]['slug'])->toBe($other->slug);
});

test('the reader plays narration only when manifest hash matches the served text', function () {
    $student = narrStudent();
    $story = narrStory();

    // No manifest at all: no player.
    $this->actingAs($student)->get(route('reader.show', $story))
        ->assertOk()
        ->assertDontSee('LISTEN');

    narrSeedAudio($story, narrManifestPayload($story));

    $this->actingAs($student)->get(route('reader.show', $story))
        ->assertOk()
        ->assertSee('LISTEN')
        ->assertSee('data-narration-segments=', false);
});

test('stale narration hides the player instead of playing outdated text', function () {
    $student = narrStudent();
    $story = narrStory();

    narrSeedAudio($story, narrManifestPayload($story));

    // Teacher edits text in every section so ALL hashes drift from the
    // recorded audio. With multi-segment narration every section must be
    // stale for the player to fully disappear.
    $content = str_replace('peddler', 'traveller', $story->content);
    $content = str_replace('nobody minded one bit', 'nobody cared at all', $content);
    $story->update(['content' => $content]);

    $this->actingAs($student)->get(route('reader.show', $story))
        ->assertOk()
        ->assertDontSee('LISTEN');
});

test('a hash-matching entry with a missing mp3 file stays silent too', function () {
    $student = narrStudent();
    $story = narrStory();

    narrSeedAudio($story, narrManifestPayload($story), withFiles: false);

    $this->actingAs($student)->get(route('reader.show', $story))
        ->assertOk()
        ->assertDontSee('LISTEN');
});

test('sync reports fresh stale and missing coverage per story', function () {
    $story = narrStory();

    // Manifest whose first entry is valid but second section is unnarrated.
    narrSeedAudio($story, narrManifestPayload($story, ['drop_second' => true]));

    $this->artisan('alice:narration:sync')->expectsOutputToContain('1/2 fresh (0 stale, 1 missing)')->assertSuccessful();

    // Now make it stale: edit content so page one's text no longer matches.
    $story->update(['content' => str_replace('peddler', 'traveller', $story->content)]);

    $this->artisan('alice:narration:sync')->expectsOutputToContain('(1 stale')->assertSuccessful();

    // And a story without any manifest shows as fully missing.
    narrStory(['title' => 'Unnarrated Yarn']);
    $this->artisan('alice:narration:sync')->expectsOutputToContain('no manifest')->assertSuccessful();
});

test('prune deletes orphaned mp3s, keeps referenced audio, and preserves dead-story archives', function () {
    $story = narrStory();

    narrSeedAudio($story, narrManifestPayload($story));
    Storage::disk('public')->put("audio/stories/{$story->slug}/section-99.mp3", 'orphan');
    Storage::disk('local')->put('narration/manifests/gone-story.json', '{}');

    Storage::disk('public')->assertExists("audio/stories/{$story->slug}/section-99.mp3");

    $this->artisan('alice:narration:sync', ['--prune' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing("audio/stories/{$story->slug}/section-99.mp3");
    Storage::disk('public')->assertExists("audio/stories/{$story->slug}/section-1.mp3");
    Storage::disk('local')->assertExists('narration/manifests/gone-story.json');
    Storage::disk('local')->assertExists("narration/manifests/{$story->slug}.json");
});

test('the export-to-reader hash contract round-trips byte-for-byte', function () {
    $story = narrStory();

    $this->artisan('alice:narration:export')->assertSuccessful();

    $payload = json_decode(Storage::disk('local')->get('narration/export.json'), true);
    $exportedText = $payload['stories'][0]['sections'][0]['text'];

    // The generator hashes exactly what was exported; the reader must agree.
    narrSeedAudio($story, [
        'voice' => 'af_heart',
        'model' => 'kokoro-v1.0',
        'sections' => [
            ['n' => 1, 'hash' => hash('sha256', $exportedText), 'mp3' => 'section-1.mp3', 'seconds' => 42,
                'sentences' => [['s' => 0, 'e' => mb_strlen(rtrim($exportedText))]],
            ],
        ],
    ]);

    $this->actingAs(narrStudent())->get(route('reader.show', $story))
        ->assertOk()
        ->assertSee('LISTEN')
        ->assertSee('data-narration-segments=', false);
});

test('narration segments include sentence offsets for highlight sync', function () {
    $student = narrStudent();
    $story = narrStory();

    $pages = StoryPagination::pages($story->content)['pages'];
    $firstPage = $pages[0];

    narrSeedAudio($story, [
        'voice' => 'af_heart',
        'model' => 'kokoro-v1.0',
        'sections' => [
            [
                'n' => 1,
                'hash' => hash('sha256', $firstPage),
                'mp3' => 'section-1.mp3',
                'seconds' => 42,
                'sentences' => [
                    ['s' => 0, 'e' => 87],
                    ['s' => 88, 'e' => 195],
                ],
            ],
        ],
    ]);

    $html = $this->actingAs($student)->get(route('reader.show', $story))
        ->assertOk()
        ->getContent();

    // Extract the data-narration-segments JSON from the rendered HTML.
    preg_match('/data-narration-segments="([^"]*)"/', $html, $m);
    expect($m)->not->toBeEmpty();

    $decoded = html_entity_decode($m[1]);
    $segments = json_decode($decoded, true);

    expect($segments)->toHaveCount(1)
        ->and($segments[0]['sentences'])->toHaveCount(2)
        ->and($segments[0]['sentences'][0])->toBe(['s' => 0, 'e' => 87])
        ->and($segments[0]['sentences'][1])->toBe(['s' => 88, 'e' => 195]);
});
