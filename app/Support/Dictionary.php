<?php

namespace App\Support;

use App\Models\DictionaryEntry;
use App\Models\DictionaryForm;

/**
 * Offline WordNet lookup ladder (TTS_AND_DICTIONARY.md §4.2, student spec
 * STUDENT_PAGES_PLAN §4.3a). Every resolution is local indexed SQL - the
 * runtime never touches an external API.
 *
 * Ladder: normalize -> exact lemma -> inflection via dictionary_forms
 * (irregulars first, generated regulars as fallback) -> hyphen retry ->
 * graceful miss with a proper-noun heuristic.
 */
class Dictionary
{
    private function __construct() {}

    /** Badge thresholds over summed WordNet usage tag counts. */
    private const FREQ_COMMON = 100;

    private const FREQ_UNCOMMON = 10;

    /** Senses shown per part of speech in the popup. */
    public const SENSES_PER_POS = 2;

    /**
     * Resolve a tapped word to lemma + senses + frequency badge, or a
     * structured miss. Never throws for weird input; never returns raw
     * exceptions to callers.
     */
    public static function lookup(string $raw): array
    {
        $word = self::normalize($raw);

        if ($word === '' || mb_strlen($word) > 60) {
            return self::miss($raw);
        }

        // Profanity / explicit-content gate: the searched term itself is
        // blocked before it can ever reach the index (exact-lemma only, so
        // innocent words containing the string are unaffected).
        if (self::isBlocked($word)) {
            return self::miss($raw, true);
        }

        // Exact lemma hit.
        if ($entry = DictionaryEntry::query()->where('lemma', $word)->first()) {
            return self::result($word, $entry, null);
        }

        // Inflection: prefer irregular maps (ran -> run), then any form row.
        if ($form = DictionaryForm::query()->where('form', $word)->orderByDesc('irregular')->first()) {
            $entry = $form->entry;

            return $entry->lemma === $word
                ? self::result($word, $entry, null)
                : self::result($word, $entry, self::formLabel($word, $entry));
        }

        // Hyphen retry: "to-night" -> "tonight".
        $squeezed = str_replace('-', '', $word);

        if ($squeezed !== $word && $squeezed !== '') {
            if ($entry = DictionaryEntry::query()->where('lemma', $squeezed)->first()) {
                return self::result($squeezed, $entry, null);
            }

            if ($form = DictionaryForm::query()->where('form', $squeezed)->orderByDesc('irregular')->first()) {
                $entry = $form->entry;

                return $entry->lemma === $squeezed
                    ? self::result($squeezed, $entry, null)
                    : self::result($squeezed, $entry, self::formLabel($squeezed, $entry));
            }
        }

        return self::miss($raw);
    }

    /**
     * Strip punctuation/curly quotes/possessives and lowercase a token.
     * Internal apostrophes survive (don't), trailing possessive marks go
     * (dog's -> dog).
     */
    public static function normalize(string $raw): string
    {
        $word = trim($raw);
        $word = str_replace(['’', '‘', '“', '”', '–', '—'], ["'", "'", '', '', '-', '-'], $word);
        $word = preg_replace('/^[^\p{L}\p{N}]++|[^\p{L}\p{N}]++$/u', '', $word);
        $word = mb_strtolower($word);

        if (str_ends_with($word, "'s") || str_ends_with($word, "s'")) {
            $word = substr($word, 0, -2);
        }

        return $word;
    }

    public static function freqBadge(?int $freq): ?string
    {
        if ($freq === null || $freq <= 0) {
            return null;
        }

        return match (true) {
            $freq >= self::FREQ_COMMON => 'COMMON',
            $freq >= self::FREQ_UNCOMMON => 'UNCOMMON',
            default => 'RARE',
        };
    }

    private static function result(string $word, DictionaryEntry $primary, ?string $formLabel): array
    {
        $lemma = $primary->lemma;

        // Inflection gate: a form can resolve to a blocked lemma ("fucked" ->
        // "fuck"); the resolved lemma is checked as eagerly as the search.
        if (self::isBlocked($lemma)) {
            return self::miss($word, true);
        }

        $rows = DictionaryEntry::query()
            ->where('lemma', $lemma)
            ->orderByRaw('FIELD(pos, '.implode(',', array_map(fn (string $p) => "'{$p}'", DictionaryEntry::POS_ORDER)).')')
            ->orderBy('sense_num')
            ->get();

        $senses = [];

        foreach ($rows as $row) {
            $pos = $row->pos;

            if (! isset($senses[$pos])) {
                $senses[$pos] = ['pos' => $pos, 'list' => []];
            }

            if (count($senses[$pos]['list']) >= self::SENSES_PER_POS) {
                continue;
            }

            $senses[$pos]['list'][] = array_filter([
                'definition' => $row->definition,
                'example' => $row->example,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $topFreq = (int) $rows->max('freq');

        return [
            'found' => true,
            'word' => $word,
            'resolved_to' => $lemma,
            'form_label' => $formLabel,
            'senses' => array_values($senses),
            'freq_badge' => self::freqBadge($topFreq),
            'looks_like_name' => false,
        ];
    }

    private static function miss(string $raw, bool $filtered = false): array
    {
        $word = self::normalize($raw);
        $looksLikeName = $word !== ''
            && ctype_upper(mb_substr(trim($raw), 0, 1))
            && ! ctype_upper(mb_substr(trim($raw), 0, 2));

        return [
            'found' => false,
            'word' => $word,
            'resolved_to' => null,
            'form_label' => null,
            'senses' => [],
            'freq_badge' => null,
            'looks_like_name' => $looksLikeName && mb_strtolower($raw) !== $raw,
            'filtered' => $filtered,
        ];
    }

    /** True when the exact lemma is on the profanity/explicit-content list. */
    private static function isBlocked(string $word): bool
    {
        return in_array($word, config('dictionary.blocklist', []), true);
    }

    /**
     * Display label explaining HOW the tapped form relates to its lemma:
     * suffix-derived labels where the pattern holds ("running -ing form of
     * run"), plain "form of run" for irregulars.
     */
    private static function formLabel(string $word, DictionaryEntry $entry): string
    {
        $lemma = $entry->lemma;

        foreach ([
            ['-ing form of', 'ing'],
            ['-er form of', 'er'],
            ['-est form of', 'est'],
        ] as [$label, $suffix]) {
            if (str_ends_with($word, $suffix) && ! str_ends_with($word, $suffix.$suffix)) {
                return "{$label} {$lemma}";
            }
        }

        if (str_ends_with($word, 'ied') && str_ends_with($lemma, 'y')) {
            return "past form of {$lemma}";
        }

        if (str_ends_with($word, 'ed')) {
            return "past form of {$lemma}";
        }

        if ((str_ends_with($word, 'ies') || str_ends_with($word, 'es')) && str_ends_with($lemma, 'y')) {
            return "plural of {$lemma}";
        }

        if (str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return "plural of {$lemma}";
        }

        return "form of {$lemma}";
    }
}
