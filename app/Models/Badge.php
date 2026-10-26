<?php

namespace App\Models;

use App\Enums\Rarity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'description', 'asset_path', 'rarity', 'active'])]
class Badge extends Model
{
    protected function casts(): array
    {
        return [
            'rarity' => Rarity::class,
            'active' => 'boolean',
        ];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}
