<?php

namespace App\Games\Journal;

use App\Games\Journal\Generators\AffixGenerator;
use App\Games\Journal\Generators\FindGenerator;
use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;
use App\Models\Story;
use App\Services\StoryVocabService;

/**
 * Teacher-authored action catalogue beyond the classic find rules: the
 * feasibility gallery the Journal tab renders, and the live instantiation
 * of each stored config against the story's CURRENT text and vocab.
 *
 * Catalogue groups:
 *  - scan:  classic FindGenerator rules (suffix/doubles/short/contains/length)
 *           plus the initial-letter hunt (one tile, A-Z grid in the config step)
 *  - sense: parts of speech from the live dictionary lexicon; word-to-definition matches
 *  - build: sentences with a blanked word; scrambled story words
 *
 * Invariants: config stores only the RULE (type/value/need), never concrete
 * answer words; instantiate() queries live data every battle so a content
 * or vocab edit can only shrink a move, never strand it. A config that no
 * longer fits returns null and the caller pads the slot from elsewhere.
 */
class AuthoredActions
{
    /** POS codes (dictionary_entries.pos) hunted in the lexicon => [plural, flavor]. */
    public const POS = [
        'verb' => ['verbs', 'GRAMMAR STRIKE'],
        'noun' => ['nouns', 'GRAMMAR STRIKE'],
        'adj' => ['adjectives', 'GRAMMAR STRIKE'],
        'adv' => ['adverbs', 'GRAMMAR STRIKE'],
    ];

    /** Items per choice/fill move (same shape as riddle/ladder). */
    public const ITEMS = 3;

    /** defmatch needs a word pool at least this big (answers + distractors). */
    public const DEFMATCH_POOL = 4;

    /** sentblank needs at least this many blankable sentences. */
    public const SENTBLANK_POOL = 3;

    /** Interior words that are too plain to make a fair blank. */
    private const BLANK_STOP = [
        'the', 'a', 'an', 'and', 'but', 'or', 'of', 'to', 'in', 'on', 'at',
        'for', 'with', 'that', 'this', 'these', 'those', 'was', 'were', 'is',
        'are', 'been', 'he', 'she', 'they', 'we', 'it', 'his', 'her', 'their',
        'from', 'as', 'by', 'into', 'about', 'had', 'has', 'have', 'not', 'so',
        'if', 'up', 'out', 'one', 'all', 'can', 'will', 'would', 'could',
    ];

    /**
     * Full gallery catalogue: one row per authorable action.
     *
     * Row shape: key (snapshot key), type (rule discriminator the form round-
     * trips), value, label, group, kind (find|choice|fill|arrange, shown as
     * the type badge), count, unit, need_floor, prompt (template with a
     * {need} placeholder), flavor, action_key (persisted dedupe key), plural
     * (pos rows only: the noun used inside prompts).
     *
     * @return list<array>
     */
    public static function catalog(Story $story): array
    {
        // One catalog build = one lexicon build: reset the memo so every
        // call reads the dictionary as it is right now.
        self::$lexiconMemo = [];

        $profile = new StoryProfile($story->content ?? '');
        $find = new FindGenerator();
        $rows = [];

        // scan: the classic find rules (including authored-only length tiles).
        foreach (FindGenerator::rules() as $rule) {
            $rows[] = [
                'key' => FindGenerator::ruleKey($rule),
                'type' => $rule['type'],
                'value' => $rule['value'],
                'label' => $rule['label'],
                'group' => 'scan',
                'kind' => 'find',
                'count' => $find->candidates($profile, $rule),
                'unit' => 'words',
                'need_floor' => 1,
                'prompt' => 'Find {need} '.$rule['label'].' in this story',
                'flavor' => 'SPELL STRIKE',
                'action_key' => FindGenerator::actionKey($rule),
            ];
        }

        // TEMPORARILY HIDDEN: the ABC (first-letter hunt) tile is off for now.
        // Its catalog row is commented out so teachers cannot add it, and the
        // validator rejects it. Restore this block to bring it back.
        // scan: hunt by first letter; the config step shows an A-Z grid
        // with live per-letter counts (one tile, 26 letter choices).
        // $initialCounts = $profile->initialCounts();
        // $rows[] = [
        //     'key' => 'initial',
        //     'type' => 'initial',
        //     'value' => null,
        //     'label' => 'words starting with a chosen letter',
        //     'group' => 'scan',
        //     'kind' => 'find',
        //     'count' => count($initialCounts),
        //     'unit' => 'letters',
        //     'need_floor' => 1,
        //     'prompt' => 'Find {need} words starting with {letter} in this story',
        //     'flavor' => 'SPELL STRIKE',
        //     'action_key' => null, // per-letter at write time (find-b, find-c, ...)
        //     'letters' => $initialCounts,
        // ];

        // sense: hunt by part of speech, resolved from the live lexicon.
        foreach (self::POS as $pos => [$plural, $flavor]) {
            $rows[] = [
                'key' => 'pos:'.$pos,
                'type' => 'pos',
                'value' => $pos,
                'label' => 'words tagged as '.$plural,
                'group' => 'sense',
                'kind' => 'find',
                'count' => self::posCount($story, $pos),
                'unit' => 'words',
                'need_floor' => 1,
                'prompt' => 'Find {need} '.$plural.' in this story',
                'flavor' => $flavor,
                'action_key' => 'pos-'.$pos,
                'plural' => $plural,
            ];
        }

        // TEMPORARILY HIDDEN: meaning match (defmatch) is off for now. Its
        // catalog row is commented out so teachers cannot add it, and the
        // validator rejects it. Restore this block to bring it back.
        // sense: match a story word to its simplified dictionary meaning.
        // $rows[] = [
        //     'key' => 'defmatch',
        //     'type' => 'defmatch',
        //     'value' => null,
        //     'label' => 'Match words to their meanings',
        //     'group' => 'sense',
        //     'kind' => 'choice',
        //     'count' => count(self::definitionPool($story)),
        //     'unit' => 'words',
        //     'need_floor' => self::DEFMATCH_POOL,
        //     'prompt' => 'Match each word to its meaning',
        //     'flavor' => 'MEANING STRIKE',
        //     'action_key' => 'defmatch',
        // ];

        // build: type the word missing from each sentence.
        $blanks = self::blankCandidates($story->content ?? '');
        $rows[] = [
            'key' => 'sentblank',
            'type' => 'sentblank',
            'value' => null,
            'label' => 'Type the missing word in each sentence',
            'group' => 'build',
            'kind' => 'fill',
            'count' => count($blanks),
            'unit' => 'sentences',
            'need_floor' => self::SENTBLANK_POOL,
            'prompt' => 'Type the missing word from each sentence',
            'flavor' => 'MISSING WORDS',
            'action_key' => 'sentblank',
        ];

        // build: unscramble story words shown with their letters shuffled.
        $rows[] = [
            'key' => 'arrange',
            'type' => 'arrange',
            'value' => null,
            'label' => 'Unscramble words from the story',
            'group' => 'build',
            'kind' => 'arrange',
            'count' => count(self::scramblePool($profile)),
            'unit' => 'words',
            'need_floor' => 1,
            'prompt' => 'Tap the letters back into order for {need} words',
            'flavor' => 'LETTER SCRAMBLE',
            'action_key' => 'arrange',
        ];

        // build: type the missing affix back onto a story word (the auto
        // move's PREFIX/SUFFIX PUZZLE, authorable per affix). Rows only
        // exist for affixes the story supports with dictionary-checked
        // stems, so an infeasible affix never reaches the gallery.
        $affixGen = new AffixGenerator();
        foreach ($affixGen->feasibleAffixes($profile) as $affix => $affixInfo) {
            $affixIsPrefix = $affixInfo['type'] === 'prefix';
            $rows[] = [
                'key' => 'affix:'.$affix,
                'type' => 'affix',
                'value' => $affix,
                'label' => 'Type the missing '.($affixIsPrefix ? 'prefix' : 'suffix').' '
                    .($affixIsPrefix ? mb_strtoupper($affix).'-' : '-'.mb_strtoupper($affix)),
                'group' => 'build',
                'kind' => 'fill',
                'count' => count($affixInfo['items']),
                'unit' => 'words',
                'need_floor' => AffixGenerator::MIN_FIT,
                'prompt' => $affixIsPrefix ? 'Add the missing prefix to each word' : 'Add the missing suffix to each word',
                'flavor' => $affixIsPrefix ? 'PREFIX PUZZLE' : 'SUFFIX PUZZLE',
                'action_key' => 'affix-'.$affix,
            ];
        }

        return $rows;
    }

    /**
     * Instantiate one stored config against live data. Returns the full
     * action row (kind/key/flavor/prompt/payload/public_payload) exactly as
     * BattleFactory persists it, or null when the move no longer fits.
     */
    public static function instantiate(array $config, StoryProfile $profile, Story $story): ?array
    {
        $rule = $config['rule'] ?? null;
        $type = $config['type'] ?? null;

        if ($type === 'arrange') {
            return self::arrangeRow($config, $profile);
        }

        if (is_array($rule) && ($rule['type'] ?? '') === 'pos') {
            return self::posRow($config, $story);
        }

        if (is_array($rule)) {
            $find = new FindGenerator();
            if ($find->candidates($profile, $rule) < 1) {
                return null;
            }

            return $find->generateWithRule($profile, $story, $rule, $config['need'] ?? null);
        }

        if ($type === 'defmatch') {
            return self::defmatchRow($story);
        }

        if ($type === 'sentblank') {
            return self::sentblankRow($story);
        }

        if ($type === 'affix') {
            return (new AffixGenerator())->generateFor($profile, (string) ($config['value'] ?? ''));
        }

        return null;
    }

    /**
     * Human sentence for a stored config (the same wording the student
     * prompt uses, with {need} filled in).
     */
    public static function summaryFor(array $config): string
    {
        $template = $config['prompt'] ?? null;

        if ($template === null) {
            // Phase 1 rows stored no template; rebuild from the rule label.
            $label = $config['rule']['label'] ?? 'matching words';
            $template = 'Find {need} '.$label.' in this story';
        }

        return str_replace('{need}', (string) ($config['need'] ?? FindGenerator::NEED), $template);
    }

    /**
     * Distinct story tokens the live lexicon lists under $pos.
     *
     * Resolved fresh on every battle (never a publish-time snapshot): each
     * token's letter-runs look up dictionary_entries.lemma OR
     * dictionary_forms.form (wolves -> wolf, heard -> hear), and a token
     * counts when ANY lemma it resolves to carries the POS. Deliberately
     * permissive - a student is never rejected for a word the dictionary
     * genuinely tags with the hunted part of speech - with the same
     * stop-word/length cut the vocab extractor uses so function-word
     * dictionary oddities ("after" tagged as an adjective) stay out.
     *
     * @return list<string> tokens exactly as the story (and tap matcher) spells them
     */
    public static function posTokens(Story $story, string $pos): array
    {
        if (! isset(self::POS[$pos])) {
            return [];
        }

        $lex = self::storyLexicon($story);
        $out = [];

        foreach ($lex['surfaces'] as $surface) {
            $plain = self::plainKey($surface);

            if (mb_strlen($plain) < 3 || in_array($plain, StoryVocabService::STOP_WORDS, true)) {
                continue;
            }

            foreach ($lex['lemmasOf'][$surface] ?? [] as $lemma) {
                foreach ($lex['rowsOf'][$lemma] ?? [] as $row) {
                    if ($row['pos'] === $pos) {
                        $out[] = $surface;
                        continue 3;
                    }
                }
            }
        }

        return $out;
    }

    public static function posCount(Story $story, string $pos): int
    {
        return count(self::posTokens($story, $pos));
    }

    /**
     * Definition-match pool: story surfaces whose lemmas the dictionary
     * defines. Single-POS lemmas come first - their definition cannot
     * describe a different part of speech than the story uses - and
     * multi-POS lemmas top the pool up with a deterministic row pick
     * (lowest sense, then alphabetical POS), never an arbitrary one.
     * Definitions are simplified the same way the vocab extractor does.
     *
     * @return list<array{word: string, definition: string}>
     */
    public static function definitionPool(Story $story): array
    {
        $lex = self::storyLexicon($story);
        $single = [];
        $multi = [];

        foreach ($lex['surfaces'] as $surface) {
            $plain = self::plainKey($surface);

            if (mb_strlen($plain) < 3 || in_array($plain, StoryVocabService::STOP_WORDS, true)) {
                continue;
            }

            $pick = self::definitionPick($lex, $surface);

            if ($pick === null) {
                continue;
            }

            $entry = [
                'word' => $surface,
                'definition' => $pick['definition'],
            ];

            if ($pick['single']) {
                $single[] = $entry;
            } else {
                $multi[] = $entry;
            }
        }

        return array_slice(array_merge($single, $multi), 0, 30);
    }

    /**
     * Best definition row for one surface: a single-POS lemma's row when
     * one resolves, otherwise the deterministic multi-POS pick. Null when
     * no resolved lemma has a usable content-word definition.
     *
     * @return array{single: bool, definition: string}|null
     */
    private static function definitionPick(array $lex, string $surface): ?array
    {
        $bestSingle = null;
        $bestMulti = null;

        foreach ($lex['lemmasOf'][$surface] ?? [] as $lemma) {
            $rows = array_values(array_filter(
                $lex['rowsOf'][$lemma] ?? [],
                fn (array $row) => isset(self::POS[$row['pos']]) && $row['definition'] !== ''
            ));

            if ($rows === []) {
                continue;
            }

            usort($rows, fn (array $a, array $b) => [$a['sense_num'], $a['pos']] <=> [$b['sense_num'], $b['pos']]);
            $definition = StoryVocabService::simplifyDefinition($rows[0]['definition']);

            if (count(array_unique(array_column($rows, 'pos'))) === 1 && $bestSingle === null) {
                $bestSingle = $definition;
            }

            if ($bestMulti === null) {
                $bestMulti = $definition;
            }
        }

        if ($bestSingle !== null) {
            return ['single' => true, 'definition' => $bestSingle];
        }

        if ($bestMulti !== null) {
            return ['single' => false, 'definition' => $bestMulti];
        }

        return null;
    }

    /**
     * Live lexicon view of a story, resolved fresh on every call.
     *
     *  - surfaces: tap-matchable strings. A hyphenated token splits, because
     *    the reader wraps each side of a hyphen as its own tap span;
     *    apostrophes stay whole because the tap keeps them ("wolf's").
     *  - lemmasOf: surface => distinct dictionary lemmas it resolves to
     *    (direct lemma hits plus inflected-form hits).
     *  - rowsOf: lemma => every dictionary row it owns (pos/sense/definition),
     *    so multi-POS words are read in full instead of one arbitrary row.
     *
     * @return array{surfaces: list<string>, lemmasOf: array<string, list<string>>, rowsOf: array<string, list<array{pos: string, sense_num: int, definition: string}>>}
     */
    private static function storyLexicon(Story $story): array
    {
        // Per-request memo: catalog() reads the lexicon once per part of
        // speech plus once for definitions. The key covers the content, and
        // the static lives only for this PHP request, so dictionary edits
        // can never serve a stale lexicon across requests.
        $memoKey = $story->id.':'.md5((string) $story->content);
        if (isset(self::$lexiconMemo[$memoKey])) {
            return self::$lexiconMemo[$memoKey];
        }

        return self::$lexiconMemo[$memoKey] = self::buildLexicon($story);
    }

    /** @var array<string, array> */
    private static array $lexiconMemo = [];

    private static function buildLexicon(Story $story): array
    {
        $surfaces = [];

        foreach ((new StoryProfile($story->content ?? ''))->distinct() as $token) {
            if (str_contains($token, '-')) {
                foreach (preg_split('/-+/u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                    $surfaces[] = $part;
                }
            } else {
                $surfaces[] = $token;
            }
        }
        $surfaces = array_values(array_unique($surfaces));

        // Lookup keys: letter-runs of at least 2 chars split off every
        // surface, so "wolf's" resolves through "wolf".
        $keys = [];
        foreach ($surfaces as $surface) {
            foreach (preg_split('/[^\p{L}]+/u', $surface, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if (mb_strlen($part) >= 2) {
                    $keys[$part] = true;
                }
            }
        }
        $keys = array_keys($keys);

        if ($keys === []) {
            return ['surfaces' => $surfaces, 'lemmasOf' => [], 'rowsOf' => []];
        }

        // Lookup key => lemmas: direct dictionary hits plus inflected forms.
        $keyLemmas = [];
        foreach (DictionaryEntry::query()->whereIn('lemma', $keys)->pluck('lemma') as $lemma) {
            $keyLemmas[$lemma][$lemma] = true;
        }

        $formRows = DictionaryForm::query()
            ->join('dictionary_entries', 'dictionary_entries.id', '=', 'dictionary_forms.entry_id')
            ->whereIn('dictionary_forms.form', $keys)
            ->get(['dictionary_forms.form', 'dictionary_entries.lemma']);

        foreach ($formRows as $row) {
            $keyLemmas[$row->form][$row->lemma] = true;
        }

        if ($keyLemmas === []) {
            return ['surfaces' => $surfaces, 'lemmasOf' => [], 'rowsOf' => []];
        }

        $lemmasOf = [];
        $allLemmas = [];

        foreach ($surfaces as $surface) {
            foreach (preg_split('/[^\p{L}]+/u', $surface, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                foreach ($keyLemmas[$part] ?? [] as $lemma => $_) {
                    $lemmasOf[$surface][$lemma] = true;
                    $allLemmas[$lemma] = true;
                }
            }
        }

        $rowsOf = [];
        foreach (DictionaryEntry::query()->whereIn('lemma', array_keys($allLemmas))->get(['lemma', 'pos', 'sense_num', 'definition']) as $row) {
            $rowsOf[$row->lemma][] = [
                'pos' => $row->pos,
                'sense_num' => (int) $row->sense_num,
                'definition' => (string) $row->definition,
            ];
        }

        $lemmasOf = array_map(fn (array $set) => array_keys($set), $lemmasOf);

        return ['surfaces' => $surfaces, 'lemmasOf' => $lemmasOf, 'rowsOf' => $rowsOf];
    }

    /** Letters-only lowercase form of a token (stop-word and length checks). */
    private static function plainKey(string $token): string
    {
        return (string) preg_replace('/[^\p{L}]/u', '', mb_strtolower($token));
    }

    /**
     * Sentences from the content with exactly one interior content word
     * blanked (shown => sentence with ___, answer => the word). First/last
     * tokens stay intact so the blank always sits mid-sentence.
     *
     * @return list<array{shown: string, answer: string}>
     */
    public static function blankCandidates(string $content): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($content)) ?: [];
        $out = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            $tokens = preg_split('/\s+/u', $sentence) ?: [];
            $last = count($tokens) - 1;

            if ($last < 3 || count($tokens) > 16) {
                continue;
            }

            for ($i = 1; $i < $last; $i++) {
                $word = self::cleanWord($tokens[$i]);

                if (self::blankable($word)) {
                    $shownTokens = $tokens;
                    $shownTokens[$i] = '___';
                    $out[] = [
                        'shown' => implode(' ', $shownTokens),
                        'answer' => $word,
                    ];
                    break; // one blank per sentence
                }

                if (count($out) >= 30) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** POS hunt: find kind, rule is a runtime-built token set. */
    private static function posRow(array $config, Story $story): ?array
    {
        $pos = (string) ($config['rule']['value'] ?? '');

        if (! isset(self::POS[$pos])) {
            return null;
        }

        $tokens = self::posTokens($story, $pos);

        if ($tokens === []) {
            return null;
        }

        [$plural, $flavor] = self::POS[$pos];
        $need = max(1, min((int) ($config['need'] ?? FindGenerator::NEED), FindGenerator::NEED, count($tokens)));
        $rule = ['type' => 'set', 'value' => $pos, 'label' => $plural, 'tokens' => $tokens];

        return [
            'kind' => 'find',
            'key' => 'pos-'.$pos,
            'flavor' => $flavor,
            'prompt' => sprintf('Find %d %s in this story', $need, $plural),
            'payload' => [
                'rule' => $rule,
                'need' => $need,
                'answer_words' => array_slice($tokens, 0, 50),
            ],
            'public_payload' => [
                'rule' => $rule,
                'need' => $need,
                'title' => $flavor.' '.strtoupper($pos),
            ],
        ];
    }

    /** Definition match: choice kind, 3 word-to-meaning items. */
    private static function defmatchRow(Story $story): ?array
    {
        $pool = self::definitionPool($story);

        if (count($pool) < self::DEFMATCH_POOL) {
            return null;
        }

        shuffle($pool);
        $answers = array_slice($pool, 0, self::ITEMS);
        $allWords = array_column($pool, 'word');
        $items = [];

        foreach ($answers as $answer) {
            $others = array_values(array_filter($allWords, fn ($w) => $w !== $answer['word']));
            shuffle($others);
            $choices = array_slice(array_merge([$answer['word']], $others), 0, 3);
            shuffle($choices);
            $items[] = [
                'q' => $answer['definition'],
                'choices' => $choices,
                'answer' => $answer['word'],
            ];
        }

        return [
            'kind' => 'choice',
            'key' => 'defmatch',
            'flavor' => 'MEANING STRIKE',
            'prompt' => 'Match each word to its meaning',
            'payload' => ['items' => $items],
            // Answers stay server-side only; the panel renders q + choices.
            'public_payload' => [
                'items' => array_map(fn ($item) => ['q' => $item['q'], 'choices' => $item['choices']], $items),
                'title' => 'MEANING STRIKE',
            ],
        ];
    }

    /** Sentence blank: fill kind, 3 sentences with one word missing. */
    private static function sentblankRow(Story $story): ?array
    {
        $candidates = self::blankCandidates($story->content ?? '');

        if (count($candidates) < self::SENTBLANK_POOL) {
            return null;
        }

        shuffle($candidates);
        $picked = array_slice($candidates, 0, self::ITEMS);

        return [
            'kind' => 'fill',
            'key' => 'sentblank',
            'flavor' => 'MISSING WORDS',
            'prompt' => 'Type the missing word from each sentence',
            'payload' => ['items' => $picked],
            'public_payload' => [
                'items' => array_map(fn ($item) => ['shown' => $item['shown']], $picked),
                'title' => 'MISSING WORDS',
            ],
        ];
    }

    /**
     * Tiled arrange: kind 'arrange'. Each item is one story word whose letters
     * are shuffled into tiles; the student taps them back into order. Answers
     * stay in payload; the client only ever sees the tiles.
     */
    public static function arrangeRow(array $config, StoryProfile $profile): ?array
    {
        $pool = self::scramblePool($profile);

        if ($pool === []) {
            return null;
        }

        shuffle($pool);
        $need = max(1, min((int) ($config['need'] ?? self::ITEMS), FindGenerator::NEED, count($pool)));
        $words = array_slice($pool, 0, $need);
        $items = [];

        foreach ($words as $word) {
            $shown = self::scrambleWord($word);
            if ($shown === $word) {
                continue; // degenerate scramble; skip rather than leak the answer
            }
            $items[] = ['tiles' => mb_str_split($shown), 'answer' => $word];
        }

        if ($items === []) {
            return null;
        }

        return [
            'kind' => 'arrange',
            'key' => 'arrange',
            'flavor' => 'LETTER SCRAMBLE',
            'prompt' => sprintf('Tap the letters back into order for %d word%s', count($items), count($items) === 1 ? '' : 's'),
            'payload' => ['items' => $items],
            'public_payload' => [
                'items' => array_map(fn ($item) => ['tiles' => $item['tiles']], $items),
                'title' => 'LETTER SCRAMBLE',
            ],
        ];
    }

    /** Distinct letter-only story tokens of a scramble-friendly length. */
    public static function scramblePool(StoryProfile $profile): array
    {
        $out = [];
        foreach ($profile->distinct() as $w) {
            $len = mb_strlen($w);
            if ($len >= 4 && $len <= 10 && preg_match('/^\p{L}+$/u', $w)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** Shuffle a word's letters; guaranteed different from the original. */
    public static function scrambleWord(string $word): string
    {
        $letters = mb_str_split($word);
        $shuffled = $letters;

        for ($try = 0; $try < 8; $try++) {
            shuffle($shuffled);
            if (implode('', $shuffled) !== $word) {
                return implode('', $shuffled);
            }
        }

        // Palindrome or all-same-letters fallback: swap the outer pair.
        [$shuffled[0], $shuffled[count($shuffled) - 1]] = [$shuffled[count($shuffled) - 1], $shuffled[0]];

        return implode('', $shuffled);
    }

    /** Strip surrounding punctuation from a raw token for the blank answer. */
    private static function cleanWord(string $token): string
    {
        $word = preg_replace('/^[^\p{L}]+|[^\p{L}]+$/u', '', $token) ?? '';

        return mb_strtolower($word);
    }

    private static function blankable(string $word): bool
    {
        if (mb_strlen($word) < 4 || mb_strlen($word) > 10) {
            return false;
        }

        if (! preg_match('/^\p{L}+$/u', $word)) {
            return false;
        }

        // Possessives and contractions are ambiguous answers (knight's could
        // be typed as knight); keep blanks to plain letter words.
        if (str_contains($word, "'") || str_contains($word, "\u{2019}")) {
            return false;
        }

        return ! in_array($word, self::BLANK_STOP, true);
    }
}
