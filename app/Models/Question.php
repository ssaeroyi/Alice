<?php

namespace App\Models;

use App\Enums\CorrectOption;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['story_id', 'section_id', 'type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'correct_options', 'acceptable_answers', 'match_pairs', 'sequence_items', 'position'])]
class Question extends Model
{
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'correct_option' => CorrectOption::class,
            'correct_options' => 'array',
            'acceptable_answers' => 'array',
            'match_pairs' => 'array',
            'sequence_items' => 'array',
            'section_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The quiz section this question belongs to; null means the story's
     * implicit "General" group that always exists without a row.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionSection::class);
    }
}
