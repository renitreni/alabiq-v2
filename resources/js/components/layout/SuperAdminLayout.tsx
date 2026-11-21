import React from 'react';
import { Head } from '@inertiajs/react';
import { Navbar } from '@/components/ui/Navbar';

interface SuperAdminLayoutProps {
    children: React.ReactNode;
    title?: string;
    admin?: { name: string; avatar?: string };
}

export const SuperAdminLayout: React.FC<SuperAdminLayoutProps> = ({
    children,
    title = 'Super Admin Dashboard - Alabiq',
    admin,
}) => {
    return (
        <>
            <Head>
                <title>{title}</title>
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <link rel="icon" href="/favicon.ico" />
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
            </Head>

            <div className="min-h-screen bg-[var(--alabiq-gray-50)] flex flex-col">
                <Navbar
                    logo="/images/alabiq-logo-full.png"
                    logoAlt="Alabiq Admin"
                    links={[
                        { label: 'Dashboard', href: '/admin/dashboard' },
                        { label: 'Restaurants', href: '/admin/restaurants' },
                        { label: 'Riders', href: '/admin/riders' },
                        { label: 'Reports', href: '/admin/reports' },
                        { label: 'Settings', href: '/admin/settings' },
                    ]}
                    userMenu={
                        <div className="d-flex align-items-center gap-3">
                            <span className="badge bg-danger px-3 py-2 small">Super Admin</span>
                            <div className="dropdown">
                                <button className="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {admin?.avatar ? (
                                        <img src={admin.avatar} alt="" className="rounded-circle" width="32" height="32" />
                                    ) : (
                                        <div className="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style={{ width: 32, height: 32 }}>
                                            {admin?.name?.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <span className="fw-medium d-none d-sm-inline">{admin?.name}</span>
                                </button>
                                <ul className="dropdown-menu dropdown-menu-end">
                                    <li><a className="dropdown-item" href="/admin/profile">Profile</a></li>
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

                <footer className="bg-[var(--alabiq-gray-900)] text-white py-4 mt-auto">
                    <div className="container px-4 text-center">
                        <p className="text-[var(--alabiq-gray-500)] small mb-0">&copy; 2025 Alabiq. All rights reserved.</p>
                    </div>
                </footer>
            </div>
        </>
    );
};
