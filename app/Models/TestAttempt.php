<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'story_id', 'attempt_uuid', 'score_pct', 'passed', 'correct_count', 'wrong_count', 'time_spent_seconds', 'answers_json', 'attempt_flags', 'reward', 'xp_awarded', 'hint_tokens_used'])]
class TestAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'answers_json' => 'array',
            'attempt_flags' => 'array',
            'passed' => 'boolean',
            'attempt_uuid' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
