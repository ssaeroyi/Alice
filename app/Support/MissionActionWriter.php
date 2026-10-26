<?php

namespace App\Support;

use App\Games\Journal\AuthoredActions;
use App\Games\Journal\Generators\FindGenerator;
use App\Games\Journal\StoryProfile;
use App\Models\Story;
use App\Models\StoryMissionAction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * The single persistence path for authored journal actions (the teacher
 * Journal tab). Mirrors QuestionWriter: one validator, one normalizer, and
 * every label/prompt template always comes from AuthoredActions' catalogue.
 * Teachers pick a move type and a want-count, they never type content, and
 * no answer words are ever stored here (BattleFactory instantiates against
 * live story text and the dictionary lexicon at battle creation).
 *
 * Form contract (unchanged from Phase 1): rule_type + rule_value pick the
 * catalogue row (suffix/ed, length/4, pos/verb, defmatch, sentblank);
 * need is the student target for find-family moves only.
 */
class MissionActionWriter
{
    public static function validate(array $payload, Story $story, ?int $exceptId = null): array
    {
        $ruleType = (string) ($payload['rule_type'] ?? '');
        $ruleValue = $payload['rule_value'] ?? null;

        $row = self::catalogRow($story, $ruleType, $ruleValue);

        $validator = Validator::make($payload, [
            'rule_type' => ['required', 'string'],
            'rule_value' => ['nullable', 'string', 'max:16'],
            'need' => ($row !== null && in_array($row['type'], ['defmatch', 'sentblank', 'affix'], true))
                ? ['nullable', 'integer']
                : ['required', 'integer', 'min:1', 'max:'.FindGenerator::NEED],
        ]);

        $validator->after(function (ValidationValidator $instance) use ($payload, $story, $row, $exceptId): void {
            if ($row === null) {
                $instance->errors()->add('rule_type', 'Pick a move type from the list.');

                return;
            }

            // Initial-letter hunt: the pick is a letter, so feasibility is
            // the per-letter word count, not the catalogue tile's count.
            if ($row['type'] === 'initial') {
                self::validateInitial($instance, $payload, $story, $exceptId);

                return;
            }

            // Solvability invariant: the story must hold at least as many
            // candidates as the move's floor (and, for find-family moves,
            // at least `need` of them), checked live.
            $count = (int) $row['count'];
            $floor = (int) $row['need_floor'];

            if ($count < $floor) {
                $noun = $row['unit'];
                $instance->errors()->add('rule_type', sprintf(
                    'This story has %d %s for that move. It needs at least %d.',
                    $count,
                    $noun,
                    $floor,
                ));

                return;
            }

            if ($row['type'] !== 'defmatch' && $row['type'] !== 'sentblank' && $row['type'] !== 'affix') {
                $need = (int) ($payload['need'] ?? 0);

                if ($need > $count) {
                    $instance->errors()->add('need', 'This story only has '.$count.' matching word'.($count === 1 ? '' : 's').'. Ask for '.$count.' at most.');
                }
            }

            $duplicate = StoryMissionAction::query()
                ->where('story_id', $story->id)
                ->where('action_key', $row['action_key'])
                ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
                ->exists();

            if ($duplicate) {
                $instance->errors()->add('rule_type', 'That move is already in this story\'s journal pool.');
            }
        });

        $data = $validator->validate();

        return self::persistShape($row, $data);
    }

    public static function create(Story $story, array $payload): StoryMissionAction
    {
        return $story->missionActions()->create([
            ...self::validate($payload, $story),
            'position' => self::nextPosition($story),
        ]);
    }

    public static function update(StoryMissionAction $action, array $payload): StoryMissionAction
    {
        $action->update(self::validate($payload, $action->story, $action->id));

        return $action;
    }

    public static function nextPosition(Story $story): int
    {
        return (int) $story->missionActions()->max('position') + 1;
    }

    /**
     * Human summary of a stored config (teacher UI + toasts): the same
     * sentence the student will see when this rule instantiates.
     */
    public static function summary(StoryMissionAction $action): string
    {
        return AuthoredActions::summaryFor($action->config ?? []);
    }

    /** Catalogue row for a form pick (type:value keyed); null when unknown. */
    private static function catalogRow(Story $story, string $ruleType, mixed $ruleValue): ?array
    {
        $key = match ($ruleType) {
            'pos' => 'pos:'.($ruleValue ?? ''),
            'defmatch', 'sentblank', 'arrange', 'initial' => $ruleType,
            default => $ruleType.':'.($ruleValue ?? ''),
        };

        foreach (AuthoredActions::catalog($story) as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    /** Per-letter feasibility for the initial hunt (find-b, find-c, ...). */
    private static function validateInitial(ValidationValidator $instance, array $payload, Story $story, ?int $exceptId): void
    {
        // A single letter or a configured two-letter start (th, sh, ...).
        $letter = mb_strtolower((string) ($payload['rule_value'] ?? ''));

        if (! preg_match('/^[a-z]{1,2}$/', $letter)) {
            $instance->errors()->add('rule_value', 'Pick a letter or a start pattern from the list.');

            return;
        }

        $profile = new StoryProfile($story->content ?? '');
        $count = count($profile->initialWords($letter));
        $upper = strtoupper($letter);
        $need = (int) ($payload['need'] ?? 0);

        if ($count < 1) {
            $instance->errors()->add('rule_value', 'This story has no words starting with '.$upper.'.');

            return;
        }

        if ($need > $count) {
            $instance->errors()->add('need', 'This story only has '.$count.' word'.($count === 1 ? '' : 's').' starting with '.$upper.'. Ask for '.$count.' at most.');
        }

        $duplicate = StoryMissionAction::query()
            ->where('story_id', $story->id)
            ->where('action_key', 'find-'.$letter)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($duplicate) {
            $instance->errors()->add('rule_type', 'That move is already in this story\'s journal pool.');
        }
    }

    /** DB shape for one catalogue row + validated form data. */
    private static function persistShape(array $row, array $data): array
    {
        $need = (int) ($data['need'] ?? FindGenerator::NEED);

        if ($row['type'] === 'initial') {
            $letter = mb_strtolower((string) ($data['rule_value'] ?? ''));
            $upper = strtoupper($letter);

            return [
                'action_key' => 'find-'.$letter,
                'kind' => 'find',
                'config' => [
                    'rule' => ['type' => 'prefix', 'value' => $letter, 'label' => 'words starting with '.$upper],
                    'need' => $need,
                    'prompt' => 'Find {need} words starting with '.$upper.' in this story',
                ],
                'enabled' => true,
            ];
        }

        if ($row['type'] === 'arrange') {
            return [
                'action_key' => 'arrange',
                'kind' => 'arrange',
                'config' => [
                    'type' => 'arrange',
                    'need' => $need,
                    'prompt' => $row['prompt'],
                ],
                'enabled' => true,
            ];
        }

        if ($row['type'] === 'defmatch' || $row['type'] === 'sentblank') {
            return [
                'action_key' => $row['action_key'],
                'kind' => $row['type'] === 'defmatch' ? 'choice' : 'fill',
                'config' => [
                    'type' => $row['type'],
                    'prompt' => $row['prompt'],
                ],
                'enabled' => true,
            ];
        }

        if ($row['type'] === 'affix') {
            // The affix is the only pick; words/answers resolve live at
            // battle creation (generateFor), never stored here.
            return [
                'action_key' => $row['action_key'],
                'kind' => 'fill',
                'config' => [
                    'type' => 'affix',
                    'value' => $row['value'],
                    'prompt' => $row['prompt'],
                ],
                'enabled' => true,
            ];
        }

        $rule = $row['type'] === 'pos'
            ? ['type' => 'pos', 'value' => $row['value'], 'label' => $row['plural']]
            : ['type' => $row['type'], 'value' => $row['value'], 'label' => $row['label']];

        return [
            'action_key' => $row['action_key'],
            'kind' => 'find',
            'config' => [
                'rule' => $rule,
                'need' => $need,
                'prompt' => $row['prompt'],
            ],
            'enabled' => true,
        ];
    }
}
