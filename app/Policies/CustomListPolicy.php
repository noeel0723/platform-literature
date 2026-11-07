<?php

namespace App\Policies;

use App\Models\CustomList;
use App\Models\CustomListComment;
use App\Models\User;

class CustomListPolicy
{
    public function comment(User $user, CustomList $customList): bool
    {
        if (! $user->canUseInteractiveFeatures() || ! $customList->user->isActive() || ! $this->view($user, $customList)) {
            return false;
        }

        return $user->is($customList->user)
            || (! $user->hasBlocked($customList->user) && ! $customList->user->hasBlocked($user));
    }

    public function deleteComment(User $user, CustomList $customList, CustomListComment $comment): bool
    {
        return $user->canUseInteractiveFeatures()
            && $this->view($user, $customList)
            && $comment->custom_list_id === $customList->id
            && ($user->id === $comment->user_id || $user->id === $customList->user_id);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, CustomList $customList): bool
    {
        return ! $customList->is_private || ($user?->canUseInteractiveFeatures() && $user->is($customList->user));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canUseInteractiveFeatures();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomList $customList): bool
    {
        return $user->canUseInteractiveFeatures() && $user->is($customList->user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomList $customList): bool
    {
        return $user->canUseInteractiveFeatures() && $user->is($customList->user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomList $customList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomList $customList): bool
    {
        return false;
    }
}
