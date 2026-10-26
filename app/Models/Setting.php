<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A tiny global key/value store (admin toggles, feature flags). Values
 * are plain strings; booleans are stored as "1"/"0". Not intended for
 * secrets — API keys stay in the environment only.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public $timestamps = false;
}
