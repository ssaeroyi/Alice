<?php

namespace App\Services;

use App\Enums\StoryStatus;
use App\Models\DictionaryEntry;
use App\Models\Story;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Extracts vocabulary from story content and classifies by dictionary frequency.
 * Used by MiniGameController to source "Open" word type for games.
 */
class StoryVocabService
{
    /**
     * Common English stop words to exclude from vocabulary extraction.
     * Public: the journal's POS hunts reuse the same cut so function-word
     * dictionary oddities (e.g. "after" tagged as an adjective) never land
     * in a student's hunt set.
     */
    public const STOP_WORDS = [
        'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i',
        'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
        'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she',
        'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their', 'what',
        'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go', 'me',
        'when', 'make', 'can', 'like', 'time', 'no', 'just', 'him', 'know', 'take',
        'people', 'into', 'year', 'your', 'good', 'some', 'could', 'them', 'see',
        'other', 'than', 'then', 'now', 'look', 'only', 'come', 'its', 'over',
        'think', 'also', 'back', 'after', 'use', 'two', 'how', 'our', 'work',
        'first', 'well', 'way', 'even', 'new', 'want', 'because', 'any', 'these',
        'give', 'day', 'most', 'us', 'is', 'am', 'are', 'was', 'were', 'been',
        'being', 'has', 'had', 'did', 'does', 'done', 'said', 'each', 'may',
        'still', 'long', 'much', 'very', 'here', 'where', 'why', 'many', 'such',
        'those', 'through', 'put', 'great', 'old', 'always', 'never', 'must',
        'though', 'while', 'might', 'every', 'made', 'before', 'between', 'found',
        'under', 'last', 'let', 'something', 'seemed', 'down', 'going', 'went',
        'upon', 'around', 'quite', 'again', 'thing', 'tell', 'another', 'hand',
        'part', 'place', 'life', 'left', 'world', 'little', 'room', 'eyes',
        'head', 'night', 'home', 'few', 'came', 'came', 'more', 'away', 'own',
        'three', 'feel', 'man', 'men', 'young', 'whole', 'white', 'since',
        'keep', 'both', 'set', 'without', 'enough', 'side', 'kind', 'off',
        'face', 'already', 'sure', 'different', 'door', 'began', 'almost',
    ];

    /**
     * Extract vocabulary from all published stories and store in story_vocab.
     * Run via `php artisan story-vocab:extract`.
     */
    public static function extractAll(): int
    {
        $stories = Story::whereNotNull('content')
            ->get();

        $totalWords = 0;

        foreach ($stories as $story) {
            $totalWords += self::extractForStory($story);
        }

        return $totalWords;
    }

    /**
     * Extract vocabulary for a single story.
     */
    public static function extractForStory(Story $story): int
    {
        // Vocab extraction changes the underlying data that wordCounts() caches,
        // so drop the cached summary here (covers publish + the story-vocab:extract
        // command; extractAll() loops through this, refreshing it each call).
        Cache::forget('vocab:word_counts');

        if (empty($story->content)) {
            return 0;
        }

        $words = self::tokenize($story->content);
        $unique = array_unique($words);
        $filtered = array_values(array_filter($unique, fn ($w) => self::isVocabWord($w)));

        if ($filtered === []) {
            return 0;
        }

        $entries = DictionaryEntry::query()
            ->whereIn('lemma', $filtered)
            ->get()
            ->keyBy('lemma');

        $count = 0;

        foreach ($filtered as $word) {
            $entry = $entries[$word] ?? null;

            if ($entry === null) {
                continue;
            }

            $definition = self::simplifyDefinition($entry->definition ?? '');

            DB::table('story_vocab')->updateOrInsert(
                ['story_id' => $story->id, 'word' => $word],
                [
                    'definition' => $definition,
                    'part_of_speech' => $entry->pos,
                    'freq' => $entry->freq,
                    'frequency_tier' => self::tierFromFreq($entry->freq),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * True when Word Search wants only meaning-carrying content words and not
     * bare function words (pronouns, connectors, helper words). The Word Search
     * grid is themed to a story, so stripping `function` keeps the hunt list
     * to real vocabulary.
     */
    private const WORD_SEARCH_POS = ['noun', 'verb', 'adj', 'adv'];

    /**
     * Pick random words for a game session from story_vocab.
     *
     * When a specific difficulty tier is requested and that tier is too thin
     * to fill `$count` (after exclusions), the picker gracefully broadens to
     * the full pool so a round never starves on an empty tier.
     *
     * @param  array<int, string>  $exclude  words to avoid (recently used)
     * @return array<int, array{id: int, word: string, definition: string|null, frequency_tier: string}>
     */
    public static function pickWords(int $count, string $difficulty = 'all', array $exclude = [], bool $contentOnly = false): array
    {
        $tier = in_array($difficulty, ['easy', 'medium', 'hard']) ? $difficulty : null;

        $base = fn () => DB::table('story_vocab')
            ->whereNotNull('definition')
            ->where('definition', '!=', '');

        $rows = self::drawWithTierFallback($base, $count, $tier, $exclude, $contentOnly);

        return array_map(fn ($row) => [
            'id' => (int) $row->id,
            'word' => $row->word,
            'definition' => $row->definition,
            'frequency_tier' => $row->frequency_tier,
        ], $rows);
    }

    /**
     * Pick random words for a game session from a single story's vocabulary.
     * Used by Word Search's "Open" source so each round draws from one story.
     * Returns the story_id so the round can surface which story was chosen.
     *
     * Like `pickWords`, this broadens past the requested tier when it cannot
     * fill the round, so a short story or thin tier never blocks the game.
     *
     * @param  array<int, string>  $exclude  words to avoid (recently used)
     * @return array<int, array{id: int, word: string, definition: string|null, frequency_tier: string, story_id: int}>
     */
    public static function pickWordsFromStory(int $storyId, int $count, string $difficulty = 'all', array $exclude = [], bool $contentOnly = false): array
    {
        $tier = in_array($difficulty, ['easy', 'medium', 'hard']) ? $difficulty : null;

        $base = fn () => DB::table('story_vocab')
            ->where('story_id', $storyId)
            ->whereNotNull('definition')
            ->where('definition', '!=', '');

        $rows = self::drawWithTierFallback($base, $count, $tier, $exclude, $contentOnly);

        return array_map(fn ($row) => [
            'id' => (int) $row->id,
            'word' => $row->word,
            'definition' => $row->definition,
            'frequency_tier' => $row->frequency_tier,
            'story_id' => (int) $row->story_id,
        ], $rows);
    }

    /**
     * Draw up to `$count` rows from `$base`, optionally restricted to a
     * frequency tier and/or content words, deduped by word. If the requested
     * tier cannot supply `$count` rows after exclusions, the remainder is filled
     * from the same base **without** the tier filter so the caller always gets
     * as many words as the underlying pool allows.
     *
     * @param  \Closure(): Builder  $base
     * @param  array<int, string>  $exclude
     * @return array<int, object>
     */
    private static function drawWithTierFallback(callable $base, int $count, ?string $tier, array $exclude, bool $contentOnly): array
    {
        $drawn = [];
        $seen = array_flip($exclude);

        $draw = function (?string $useTier, int $limit) use ($base, $contentOnly) {
            $query = $base();
            if ($contentOnly) {
                $query->whereIn('part_of_speech', self::WORD_SEARCH_POS);
            }
            if ($useTier !== null) {
                $query->where('frequency_tier', $useTier);
            }

            return $query;
        };

        if ($tier !== null) {
            $query = $draw($tier, $count);
            if ($seen) {
                $query->whereNotIn('word', array_keys($seen));
            }
            foreach ($query->inRandomOrder()->limit($count)->get() as $row) {
                if (isset($seen[$row->word])) {
                    continue;
                }
                $seen[$row->word] = true;
                $drawn[] = $row;
                if (count($drawn) >= $count) {
                    return $drawn;
                }
            }
        }

        if (count($drawn) >= $count) {
            return $drawn;
        }

        $query = $draw(null, $count - count($drawn));
        if ($seen) {
            $query->whereNotIn('word', array_keys($seen));
        }
        foreach ($query->inRandomOrder()->limit($count)->get() as $row) {
            if (isset($seen[$row->word])) {
                continue;
            }
            $seen[$row->word] = true;
            $drawn[] = $row;
            if (count($drawn) >= $count) {
                break;
            }
        }

        return $drawn;
    }

    /**
     * A random published story that has at least one word of vocabulary
     * extracted, so a per-story Word Search round has something to draw from.
     * Returns null when no published story has any vocab yet.
     */
    public static function randomStory(): ?Story
    {
        $candidate = Story::query()
            ->where('status', StoryStatus::Published)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('story_vocab')
                    ->whereColumn('story_vocab.story_id', 'stories.id')
                    ->whereNotNull('definition')
                    ->where('definition', '!=', '');
            })
            ->inRandomOrder()
            ->first();

        return $candidate;
    }

    /**
     * Published stories rich enough to fill a Word Search round, for the setup
     * screen's story picker. Counts content words only (the same part-of-speech
     * cut Word Search uses) and is ordered by title.
     *
     * @return array<int, array{story: Story, word_count: int}>
     */
    public static function pickableStories(int $minWords = 10): array
    {
        $counts = DB::table('story_vocab')
            ->select('story_id')
            ->selectRaw('COUNT(*) as words')
            ->whereNotNull('definition')
            ->where('definition', '!=', '')
            ->whereIn('part_of_speech', self::WORD_SEARCH_POS)
            ->groupBy('story_id')
            ->havingRaw('COUNT(*) >= ?', [$minWords])
            ->pluck('words', 'story_id');

        if ($counts->isEmpty()) {
            return [];
        }

        return Story::query()
            ->where('status', StoryStatus::Published)
            ->whereIn('id', $counts->keys())
            ->orderBy('title')
            ->get()
            ->map(fn (Story $story) => [
                'story' => $story,
                'word_count' => (int) $counts[$story->id],
            ])
            ->all();
    }

    /**
     * Count available words per difficulty tier across all stories.
     * Collapsed to a single conditional-SUM and cached for 6 hours (invalidated
     * by extract commands that rebuild story_vocab).
     */
    public static function wordCounts(): array
    {
        return Cache::remember('vocab:word_counts', 21600, function () {
            $row = DB::table('story_vocab')
                ->whereNotNull('definition')
                ->where('definition', '!=', '')
                ->selectRaw('
                    COUNT(*) as `all`,
                    SUM(CASE WHEN frequency_tier = ? THEN 1 ELSE 0 END) as easy,
                    SUM(CASE WHEN frequency_tier = ? THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN frequency_tier = ? THEN 1 ELSE 0 END) as hard
                ', ['easy', 'medium', 'hard'])
                ->first();

            return [
                'all' => (int) ($row->all ?? 0),
                'easy' => (int) ($row->easy ?? 0),
                'medium' => (int) ($row->medium ?? 0),
                'hard' => (int) ($row->hard ?? 0),
            ];
        });
    }

    /**
     * Pick random sentences from stories for the sentence builder game.
     *
     * Each sentence is returned with per-token part-of-speech and the
     * indices of words to blank — systematically one verb, one noun and
     * one connector (function word) where the sentence has them, so the
     * exercise targets a spread of word types rather than a single gap.
     *
     * @return array<int, array{sentence: string, words: array<int, array{text: string, pos: string|null}>, blanks: array<int, int>, hint: string}>
     */
    public static function pickSentences(int $count, string $difficulty = 'all'): array
    {
        $stories = Story::whereNotNull('content')
            ->inRandomOrder()
            ->limit(20)
            ->get();

        $sentences = [];

        foreach ($stories as $story) {
            // Split into sentences but keep each ending terminator (., !, ?)
            // attached so the rendered sentence can finish with real
            // punctuation instead of trailing off. preg_split with DELIM_CAPTURE
            // returns [text, punct, text, punct, ...]; pair them up below.
            $parts = preg_split('/([.!?]+)/u', $story->content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            if (empty($parts)) {
                continue;
            }

            // Recombine each text chunk with its following punctuation.
            $storySentences = [];
            for ($i = 0, $n = count($parts); $i < $n; $i += 2) {
                $text = $parts[$i];
                $punct = $parts[$i + 1] ?? '';
                $storySentences[] = trim($text).$punct;
            }

            foreach ($storySentences as $s) {
                $s = trim($s);
                $wordCount = str_word_count($s);

                if ($wordCount < 5 || $wordCount > 12) {
                    continue;
                }

                $rawWords = preg_split('/\s+/u', $s);

                if ($rawWords === false || count($rawWords) < 5) {
                    continue;
                }

                $words = self::tagWords($rawWords);

                $blanks = self::chooseBlanks($words);

                if (count($blanks) < 2) {
                    continue;
                }

                $sentences[] = [
                    'sentence' => rtrim($s, '.,;:'),
                    'words' => $words,
                    'blanks' => $blanks,
                    'hint' => self::blankHint($words, $blanks),
                ];

                if (count($sentences) >= $count * 3) {
                    break 2;
                }
            }
        }

        shuffle($sentences);

        return array_slice($sentences, 0, $count);
    }

    /**
     * Resolve each token to its part of speech.
     *
     * Display text is cleaned of stray quotes/dashes/punctuation so tiles
     * and blanks never show artifacts like `"hope"` or `Pass,`. A token is
     * only ever blankable when its cleaned, lowercased form is in the
     * curated whitelist (config/game_sentence_words.php); anything else gets
     * a null pos and can never become a blank. This sidesteps the unreliable
     * offline dictionary tagging (e.g. "wolves" tagged as a verb, "castle"
     * as a verb, quotes kept in tokens).
     *
     * @param  array<int, string>  $tokens
     * @return array<int, array{text: string, pos: string|null, pre: string, post: string}>
     */
    private static function tagWords(array $tokens): array
    {
        $whitelist = config('game_sentence_words', []);

        $tagged = array_map(function (string $t) use ($whitelist) {
            $text = self::cleanDisplayToken($t);
            $normalized = self::normalizeToken($t);

            return [
                'text' => $text,
                'pos' => ($normalized !== '' && isset($whitelist[$normalized]))
                    ? $whitelist[$normalized]
                    : null,
                'pre' => '',
                'post' => self::trailingPunctuation($t),
            ];
        }, $tokens);

        return array_values(array_filter(
            $tagged,
            fn (array $w) => $w['text'] !== ''
        ));
    }

    /**
     * The punctuation that trails a word token and should render as stationary
     * text after the word block. Only commas, periods and sentence terminators
     * (`.` `,` `!` `?`) are kept — quotes/brackets/etc. are dropped so they
     * never collide with a comma or period occupying the same slot (e.g.
     * `eyes,"` renders as `eyes,` not `eyes,"`). Returns '' when none.
     * Leading punctuation is deliberately not captured — the minimal scope is
     * commas and sentence-ending punctuation, both of which are trailing.
     */
    private static function trailingPunctuation(string $token): string
    {
        if (preg_match('/([^\p{L}\p{N}]+)$/u', $token, $m) !== 1) {
            return '';
        }

        $run = $m[1];

        // Keep only the wanted marks; strip any quote/bracket that trails them.
        return (string) preg_replace('/[^,.!?]/u', '', $run);
    }

    /**
     * Strip leading/trailing quotes, dashes and punctuation from a token
     * so its displayed text is clean (e.g. `"hope"` -> `hope`, `Pass,` ->
     * `Pass`).
     */
    private static function cleanDisplayToken(string $token): string
    {
        $clean = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $token) ?? $token;

        return trim($clean);
    }

    /**
     * Strip punctuation and lowercase a token for dictionary lookup.
     */
    private static function normalizeToken(string $token): string
    {
        $word = mb_strtolower(trim($token), 'UTF-8');
        $word = preg_replace('/^[^\p{L}\p{N}]++|[^\p{L}\p{N}]++$/u', '', $word);

        return $word ?? '';
    }

    /**
     * Choose blanks targeting a spread of word types: one verb, one noun,
     * one connector (function word), then one adjective and one adverb.
     * Falls back to any remaining blankable content word when a type is
     * missing. Returns the word indices to blank, up to 5 (adaptive — a
     * sentence resolves to 2-5 depending on how many blankable interior
     * words it actually has).
     *
     * @param  array<int, array{text: string, pos: string|null}>  $words
     * @return array<int, int>
     */
    private static function chooseBlanks(array $words): array
    {
        $indicesOf = function (array $positions, array $exclude = []) use ($words): array {
            $out = [];
            foreach ($words as $i => $w) {
                if ($i === 0 || $i === count($words) - 1) {
                    continue; // keep first/last for sentence shape
                }
                if (in_array($i, $exclude, true)) {
                    continue;
                }
                if (in_array($w['pos'], $positions, true)) {
                    $out[] = $i;
                }
            }

            return $out;
        };

        $blanks = [];

        // Pass 1 — one blank per distinct POS type (spread, up to 5).
        foreach (['verb', 'noun', 'function', 'adj', 'adv'] as $pos) {
            $candidates = $indicesOf([$pos]);

            if ($candidates !== []) {
                $blanks[] = $candidates[array_rand($candidates)];

                if (count($blanks) >= 5) {
                    break;
                }
            }
        }

        // Pass 2 — fill any remaining slots with any blankable interior word
        // (deduped, first/last still excluded, empty-pool guarded) so a
        // sentence produces up to 5 blanks even when it lacks five distinct
        // POS types in its interior.
        $remaining = $indicesOf(['verb', 'noun', 'function', 'adj', 'adv'], $blanks);

        while (count($blanks) < 5 && $remaining !== []) {
            $pick = array_rand($remaining);
            $blanks[] = $remaining[$pick];
            unset($remaining[$pick]);
            $remaining = array_values($remaining);
        }

        sort($blanks);

        return $blanks;
    }

    /**
     * Friendly instruction shown above the word blocks. Deliberately plain:
     * players don't need to know the targeted parts of speech to play.
     */
    private static function blankHint(array $words, array $blanks): string
    {
        return 'Complete the sentence with the word blocks.';
    }

    /**
     * Tokenize text into lowercase words.
     */
    private static function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $words ?: [];
    }

    /**
     * Determine if a word is suitable for vocabulary games.
     */
    private static function isVocabWord(string $word): bool
    {
        if (mb_strlen($word) < 3) {
            return false;
        }

        if (in_array($word, self::STOP_WORDS)) {
            return false;
        }

        if (preg_match('/^\d+$/', $word)) {
            return false;
        }

        return true;
    }

    /**
     * Map dictionary frequency to a difficulty tier.
     */
    private static function tierFromFreq(int $freq): string
    {
        if ($freq >= 100) {
            return 'easy';
        }

        if ($freq >= 10) {
            return 'medium';
        }

        return 'hard';
    }

    /**
     * Simplify a dictionary definition to a short, student-friendly phrase.
     * Takes the first sense and truncates to ~60 chars.
     */
    public static function simplifyDefinition(string $definition): string
    {
        if (empty($definition)) {
            return '';
        }

        $definition = trim($definition);

        $firstSense = preg_split('/[;—–]/u', $definition);

        if (! empty($firstSense)) {
            $definition = trim($firstSense[0]);
        }

        $definition = preg_replace('/\s*\(.*?\)\s*/u', ' ', $definition);
        $definition = preg_replace('/\s{2,}/u', ' ', $definition);
        $definition = trim($definition);

        if (mb_strlen($definition) > 80) {
            $definition = mb_substr($definition, 0, 77).'...';
        }

        return $definition;
    }
}
