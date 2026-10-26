<?php

namespace App\Support;

/**
 * Integrity signal schema and neutral wording for teacher-only review.
 *
 * Every flag is an objective behavioral observation, never an accusation.
 * The teacher is the arbiter; scores/rewards are never touched automatically.
 */
class IntegritySignals
{
    /** Tier 1 — objective, near-zero false positives. */
    public const TIER_1 = ['focus_lost', 'focus_duration', 'pastes', 'speed_implausible'];

    /** Tier 2 — statistical, needs hedging. */
    public const TIER_2 = ['perfect_and_fast', 'identical_answers'];

    /** Tier 3 — deferred, high false-positive risk. */
    public const TIER_3 = ['collusion', 'ai_detected'];

    /** Maximum plausible characters pasted in a single event (before flagging). */
    public const PASTE_THRESHOLD_CHARS = 20;

    /** Maximum plausible seconds for the full quiz (below = implausibly fast). */
    public const SPEED_SECONDS_PER_QUESTION = 8;

    /**
     * Build the attempt_flags payload from raw quiz JS data.
     */
    public static function buildFlags(array $raw): array
    {
        $flags = [];

        // Tier 1: Focus loss
        if (isset($raw['focus_lost']) && (int) $raw['focus_lost'] > 0) {
            $flags['focus_lost'] = (int) $raw['focus_lost'];
        }
        if (isset($raw['focus_duration']) && (int) $raw['focus_duration'] > 0) {
            $flags['focus_duration'] = (int) $raw['focus_duration'];
        }

        // Tier 1: Paste events
        if (! empty($raw['pastes']) && is_array($raw['pastes'])) {
            $significant = array_filter($raw['pastes'], fn ($p) => ($p['chars'] ?? 0) >= static::PASTE_THRESHOLD_CHARS);
            if ($significant !== []) {
                $flags['pastes'] = array_values(array_map(fn ($p) => [
                    'question' => $p['question'] ?? '?',
                    'chars' => (int) ($p['chars'] ?? 0),
                ], $significant));
            }
        }

        // Tier 1: Implausible speed
        if (isset($raw['time_spent']) && isset($raw['question_count'])) {
            $time = (int) $raw['time_spent'];
            $count = (int) $raw['question_count'];
            $minPlausible = $count * static::SPEED_SECONDS_PER_QUESTION;
            if ($time > 0 && $time < $minPlausible) {
                $flags['speed_implausible'] = [
                    'time' => $time,
                    'questions' => $count,
                    'min_plausible' => $minPlausible,
                ];
            }
        }

        return $flags;
    }

    /**
     * Neutral teacher-facing review notes for a given flags array.
     * Returns an array of {text, tone} pairs.
     */
    public static function reviewNotes(array $flags): array
    {
        $notes = [];

        if (isset($flags['focus_lost'])) {
            $count = $flags['focus_lost'];
            $dur = $flags['focus_duration'] ?? 0;
            $durText = $dur > 0 ? ' (total '.$dur.'s)' : '';
            $s = $count === 1 ? '' : 's';
            $notes[] = [
                'text' => "Left the page {$count} time{$s}{$durText}.",
                'tone' => 'neutral',
            ];
        }

        if (isset($flags['pastes']) && is_array($flags['pastes'])) {
            foreach ($flags['pastes'] as $paste) {
                $notes[] = [
                    'text' => "Question {$paste['question']}: answer was pasted ({$paste['chars']} characters).",
                    'tone' => 'neutral',
                ];
            }
        }

        if (isset($flags['speed_implausible'])) {
            $s = $flags['speed_implausible'];
            $timeMin = floor($s['time'] / 60);
            $timeSec = $s['time'] % 60;
            $timeStr = $timeMin > 0 ? "{$timeMin}m {$timeSec}s" : "{$timeSec}s";
            $notes[] = [
                'text' => "Completed {$s['questions']} questions in {$timeStr} (class-fastest range).",
                'tone' => 'hint',
            ];
        }

        if (isset($flags['perfect_and_fast'])) {
            $notes[] = [
                'text' => 'All correct with unusually low time — consider a quick review.',
                'tone' => 'hint',
            ];
        }

        if (isset($flags['identical_answers'])) {
            $n = $flags['identical_answers'];
            $notes[] = [
                'text' => "Recent attempts share identical answer sequences (×{$n}).",
                'tone' => 'hint',
            ];
        }

        return $notes;
    }

    /**
     * Does this attempt have any flags worth showing?
     */
    public static function hasFlags(array $flags): bool
    {
        return $flags !== [];
    }

    /**
     * Neutral disclaimer text for the review panel footer.
     */
    public static function disclaimer(): string
    {
        return 'These are observations to guide a check-in, not proof of dishonesty. Scores are never changed automatically.';
    }
}
