<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;
use App\Models\Story;

/**
 * Word-part fill (workbook C/E/F mechanics): blank the prefix or suffix in a
 * story word and the student types it back ("excited" -> "__cited", answer
 * "ex"; "beauty" -> "beau__", answer "ty").
 *
 * Feasibility is story-derived and dictionary-checked: the stem left after
 * removing the affix must be a real word (a lemma or an inflected form in the
 * offline dictionary), so "reach" never yields the stem "ach". One affix is
 * chosen per battle from the affixes that still have enough valid stems.
 */
class AffixGenerator implements ActionGenerator
{
    private const ITEMS = 3;

    /** Shortest stem worth showing ("ex" + "cited" is fine, "un" + "it" is not). */
    private const MIN_STEM = 3;

    /**
     * An affix is offered when at least this many valid words take it. A move
     * uses up to ITEMS of them, so two-word moves are allowed on short text.
     */
    public const MIN_FIT = 2;

    /** @var array<string, array{type: string, items: list<array{0: string, 1: string}>}>|null */
    private ?array $feasible = null;

    public function key(): string
    {
        return 'affix';
    }

    public function feasibility(StoryProfile $profile): int
    {
        return count($this->feasibleAffixes($profile));
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        $feasible = $this->feasibleAffixes($profile);

        if ($feasible === []) {
            throw new \LogicException('AffixGenerator::generate called without a feasible affix.');
        }

        $affix = array_rand($feasible);

        return $this->generateFor($profile, $affix) ?? throw new \LogicException('AffixGenerator::generate lost its picked affix.');
    }

    /**
     * One pinned-affix move for the teacher-authored pool: same shape as
     * generate() but the affix comes from the stored config, and the items
     * are resolved against the live profile so a story edit that breaks the
     * affix returns null (BattleFactory pads the row away).
     */
    public function generateFor(StoryProfile $profile, string $affix): ?array
    {
        $feasible = $this->feasibleAffixes($profile);

        if (! isset($feasible[$affix])) {
            return null;
        }

        $info = $feasible[$affix];

        $picked = $info['items'];
        shuffle($picked);
        $picked = array_slice($picked, 0, self::ITEMS);

        $items = [];
        foreach ($picked as [$word, $stem]) {
            $items[] = [
                'shown' => $info['type'] === 'prefix' ? '__'.$stem : $stem.'__',
                'answer' => $affix,
            ];
        }

        $flavor = $info['type'] === 'prefix' ? 'PREFIX PUZZLE' : 'SUFFIX PUZZLE';
        $prompt = $info['type'] === 'prefix'
            ? 'Add the missing prefix to each word'
            : 'Add the missing suffix to each word';

        return [
            'kind' => 'fill',
            'key' => 'affix-'.$affix,
            'flavor' => $flavor,
            'prompt' => $prompt,
            'payload' => ['items' => $items],
            // Answers stay in payload (server grading only).
            'public_payload' => [
                'items' => array_map(fn (array $item) => ['shown' => $item['shown']], $items),
                'title' => $flavor,
            ],
        ];
    }

    /**
     * Affixes this story can support, each with its validated [word, stem]
     * pairs. Memoized per instance so feasibility() and generate() share one
     * dictionary lookup per battle.
     *
     * @return array<string, array{type: string, items: list<array{0: string, 1: string}>}>
     */
    public function feasibleAffixes(StoryProfile $profile): array
    {
        if ($this->feasible !== null) {
            return $this->feasible;
        }

        $affixes = config('missions.affixes', ['prefix' => [], 'suffix' => []]);
        $candidates = [];
        $allStems = [];

        foreach (['prefix', 'suffix'] as $type) {
            foreach ((array) ($affixes[$type] ?? []) as $affix) {
                $pairs = [];

                foreach ($profile->distinct() as $word) {
                    if (! preg_match('/^\p{L}+$/u', $word)) {
                        continue;
                    }

                    $stem = self::splitStem($word, $type, $affix);

                    if ($stem !== null) {
                        $pairs[] = [$word, $stem];
                        $allStems[$stem] = true;
                    }
                }

                if ($pairs !== []) {
                    $candidates[$affix] = ['type' => $type, 'items' => $pairs];
                }
            }
        }

        $known = $candidates === [] ? [] : self::knownWords(array_keys($allStems));
        $out = [];

        foreach ($candidates as $affix => $candidate) {
            $valid = array_values(array_filter(
                $candidate['items'],
                fn (array $pair) => isset($known[$pair[1]]),
            ));

            if (count($valid) >= self::MIN_FIT) {
                $out[$affix] = ['type' => $candidate['type'], 'items' => $valid];
            }
        }

        $this->feasible = $out;

        return $out;
    }

    /** The stem left after removing the affix, or null when the word does not take it. */
    public static function splitStem(string $word, string $type, string $affix): ?string
    {
        $wordLen = mb_strlen($word);
        $affixLen = mb_strlen($affix);

        if ($wordLen - $affixLen < self::MIN_STEM) {
            return null;
        }

        if ($type === 'prefix') {
            return str_starts_with($word, $affix) ? mb_substr($word, $affixLen) : null;
        }

        return str_ends_with($word, $affix) ? mb_substr($word, 0, $wordLen - $affixLen) : null;
    }

    /**
     * Stems that are dictionary lemmas or inflected forms, as a set.
     *
     * @param  list<string>  $stems
     * @return array<string, true>
     */
    private static function knownWords(array $stems): array
    {
        $known = [];

        foreach (array_chunk($stems, 500) as $chunk) {
            foreach (DictionaryEntry::query()->whereIn('lemma', $chunk)->pluck('lemma') as $lemma) {
                $known[$lemma] = true;
            }
            foreach (DictionaryForm::query()->whereIn('form', $chunk)->pluck('form') as $form) {
                $known[$form] = true;
            }
        }

        return $known;
    }
}
