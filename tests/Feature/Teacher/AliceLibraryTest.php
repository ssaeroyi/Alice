<?php

use App\Enums\StoryStatus;
use App\Models\Question;
use App\Models\Story;
use App\Support\AliceLibrary;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function libraryEntry(array $overrides = []): array
{
    return [
        'slug' => 'test-fable',
        'gutenberg_id' => 21,
        'title' => 'The Test Fable',
        'author' => 'Aesop',
        'license' => 'Public domain',
        'genres' => ['Fantasy'],
        'quiz_difficulty' => 'easy',
        'cover' => null,
        'synopsis' => 'A short test fable used by the Alice Library importer tests.',
        'text' => "A farmer once kept a small flock of sheep near the hills. Every morning he led them out to graze, and every evening he counted each one before locking the gate. The warm fields gave the flock plenty of grass, and the sheep grew fat and contented under the open sky.\n\nOne evening, as he shut the pen, the farmer told his hired boy to keep a careful watch through the night. The boy promised, but the hour was late and his eyes grew heavy, and before long he nodded off beside the gate.\n\nOne stormy night a wolf crept close, lured by the smell of the fattening sheep. The steady sound of its padding feet and the low growl roused the faithful dogs. The dogs barked and drove it away, chasing it far off across the dark fields until the wolf was gone from sight.\n\nThe farmer heard nothing at all, having slept soundly through the whole danger, and in the morning he found his flock safe and calm. The sheep were all grazing as if nothing had happened, and only the panting dogs showed that harm had ever been near.\n\nSo the farmer learned that a watched flock is a safe flock, and he thanked the dogs that had guarded it through the stormy night.",
        'questions' => [
            ['section' => 'Vocabulary', 'type' => 'mcq', 'question_text' => 'What does the word "flock" mean in this story?', 'option_a' => 'A group of sheep', 'option_b' => 'A kind of dog', 'option_c' => 'A farm building', 'option_d' => 'A storm wind', 'correct_option' => 'a'],
            ['section' => 'Comprehension', 'type' => 'true_false', 'question_text' => 'The farmer counted his sheep every evening.', 'correct_option' => 'a'],
            ['section' => 'Comprehension', 'type' => 'identification', 'question_text' => 'Which animal crept close on the stormy night?', 'acceptable_answers' => ['wolf']],
            ['section' => 'Comprehension', 'type' => 'matching', 'question_text' => 'Match each character to their action.', 'match_pairs' => [['left' => 'The farmer', 'right' => 'Counted the sheep'], ['left' => 'The dogs', 'right' => 'Drove the wolf away']]],
            ['section' => 'Comprehension', 'type' => 'sequence', 'question_text' => 'Put the events of the story in order.', 'sequence_items' => ['The sheep graze in the hills', 'A wolf crept close', 'The dogs bark it away', 'The flock is found safe']],
        ],
        ...$overrides,
    ];
}

test('import creates a published story with metadata, sections, questions and difficulty fields', function () {
    $summary = AliceLibrary::importAll([libraryEntry()]);

    expect($summary['created'])->toBe(1)
        ->and($summary['failed'])->toBe([]);

    $story = Story::query()->where('title', 'The Test Fable')->first();

    expect($story->status->value)->toBe('published')
        ->and($story->gutenberg_id)->toBe(21)
        ->and($story->license)->toBe('Public domain')
        ->and($story->source_url)->toBe('https://www.gutenberg.org/ebooks/21')
        ->and($story->reading_level)->toBeInt()
        ->and($story->word_count)->toBeGreaterThan(0)
        ->and($story->duration_tier)->toBe('flash')
        ->and($story->quiz_difficulty)->toBe('easy')
        ->and($story->difficulty_label)->toBeString()
        ->and($story->difficulty_breakdown)->toHaveKey('total')
        ->and($story->provenance)->toContain('Project Gutenberg #21')
        ->and($story->genres()->pluck('name')->toArray())->toBe(['Fantasy'])
        ->and($story->sections()->count())->toBe(2)
        ->and($story->questions()->count())->toBe(5);
});

test('re-importing an unchanged book skips instead of duplicating', function () {
    AliceLibrary::importAll([libraryEntry()]);
    $summary = AliceLibrary::importAll([libraryEntry()]);

    expect($summary['skipped'])->toBe(1)
        ->and(Story::count())->toBe(1);
});

test('changed content updates the story and rewrites the quiz', function () {
    AliceLibrary::importAll([libraryEntry()]);

    $changed = libraryEntry(['text' => str_repeat('The old farmer walked the hill path again. ', 32)]);
    $summary = AliceLibrary::importAll([$changed]);

    $story = Story::query()->where('title', 'The Test Fable')->first();

    expect($summary['updated'])->toBe(1)
        ->and(Story::count())->toBe(1)
        ->and($story->content)->toContain('hill path')
        ->and($story->questions()->count())->toBe(5)
        ->and($story->status->value)->toBe('published');
});

test('an unchanged curated draft converges to published on re-import', function () {
    AliceLibrary::importAll([libraryEntry()]);

    $story = Story::query()->where('title', 'The Test Fable')->first();
    $story->update(['status' => 'draft']);

    $summary = AliceLibrary::importAll([libraryEntry()]);

    expect($summary['skipped'])->toBe(1)
        ->and($story->fresh()->status->value)->toBe('published');
});

test('a deliberately archived curated book stays archived on re-import', function () {
    AliceLibrary::importAll([libraryEntry()]);

    $story = Story::query()->where('title', 'The Test Fable')->first();
    $story->update(['status' => 'archived']);

    $summary = AliceLibrary::importAll([libraryEntry()]);

    expect($summary['skipped'])->toBe(1)
        ->and($story->fresh()->status->value)->toBe('archived');
});

test('texts over the word or character window fail the mechanical gate', function () {
    $tooLong = libraryEntry(['slug' => 'too-long', 'title' => 'Too Long', 'text' => str_repeat('word ', 3300)]);

    $summary = AliceLibrary::importAll([$tooLong]);

    expect($summary['created'])->toBe(0)
        ->and($summary['failed'])->toHaveCount(1)
        ->and($summary['failed'][0]['reason'])->toContain('window');
});

test('texts under the 200-word school-standard floor fail the gate', function () {
    $tooShort = libraryEntry(['slug' => 'too-short', 'title' => 'Too Short', 'text' => 'A tiny blurb that is far too brief to count as a real story.']);

    $summary = AliceLibrary::importAll([$tooShort]);

    expect($summary['created'])->toBe(0)
        ->and($summary['failed'])->toHaveCount(1)
        ->and($summary['failed'][0]['reason'])->toContain('floor');
});

test('unknown genres fail loudly so batches stay honest', function () {
    $badGenre = libraryEntry(['slug' => 'bad-genre', 'title' => 'Bad Genre', 'genres' => ['Space Western']]);

    $summary = AliceLibrary::importAll([$badGenre]);

    expect($summary['failed'][0]['reason'])->toContain('Space Western');
});

test('quizzes under five questions fail the charter window', function () {
    $thinQuiz = libraryEntry([
        'slug' => 'thin-quiz',
        'title' => 'Thin Quiz',
        'questions' => [libraryEntry()['questions'][0]],
    ]);

    $summary = AliceLibrary::importAll([$thinQuiz]);

    expect($summary['failed'][0]['reason'])->toContain('charter window');
});

test('imported quizzes match what QuestionWriter would have written', function () {
    $entry = libraryEntry();
    AliceLibrary::importAll([$entry]);

    $story = Story::query()->where('title', 'The Test Fable')->first();
    $sections = $story->sections()->pluck('id', 'name');

    $matching = Question::query()
        ->where('story_id', $story->id)
        ->where('section_id', $sections['Comprehension'])
        ->where('type', 'matching')
        ->first();

    expect($matching->match_pairs)->toBe($entry['questions'][3]['match_pairs'])
        ->and($matching->acceptable_answers)->toBeNull()
        ->and($matching->sequence_items)->toBeNull();

    $sequence = Question::query()
        ->where('story_id', $story->id)
        ->where('type', 'sequence')
        ->first();

    expect($sequence->sequence_items)->toBe($entry['questions'][4]['sequence_items']);
});

test('the real manifest imports cleanly end to end', function () {
    $manifest = AliceLibrary::manifest();

    expect(count($manifest))->toBeGreaterThan(0);

    $summary = AliceLibrary::importAll();

    expect($summary['failed'])->toBe([])
        ->and($summary['created'])->toBe(count($manifest))
        ->and(Story::where('status', StoryStatus::Published)->count())->toBe(count($manifest));
});
