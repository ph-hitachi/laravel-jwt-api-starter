<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can update the target user model.
     */
    public function update(User $currentUser, User $userToUpdate): bool
    {
        return $currentUser->id === $userToUpdate->id;
    }
}
