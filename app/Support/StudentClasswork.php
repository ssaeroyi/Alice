<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\User;

/**
 * Builds the data behind the student hub's "Classwork" panel: the
 * school-wide assignments a student can see, plus each class they've joined
 * with its member count and active class-scoped assignments. Shared by the hub
 * initial render and the AJAX re-render after a join/leave so both always agree.
 */
class StudentClasswork
{
    public static function data(User $user): array
    {
        $joinedClasses = $user->joinedGroups()
            ->with(['members' => fn ($q) => $q->realAccounts(), 'teacher:id,first_name,last_name'])
            ->get()
            ->map(fn ($group) => static::hydrateGroup($group));

        return [
            'assignments' => $user->activeAssignments(),
            'finished' => $user->finishedAssignments(),
            'joinedClasses' => $joinedClasses,
        ];
    }

    public static function hydrateGroup($group)
    {
        $group->setAttribute('members_count', $group->members->count());
        $group->setAttribute('active_assignments', Assignment::query()
            ->where('scope', 'group')
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->orderBy('assigned_at', 'desc')
            ->with('story:id,title,cover_image')
            ->get(['id', 'title', 'story_id', 'due_at', 'assigned_at', 'created_at', 'status']));
        $group->setAttribute('finished_assignments', Assignment::query()
            ->where('scope', 'group')
            ->where('group_id', $group->id)
            ->where('status', 'archived')
            ->orderBy('finished_at', 'desc')
            ->with('story:id,title,cover_image')
            ->get(['id', 'title', 'story_id', 'due_at', 'assigned_at', 'created_at', 'status']));

        return $group;
    }
}
