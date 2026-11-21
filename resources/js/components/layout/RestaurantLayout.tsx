import React from 'react';
import { Head } from '@inertiajs/react';
import { Navbar } from '@/components/ui/Navbar';

interface RestaurantLayoutProps {
    children: React.ReactNode;
    title?: string;
    restaurant?: { name: string; logo?: string };
}

export const RestaurantLayout: React.FC<RestaurantLayoutProps> = ({
    children,
    title = 'Restaurant Dashboard - Alabiq',
    restaurant,
}) => {
    return (
        <>
            <Head>
                <title>{title}</title>
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <link rel="icon" href="/favicon.ico" />
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet" />
            </Head>

            <div className="min-vh-100 bg-100 d-flex flex-column">
                <Navbar
                    logo="/images/alabiq-logo-full.png"
                    logoAlt="Alabiq"
                    links={[
                        { label: 'Dashboard', href: '/restaurant/dashboard' },
                        { label: 'Orders', href: '/restaurant/orders' },
                        { label: 'Menu', href: '/restaurant/menu' },
                        { label: 'Profile', href: '/restaurant/profile' },
                    ]}
                    userMenu={
                        <div className="d-flex align-items-center gap-3">
                            <div className="dropdown">
                                <button className="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {restaurant?.logo ? (
                                        <img src={restaurant.logo} alt="" className="rounded-circle" width="32" height="32" />
                                    ) : (
                                        <div className="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style={{ width: 32, height: 32 }}>
                                            {restaurant?.name?.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <span className="fw-medium d-none d-sm-inline">{restaurant?.name}</span>
                                </button>
                                <ul className="dropdown-menu dropdown-menu-end">
                                    <li><a className="dropdown-item" href="/restaurant/profile">Restaurant Profile</a></li>
                                    <li><a className="dropdown-item" href="/restaurant/settings">Settings</a></li>
                                    <li><hr className="dropdown-divider" /></li>
                                    <li>
                                        <form method="POST" action="/logout">
                                            <button type="submit" className="dropdown-item text-danger">Logout</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    }
                />

                <main className="flex-1 py-4">{children}</main>

                <footer className="bg-900 text-white py-4 mt-auto">
                    <div className="container px-4 text-center">
                        <p className="text-500 small mb-0">&copy; 2026 Alabiq. All rights reserved.</p>
                    </div>
                </footer>
            </div>
        </>
    );
};
