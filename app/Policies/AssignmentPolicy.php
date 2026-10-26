<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\User;

/**
 * Assignment ownership: only the creating teacher (or an admin) may edit,
 * publish, cancel or re-target. Every teacher can view any assignment
 * read-only for transparency.
 */
class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Teacher, Role::Admin], true);
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return in_array($user->role, [Role::Teacher, Role::Admin], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Teacher, Role::Admin], true);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->role === Role::Admin
            || $assignment->teacher_id === $user->id;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function publish(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function cancel(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function archive(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
