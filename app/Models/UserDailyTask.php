<?php

namespace App\Models;

use App\Enums\UserDailyTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'daily_task_id', 'task_date', 'progress', 'status', 'claimed_at'])]
class UserDailyTask extends Model
{
    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'status' => UserDailyTaskStatus::class,
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DailyTask::class, 'daily_task_id');
    }
}
