<?php

namespace App\Support;

use App\Enums\CorrectOption;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionSection;
use App\Models\Story;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * The single persistence path for quiz questions and sections. The
 * teacher-side builder (store/update) and the Alice Library importer
 * both go through here, so anything the library writes is byte-for-byte
 * what the builder would have written — one validator, one normalizer,
 * zero drift between authored and packaged quizzes.
 *
 * Input is always a plain payload array (the controller passes the
 * request fields through; the importer passes manifest entries). Every
 * type keeps only its own columns — editing a Matching question into an
 * MCQ can never leave stale pair data behind.
 */
class QuestionWriter
{
    /**
     * Every question column a payload may set besides the shared ones.
     */
    private const FIELDS = [
        'option_a', 'option_b', 'option_c', 'option_d',
        'correct_option', 'correct_options', 'acceptable_answers',
        'match_pairs', 'sequence_items',
    ];

    /**
     * Validate a question payload and return the normalized attributes
     * ready for create/update. Throws a ValidationException on failure,
     * exactly as the form flow expects.
     */
    public static function validate(array $payload, ?Story $story = null, ?int $exceptQuestionId = null): array
    {
        $type = QuestionType::tryFrom((string) ($payload['type'] ?? ''));

        if ($type === null) {
            abort(422);
        }

        $payload = self::canonicalizePairs($payload);

        $rules = [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'question_text' => ['required', 'string', 'min:10', 'max:2000'],
            'section_id' => ['required', 'integer'],
        ];

        if ($type === QuestionType::TrueFalse) {
            $rules['correct_option'] = ['required', Rule::in([CorrectOption::A->value, CorrectOption::B->value])];
        } elseif ($type === QuestionType::Identification) {
            $rules['acceptable_answers'] = ['required', 'array', 'min:1', 'max:10'];
            $rules['acceptable_answers.*'] = ['nullable', 'string', 'max:255'];
        } elseif ($type === QuestionType::Matching) {
            $rules['match_pairs'] = ['required', 'array', 'min:2', 'max:4'];
            $rules['match_pairs.*.left'] = ['required', 'string', 'max:255'];
            $rules['match_pairs.*.right'] = ['required', 'string', 'max:255'];
        } elseif ($type === QuestionType::Sequence) {
            $rules['sequence_items'] = ['required', 'array', 'min:3', 'max:5'];
            $rules['sequence_items.*'] = ['required', 'string', 'max:255'];
        } else {
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $rules['option_'.$letter] = ['required', 'string', 'max:255'];
            }

            $rules['correct_option'] = ['required', Rule::enum(CorrectOption::class)];
        }

        $validator = Validator::make($payload, $rules);

        $validator->after(function (ValidationValidator $instance) use ($payload, $type, $story, $exceptQuestionId): void {
            if ($type === QuestionType::Mcq) {
                $seen = [];

                foreach (['a', 'b', 'c', 'd'] as $letter) {
                    $option = trim((string) ($payload['option_'.$letter] ?? ''));
                    $key = mb_strtolower($option);

                    if ($option !== '' && isset($seen[$key])) {
                        $instance->errors()->add('option_'.$letter, 'The options must all be different from each other.');
                    }

                    $seen[$key] = $option;
                }
            }

            if ($type === QuestionType::Identification) {
                [$answers, $duplicates] = self::uniqueStrings((array) ($payload['acceptable_answers'] ?? []));

                if ($duplicates) {
                    $instance->errors()->add('acceptable_answers', 'The accepted answers must all be different from each other.');
                }

                if ($answers === []) {
                    $instance->errors()->add('acceptable_answers', 'Enter at least one accepted answer.');
                }
            }

            if ($type === QuestionType::Matching) {
                $pairs = self::normalizePairs((array) ($payload['match_pairs'] ?? []));

                foreach ([['left', 'prompts'], ['right', 'matches']] as [$side, $label]) {
                    [, $duplicates] = self::uniqueStrings(array_column($pairs, $side));

                    if ($duplicates) {
                        $instance->errors()->add('match_pairs', 'The '.$label.' must all be different from each other.');
                    }
                }
            }

            if ($type === QuestionType::Sequence) {
                [, $duplicates] = self::uniqueStrings((array) ($payload['sequence_items'] ?? []));

                if ($duplicates) {
                    $instance->errors()->add('sequence_items', 'The steps must all be different from each other.');
                }
            }

            if ($story !== null) {
                $text = trim((string) ($payload['question_text'] ?? ''));

                $duplicate = Question::query()
                    ->where('story_id', $story->id)
                    ->whereRaw('LOWER(question_text) = ?', [mb_strtolower($text)])
                    ->when($exceptQuestionId !== null, fn ($query) => $query->whereKeyNot($exceptQuestionId))
                    ->exists();

                if ($duplicate) {
                    $instance->errors()->add('question_text', 'Another question in this quiz already asks the same thing.');
                }

                $sectionId = (int) ($payload['section_id'] ?? 0);

                if (! $story->sections()->whereKey($sectionId)->exists()) {
                    $instance->errors()->add('section_id', 'Pick a section this story actually has.');
                }
            }
        });

        $data = $validator->validate();

        $kept = match ($type) {
            QuestionType::Mcq => ['option_a', 'option_b', 'option_c', 'option_d', 'correct_option'],
            QuestionType::TrueFalse => ['correct_option'],
            QuestionType::Identification => ['acceptable_answers'],
            QuestionType::Matching => ['match_pairs'],
            QuestionType::Sequence => ['sequence_items'],
        };

        foreach (self::FIELDS as $field) {
            if (! in_array($field, $kept, true)) {
                $data[$field] = null;
            }
        }

        $sectionId = (int) ($data['section_id'] ?? 0);
        $data['section_id'] = ($story !== null && $sectionId !== 0 && $story->sections()->whereKey($sectionId)->exists()) ? $sectionId : null;

        if ($type === QuestionType::Identification) {
            $data['acceptable_answers'] = self::normalizeItems((array) ($data['acceptable_answers'] ?? []));
        } elseif ($type === QuestionType::Matching) {
            $data['match_pairs'] = self::normalizePairs((array) ($data['match_pairs'] ?? []));
        } elseif ($type === QuestionType::Sequence) {
            $data['sequence_items'] = self::normalizeItems((array) ($data['sequence_items'] ?? []));
        }

        return $data;
    }

    public static function create(Story $story, array $payload): Question
    {
        return $story->questions()->create([
            ...self::validate($payload, $story),
            'position' => self::nextPosition($story),
        ]);
    }

    public static function update(Question $question, array $payload): Question
    {
        $question->update(self::validate($payload, $question->story, $question->id));

        return $question;
    }

    public static function nextPosition(Story $story): int
    {
        return (int) $story->questions()->max('position') + 1;
    }

    /**
     * Validate a section payload (name 2-60 chars, unique per story,
     * "General" retired — sections must be descriptive).
     */
    public static function validateSection(array $payload, Story $story, ?int $exceptSectionId = null): array
    {
        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $validator->after(function (ValidationValidator $instance) use ($payload, $story, $exceptSectionId): void {
            $name = trim((string) ($payload['name'] ?? ''));

            if (mb_strtolower($name) === 'general') {
                $instance->errors()->add('name', '"General" is retired — pick a descriptive name.');

                return;
            }

            $duplicate = QuestionSection::query()
                ->where('story_id', $story->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($exceptSectionId !== null, fn ($query) => $query->whereKeyNot($exceptSectionId))
                ->exists();

            if ($duplicate) {
                $instance->errors()->add('name', 'This story already has a section with that name.');
            }
        });

        return $validator->validate();
    }

    public static function nextSectionPosition(Story $story): int
    {
        return (int) $story->sections()->max('position') + 1;
    }

    /**
     * Trim a string list, drop empties, collapse to unique values and
     * reindex — the stored shape for acceptable_answers/sequence_items.
     * Returns [values, hadDuplicates].
     *
     * @return array{0: list<string>, 1: bool}
     */
    private static function uniqueStrings(array $items): array
    {
        $seen = [];
        $duplicates = false;

        foreach ($items as $item) {
            $item = trim((string) $item);

            if ($item === '') {
                continue;
            }

            $key = mb_strtolower($item);

            if (isset($seen[$key])) {
                $duplicates = true;

                continue;
            }

            $seen[$key] = $item;
        }

        return [array_values($seen), $duplicates];
    }

    private static function normalizeItems(array $items): array
    {
        return self::uniqueStrings($items)[0];
    }

    /**
     * Form posts arrive as parallel arrays (match_pairs[left][] +
     * match_pairs[right][]); the manifest uses a list of pair maps.
     * Canonicalize both into the list-of-maps shape before validation.
     */
    private static function canonicalizePairs(array $payload): array
    {
        $pairs = $payload['match_pairs'] ?? null;

        if (is_array($pairs) && (isset($pairs['left']) || isset($pairs['right']))) {
            $lefts = array_values((array) ($pairs['left'] ?? []));
            $rights = array_values((array) ($pairs['right'] ?? []));
            $zipped = [];

            foreach (range(0, max(count($lefts), count($rights)) - 1) as $index) {
                $zipped[] = [
                    'left' => $lefts[$index] ?? '',
                    'right' => $rights[$index] ?? '',
                ];
            }

            $payload['match_pairs'] = $zipped;
        }

        return $payload;
    }

    /**
     * Trim every side of every pair, drop pairs with an empty half and
     * reindex — the stored shape for match_pairs.
     *
     * @return list<array{left: string, right: string}>
     */
    private static function normalizePairs(array $pairs): array
    {
        $normalized = [];

        foreach ($pairs as $pair) {
            if (! is_array($pair)) {
                continue;
            }

            $left = trim((string) ($pair['left'] ?? ''));
            $right = trim((string) ($pair['right'] ?? ''));

            if ($left !== '' && $right !== '') {
                $normalized[] = ['left' => $left, 'right' => $right];
            }
        }

        return $normalized;
    }
}
