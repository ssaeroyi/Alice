<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One number for one day, keyed by metric. The nightly alice:stats:daily
 * command rolls durable per-day traffic totals out of the request log,
 * which is pruned to the newest ~10k rows on a rolling basis. Reading the
 * traffic chart from these rows keeps the history graph true even after
 * the raw logs are gone.
 */
#[Fillable(['day', 'metric', 'value'])]
class DailyStat extends Model
{
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'value' => 'integer',
        ];
    }
}
