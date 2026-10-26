<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'description'])]
class Genre extends Model
{
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_genre');
    }
}
