<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher's manual "done" mark on an assignment for a student. Independent
 * of assignment_targets (which only exists for scope='students'), so it works
 * for every scope. The mark ORs into Assignment::completedFor (rollup,
 * compliance, hub pill, toast-skip) but never into the evidence-based
 * studentProgress() read/quiz flags.
 */
class AssignmentMark extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'teacher_id'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
