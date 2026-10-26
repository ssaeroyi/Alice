<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form', 'entry_id', 'irregular'])]
class DictionaryForm extends Model
{
    public function entry(): BelongsTo
    {
        return $this->belongsTo(DictionaryEntry::class, 'entry_id');
    }
}
