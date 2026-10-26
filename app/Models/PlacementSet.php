<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'title', 'author', 'source_url', 'content'])]
class PlacementSet extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PlacementAttempt::class, 'set_id');
    }
}
