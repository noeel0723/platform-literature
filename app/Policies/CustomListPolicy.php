<?php

namespace App\Policies;

use App\Models\CustomList;
use App\Models\User;

class CustomListPolicy
{
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
        return ! $customList->is_private || $user?->is($customList->user) === true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomList $customList): bool
    {
        return $user->is($customList->user) && $user->isActive();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomList $customList): bool
    {
        return $user->is($customList->user) && $user->isActive();
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
