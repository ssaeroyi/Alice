<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Story;
use App\Models\User;

/**
 * Story ownership: the shared library is readable/assignable by every
 * teacher, but authoring actions (edit, publish, archive, duplicate,
 * delete, question/section management) are gated to the creator
 * (or an admin). Published stories are immutable to students.
 */
class StoryPolicy
{
    public function view(User $user, Story $story): bool
    {
        return in_array($user->role, [Role::Teacher, Role::Admin], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Teacher, Role::Admin], true);
    }

    public function update(User $user, Story $story): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        // Imported/shared-library books carry no author (created_by null):
        // any teacher may edit them. Teacher-authored stories are gated
        // to their creator.
        return $story->created_by === null || $story->created_by === $user->id;
    }

    public function delete(User $user, Story $story): bool
    {
        return $this->update($user, $story);
    }

    public function archive(User $user, Story $story): bool
    {
        return $this->update($user, $story);
    }

    public function publish(User $user, Story $story): bool
    {
        return $this->update($user, $story);
    }

    public function duplicate(User $user, Story $story): bool
    {
        return in_array($user->role, [Role::Teacher, Role::Admin], true);
    }

    public function manageQuestions(User $user, Story $story): bool
    {
        return $this->update($user, $story);
    }

    public function manageJournal(User $user, Story $story): bool
    {
        return $this->update($user, $story);
    }
}
