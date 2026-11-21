<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    /**
     * Any authenticated user can list their own addresses (scoped
     * by the controller).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Users can only view their own addresses; super admins any.
     */
    public function view(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->id === $customerAddress->user_id || $user->isSuperAdmin();
    }

    /**
     * Any authenticated user can save delivery addresses.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Users can only update their own addresses; super admins any.
     */
    public function update(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->id === $customerAddress->user_id || $user->isSuperAdmin();
    }

    /**
     * Users can only delete their own addresses; super admins any.
     */
    public function delete(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->id === $customerAddress->user_id || $user->isSuperAdmin();
    }
}
