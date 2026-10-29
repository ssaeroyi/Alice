<?php

namespace App\Games\Journal\Generators;

use App\Games\Journal\ActionGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\Story;

/**
 * Find-in-text (the story text is the game surface). Picks the rule with the
 * richest candidate pool; need is capped to what the story actually contains
 * so the action is always solvable.
 */
class FindGenerator implements ActionGenerator
{
    public const NEED = 5;

    private const RULES = [
        ['type' => 'suffix', 'value' => 'ing', 'label' => 'words ending in -ING'],
        ['type' => 'suffix', 'value' => 'ed', 'label' => 'words ending in -ED'],
        ['type' => 'suffix', 'value' => 'ly', 'label' => 'words ending in -LY'],
        ['type' => 'doubles', 'value' => null, 'label' => 'words with double letters'],
        ['type' => 'short', 'value' => '3', 'label' => 'short words of 3 letters or less'],
        ['type' => 'contains', 'value' => 'ie', 'label' => 'words containing IE'],
        ['type' => 'contains', 'value' => 'ei', 'label' => 'words containing EI'],
        ['type' => 'length', 'value' => '4', 'label' => 'words of exactly 4 letters', 'auto' => false],
        ['type' => 'length', 'value' => '5', 'label' => 'words of exactly 5 letters', 'auto' => false],
    ];

    public function key(): string
    {
        return 'find';
    }

    /** The full rule catalogue a teacher may author (Phase 1: find only). */
    public static function rules(): array
    {
        return self::RULES;
    }

    /** Stable catalogue key: type:value (suffix:ed, doubles:, ...). */
    public static function ruleKey(array $rule): string
    {
        return $rule['type'].':'.($rule['value'] ?? '');
    }

    /** Persisted action key for one rule (find-ed, find-doubles, ...). */
    public static function actionKey(array $rule): string
    {
        return 'find-'.($rule['value'] ?? $rule['type']);
    }

    /** Look up a catalogue rule by type+value; null when unknown. */
    public static function ruleBy(string $type, ?string $value): ?array
    {
        foreach (self::RULES as $rule) {
            if ($rule['type'] === $type && (string) ($rule['value'] ?? '') === (string) ($value ?? '')) {
                return $rule;
            }
        }

        return null;
    }


    public function feasibility(StoryProfile $profile): int
    {
        return $this->bestRules($profile, 1)[0][0] ?? 0;
    }

    public function generate(StoryProfile $profile, Story $story): array
    {
        [, $rule] = $this->bestRules($profile, 1)[0];
        return $this->generateWithRule($profile, $story, $rule);
    }

    /** Top N distinct feasible rules, richest first (authored-only rules skipped). */
    public function bestRules(StoryProfile $profile, int $n = 3): array
    {
        $all = [];
        foreach (self::RULES as $rule) {
            if (($rule['auto'] ?? true) === false) {
                continue;
            }
            $count = $this->candidates($profile, $rule);
            if ($count > 0) {
                $all[] = [$count, $rule];
            }
        }
        usort($all, fn ($a, $b) => $b[0] <=> $a[0]);

        $out = [];
        $seen = [];
        foreach ($all as $pair) {
            $key = self::ruleKey($pair[1]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $pair;
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /**
     * Build the find action for one specific rule (always solvable).
     * $want is the authored target count; it is capped to the story's
     * real candidate pool so a content edit can never strand the move.
     */
    public function generateWithRule(StoryProfile $profile, Story $story, array $rule, ?int $want = null): array
    {
        $count = $this->candidates($profile, $rule);
        $need = max(1, min($want ?? self::NEED, self::NEED, $count));

        $prompt = sprintf('Find %d %s in this story', $need, $rule['label']);
        $title = match ($rule['type']) {
            'suffix' => 'SPELL STRIKE -' . strtoupper($rule['value']),
            'doubles' => 'SPELL STRIKE DBL',
            'short' => 'SPELL STRIKE SHORT',
            default => 'SPELL STRIKE ' . strtoupper((string) $rule['value']),
        };

        return [
            'kind' => 'find',
            'key' => self::actionKey($rule),
            'flavor' => 'SPELL STRIKE',
            'prompt' => $prompt,
            'payload' => [
                'rule' => $rule,
                'need' => $need,
                'answer_words' => $this->answerWords($profile, $rule),
            ],
            'public_payload' => [
                'rule' => $rule,
                'need' => $need,
                'title' => $title,
            ],
        ];
    }

    /**
     * Shared rule test (client match + server verification both use it).
     * Public so journal.js mirrors it and MissionController verifies taps.
     * Normalization is the SAME as the client's normalize(): smart quotes
     * become straight ones before lowercase, so "dog’s" passes server-side
     * too (it used to be the rejection source).
     */
    public static function wordMatches(array $rule, string $word): bool
    {
        $word = self::normalizeWord($word);

        if ($word === '' || !preg_match('/\p{L}/u', $word)) {
            return false;
        }

        return match ($rule['type']) {
            'suffix' => str_ends_with($word, $rule['value']) && mb_strlen($word) > mb_strlen($rule['value']),
            'doubles' => (bool) preg_match('/(.)\1/u', $word),
            'short' => mb_strlen($word) <= (int) $rule['value'],
            'contains' => str_contains($word, $rule['value']),
            'length' => mb_strlen($word) === (int) $rule['value'],
            'prefix' => str_starts_with($word, self::normalizeWord((string) $rule['value'])) && mb_strlen($word) >= 2,
            'set' => in_array($word, (array) ($rule['tokens'] ?? []), true),
            default => false,
        };
    }

    /** Client-parity word normalization: smart quotes → straight, trim, lowercase. */
    public static function normalizeWord(string $word): string
    {
        $word = str_replace(["\u{2019}", "\u{2018}"], "'", $word);

        return mb_strtolower(trim($word));
    }

    /** Distinct candidate count for a rule against this story's text. */
    public function candidates(StoryProfile $profile, array $rule): int
    {
        return match ($rule['type']) {
            'suffix' => count($profile->suffixWords($rule['value'])),
            'doubles' => count($profile->doubleWords()),
            'short' => count($profile->shortWords((int) $rule['value'])),
            'contains' => count($profile->digraphWords($rule['value'])),
            'length' => count($profile->lengthWords((int) $rule['value'])),
            'prefix' => count($profile->initialWords((string) $rule['value'])),
            'set' => count(array_unique((array) ($rule['tokens'] ?? []))),
            default => 0,
        };
    }

    /** @return string[] */
    private function answerWords(StoryProfile $profile, array $rule): array
    {
        $words = match ($rule['type']) {
            'suffix' => $profile->suffixWords($rule['value']),
            'doubles' => $profile->doubleWords(),
            'short' => $profile->shortWords((int) $rule['value']),
            'contains' => $profile->digraphWords($rule['value']),
            'length' => $profile->lengthWords((int) $rule['value']),
            'prefix' => $profile->initialWords((string) $rule['value']),
            'set' => array_values(array_unique((array) ($rule['tokens'] ?? []))),
            default => [],
        };
        return array_slice($words, 0, 50);
    }
}
