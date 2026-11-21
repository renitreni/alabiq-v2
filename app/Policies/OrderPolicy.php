<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Any authenticated user can list orders (scoped to their own by controllers).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * The customer who placed it, the owning restaurant, or a super admin.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->isSuperAdmin()
            || ($user->restaurant && $user->restaurant->id === $order->restaurant_id);
    }

    /**
     * Only customer accounts (or super admins) can place orders.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_CUSTOMER, User::ROLE_SUPER_ADMIN);
    }

    /**
     * Order lifecycle updates (accept, prepare, ready) belong to the
     * owning restaurant or a super admin.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isSuperAdmin()
            || ($user->restaurant && $user->restaurant->id === $order->restaurant_id);
    }

    /**
     * Cancellation: the customer who placed it, the owning restaurant,
     * or a super admin.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->isSuperAdmin()
            || ($user->restaurant && $user->restaurant->id === $order->restaurant_id);
    }
}
