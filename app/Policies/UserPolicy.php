<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management is a super admin privilege. Admins can never change
 * their own role or deactivate their own account (prevents lockout).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function updateRole(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }
}
