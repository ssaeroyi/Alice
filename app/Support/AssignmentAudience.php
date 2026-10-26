<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves which students an assignment targets,
 * based on the assignment's scope + audience columns.
 */
class AssignmentAudience
{
    /**
     * Get the query builder scoped to students matching this assignment's audience.
     */
    public static function resolve(Assignment $assignment): Builder
    {
        $students = User::query()
            ->where('role', Role::Student)
            // Preview/scratch students (admin "view as") are not real
            // learners - never count them in audiences, rosters or compliance.
            ->whereNot('email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN);

        switch ($assignment->scope) {
            case 'group':
                $students->whereIn('id', function ($query) use ($assignment) {
                    $query->select('user_id')
                        ->from('group_members')
                        ->where('group_id', $assignment->group_id);
                });
                break;

            case 'grade':
                // Use effectiveGrade to handle year-advancement correctly.
                // The DB stores grade_level; effectiveGrade computes in PHP.
                // We fetch all students and filter in-memory for accuracy.
                $students->where(function ($query) use ($assignment) {
                    $query->whereRaw('CASE
                        WHEN grade_level IS NULL THEN 0
                        WHEN grade_hold_school_year = YEAR(CURRENT_DATE) THEN LEAST(10, grade_level)
                        ELSE LEAST(10, grade_level + GREATEST(0, YEAR(CURRENT_DATE) - IFNULL(grade_anchor_year, YEAR(CURRENT_DATE))))
                    END = ?', [$assignment->grade_level]);
                });
                break;

            case 'students':
                $studentIds = $assignment->targets()->pluck('user_id');
                $students->whereIn('id', $studentIds);
                break;

            case 'all':
            default:
                // No additional scope — all students.
                break;
        }

        return $students;
    }

    /**
     * Count how many students are in an assignment's audience.
     */
    public static function count(Assignment $assignment): int
    {
        return static::resolve($assignment)->count();
    }

    /**
     * Get the friendly label for the audience.
     */
    public static function label(Assignment $assignment): string
    {
        return match ($assignment->scope) {
            'all' => 'All students',
            'group' => $assignment->group?->name ?? 'A class',
            'grade' => 'Grade '.$assignment->grade_level,
            'students' => $assignment->targets()->count().' student'.($assignment->targets()->count() === 1 ? '' : 's'),
            default => 'All students',
        };
    }
}
