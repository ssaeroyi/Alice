<?php

namespace App\Models;

use App\Enums\StoryProgressStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'story_id', 'status', 'is_favorite', 'time_spent_seconds', 'current_page', 'best_score_pct', 'completed_at'])]
class StoryProgress extends Model
{
    protected function casts(): array
    {
        return [
            'status' => StoryProgressStatus::class,
            'is_favorite' => 'boolean',
            'completed_at' => 'datetime',
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
