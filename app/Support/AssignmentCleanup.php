<?php

namespace App\Support;

use App\Models\Assignment;

/**
 * Data repair for assignments stranded by the legacy group nullOnDelete FK.
 * When a class was deleted before the cascade migration, its group-scoped
 * assignments were orphaned (group_id nulled, scope kept as 'group') and
 * stayed on the teacher's assignment board forever.
 */
class AssignmentCleanup
{
    private function __construct() {}

    /**
     * Hard-delete group orphans (scope 'group' with null group_id). An orphan
     * can never resolve an audience — AssignmentAudience matches on group_id —
     * so it only pollutes the teacher's assignment board. Whole-class rows
     * (scope 'all') are deliberately untouched, as are group-scoped rows that
     * still have their group. Soft-deleted orphans are swept too: a trashed
     * orphan is still a dead row and must not survive. Idempotent.
     */
    public static function sweepGroupOrphans(): int
    {
        $orphans = Assignment::query()
            ->where('scope', 'group')
            ->whereNull('group_id')
            ->withTrashed()
            ->get();

        foreach ($orphans as $orphan) {
            $orphan->forceDelete();
        }

        return $orphans->count();
    }
}
