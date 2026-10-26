<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'activity_date', 'read_sessions', 'quiz_submissions', 'drills_completed', 'game_rounds', 'reading_seconds_today', 'task_rerolls', 'reading_goal_claimed_at', 'study_goal_claimed_at', 'stamped_at'])]
class UserDailyActivity extends Model
{
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'stamped_at' => 'datetime',
            'reading_goal_claimed_at' => 'datetime',
            'study_goal_claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Total major actions recorded for the day — the stamp-worthiness counter.
     */
    public function totalActions(): int
    {
        return $this->read_sessions + $this->quiz_submissions + $this->drills_completed + $this->game_rounds;
    }
}
