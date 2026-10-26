<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreakLeaderboardSnapshot extends Model
{
    protected $fillable = [
        'week_start',
        'rank',
        'user_id',
        'display_name',
        'avatar',
        'streak',
    ];

    protected $casts = [
        'week_start' => 'date',
        'rank' => 'integer',
        'streak' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
