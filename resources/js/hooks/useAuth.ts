import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

interface User {
    id: string;
    name: string;
    email: string;
    role: 'customer' | 'restaurant' | 'rider' | 'super_admin';
    avatar?: string;
    emailVerifiedAt?: string;
    createdAt: string;
    // Role-specific data
    restaurant?: {
        id: string;
        name: string;
        slug: string;
        logo?: string;
    };
    rider?: {
        id: string;
        isOnline: boolean;
        vehicleType: string;
    };
}

export function useAuth() {
    const { props } = usePage<{ auth: { user: User | null } }>();

    const user = useMemo(() => props.auth?.user ?? null, [props.auth?.user]);

    const isAuthenticated = !!user;
    const isCustomer = user?.role === 'customer';
    const isRestaurant = user?.role === 'restaurant';
    const isRider = user?.role === 'rider';
    const isSuperAdmin = user?.role === 'super_admin';

    const canAccess = (roles: string[]) => {
        if (!user) return false;
        return roles.includes(user.role);
    };

    return {
        user,
        isAuthenticated,
        isCustomer,
        isRestaurant,
        isRider,
        isSuperAdmin,
        canAccess,
    };
}