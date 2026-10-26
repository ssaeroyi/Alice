<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One authored Field Journal battle move for a story. `config` holds the
 * RULE only (e.g. suffix -ed + want count), never concrete answer words:
 * BattleFactory instantiates it against the live story text at battle
 * creation, so edits to the story can never leave a stale answer set.
 */
class StoryMissionAction extends Model
{
    protected $table = 'story_mission_actions';

    protected $fillable = [
        'story_id',
        'action_key',
        'kind',
        'config',
        'enabled',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
