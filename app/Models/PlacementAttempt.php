<?php

namespace App\Models;

use App\Enums\PlacementAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'set_id', 'status', 'started_at', 'completed_at', 'score'])]
class PlacementAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PlacementAttemptStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(PlacementSet::class, 'set_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PlacementResponse::class, 'attempt_id');
    }
}
