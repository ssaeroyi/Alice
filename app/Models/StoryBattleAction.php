<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryBattleAction extends Model
{
    protected $fillable = [
        'story_battle_id', 'action_key', 'kind', 'flavor', 'prompt',
        'payload', 'public_payload', 'status', 'timer_seconds', 'position', 'deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'public_payload' => 'array',
            'timer_seconds' => 'integer',
            'position' => 'integer',
            'deadline_at' => 'datetime',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(StoryBattle::class, 'story_battle_id');
    }
}
