<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Anyone (including guests) can browse the marketplace listing.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Active restaurants are public; owners can always view their own.
     */
    public function view(?User $user, Restaurant $restaurant): bool
    {
        if ($restaurant->is_active) {
            return true;
        }

        return $user !== null && ($user->id === $restaurant->owner_id || $user->isSuperAdmin());
    }

    /**
     * Only restaurant accounts (or super admins) can onboard restaurants.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_RESTAURANT, User::ROLE_SUPER_ADMIN);
    }

    /**
     * Only the owner (or super admin) can update a restaurant.
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->id === $restaurant->owner_id || $user->isSuperAdmin();
    }

    /**
     * Only the owner (or super admin) can delete a restaurant.
     */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->id === $restaurant->owner_id || $user->isSuperAdmin();
    }
}
