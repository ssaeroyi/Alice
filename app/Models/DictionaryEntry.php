<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lemma', 'pos', 'sense_num', 'definition', 'example', 'freq'])]
class DictionaryEntry extends Model
{
    /**
     * Display order for sense groups; 'function' closes the list so
     * content senses always lead. FIELD() ordering relies on every real
     * pos being present here.
     */
    public const POS_ORDER = ['noun', 'verb', 'adj', 'adv', 'function'];

    public function forms(): HasMany
    {
        return $this->hasMany(DictionaryForm::class, 'entry_id');
    }
}
