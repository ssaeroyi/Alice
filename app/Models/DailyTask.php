<?php

namespace App\Models;

use App\Enums\DailyTaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'title', 'description', 'type', 'target_value', 'reward_keys', 'reward_xp', 'active'])]
class DailyTask extends Model
{
    protected function casts(): array
    {
        return [
            'type' => DailyTaskType::class,
            'active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserDailyTask::class);
    }
}
