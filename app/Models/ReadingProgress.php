<?php

namespace App\Models;

use App\Enums\ComprehensionLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per student holding the live, evidence-based comprehension estimate
 * and its honesty metadata. This is the single source of truth for the
 * "reading level that moves" — read by both the student surface (positive
 * grade bar, recommendation band) and the teacher surface (trajectory + basis).
 *
 * Users.comprehension_* remains the placement-time snapshot used as the
 * fallback before enough real evidence exists ("from placement").
 */
#[Fillable(['user_id', 'live_score', 'teacher_score', 'comprehension_level', 'reading_grade', 'sample_count', 'distinct_grades', 'last_activity_at', 'computed_at', 'trend', 'slipping'])]
class ReadingProgress extends Model
{
    protected function casts(): array
    {
        return [
            'comprehension_level' => ComprehensionLevel::class,
            'live_score' => 'integer',
            'teacher_score' => 'integer',
            'reading_grade' => 'integer',
            'sample_count' => 'integer',
            'distinct_grades' => 'integer',
            'last_activity_at' => 'datetime',
            'computed_at' => 'datetime',
            'slipping' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
