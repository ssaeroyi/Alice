<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'deck_id', 'word', 'definition', 'part_of_speech', 'phonetic', 'example_sentence', 'lookup_count', 'times_correct', 'times_wrong', 'mastery_level', 'last_drilled_at', 'due_at'])]
class SavedWord extends Model
{
    protected function casts(): array
    {
        return [
            'lookup_count' => 'integer',
            'last_drilled_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(WordGymDeck::class);
    }
}
