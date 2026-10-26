<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Field Journal battle per (user, story). Hearts are shared across the
 * battle; at 0 the action set regenerates (never a soft-lock). Won battles
 * unlock the reader's FINISH button.
 */
class StoryBattle extends Model
{
    protected $fillable = ['user_id', 'story_id', 'hearts', 'boss_hp', 'status'];

    protected function casts(): array
    {
        return [
            'hearts' => 'integer',
            'boss_hp' => 'integer',
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

    public function actions(): HasMany
    {
        return $this->hasMany(StoryBattleAction::class)->orderBy('position');
    }

    public function isWon(): bool
    {
        return $this->status === 'won';
    }
}
