<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One frozen podium row from a past week of a single game type.
 * Written once per (game_type, week_start, rank) via firstOrCreate.
 */
class LeaderboardSnapshot extends Model
{
    protected $fillable = [
        'game_type',
        'week_start',
        'rank',
        'user_id',
        'display_name',
        'avatar',
        'score',
    ];

    protected $casts = [
        'week_start' => 'date',
        'rank' => 'integer',
        'score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
