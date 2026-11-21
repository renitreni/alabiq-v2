<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    /**
     * Menu browsing is public.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Available items are public; unavailable items are visible only to
     * the managing restaurant or a super admin.
     */
    public function view(?User $user, MenuItem $menuItem): bool
    {
        if ($menuItem->is_available) {
            return true;
        }

        return $user !== null && $this->manages($user, $menuItem);
    }

    /**
     * Only the owning restaurant (or super admin) can create items.
     */
    public function create(User $user, MenuItem $menuItem): bool
    {
        return $this->manages($user, $menuItem);
    }

    /**
     * Only the owning restaurant (or super admin) can update items.
     */
    public function update(User $user, MenuItem $menuItem): bool
    {
        return $this->manages($user, $menuItem);
    }

    /**
     * Only the owning restaurant (or super admin) can delete items.
     */
    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $this->manages($user, $menuItem);
    }

    /**
     * Ownership check shared by all management abilities.
     */
    private function manages(User $user, MenuItem $menuItem): bool
    {
        return $user->isSuperAdmin()
            || ($user->restaurant && $user->restaurant->id === $menuItem->restaurant_id);
    }
}
