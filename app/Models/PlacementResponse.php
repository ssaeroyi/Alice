<?php

namespace App\Models;

use App\Enums\PlacementStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'step', 'payload'])]
class PlacementResponse extends Model
{
    protected function casts(): array
    {
        return [
            'step' => PlacementStep::class,
            'payload' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PlacementAttempt::class, 'attempt_id');
    }
}
