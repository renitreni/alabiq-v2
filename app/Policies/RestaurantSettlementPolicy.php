<?php

namespace App\Policies;

use App\Models\RestaurantSettlement;
use App\Models\User;

/**
 * Settlements: restaurants read their own payouts; only super admins
 * generate, pay or browse across restaurants.
 */
class RestaurantSettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, RestaurantSettlement $settlement): bool
    {
        return $user->isSuperAdmin()
            || ($user->restaurant && $user->restaurant->id === $settlement->restaurant_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function pay(User $user, RestaurantSettlement $settlement): bool
    {
        return $user->isSuperAdmin();
    }
}
