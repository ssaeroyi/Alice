<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientError extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'message', 'url', 'stack', 'user_agent', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
