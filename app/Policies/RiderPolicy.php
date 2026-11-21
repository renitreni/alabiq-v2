<?php

namespace App\Policies;

use App\Models\Rider;
use App\Models\User;

class RiderPolicy
{
    /**
     * Viewing a rider profile: the rider's own account or a super admin.
     */
    public function view(User $user, Rider $rider): bool
    {
        return $user->isSuperAdmin() || $user->id === $rider->user_id;
    }

    /**
     * Availability toggles: only the rider's own account.
     */
    public function updateAvailability(User $user, Rider $rider): bool
    {
        return $user->id === $rider->user_id;
    }

    /**
     * Location updates: only the rider's own account.
     */
    public function updateLocation(User $user, Rider $rider): bool
    {
        return $user->id === $rider->user_id;
    }
}
