import React from 'react';
import { Head } from '@inertiajs/react';
import { Navbar } from '@/components/ui/Navbar';

interface RiderLayoutProps {
    children: React.ReactNode;
    title?: string;
    rider?: { name: string; avatar?: string; isOnline?: boolean } | null;
}

export const RiderLayout: React.FC<RiderLayoutProps> = ({
    children,
    title = 'Rider Dashboard - Alabiq',
    rider,
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
                        { label: 'Dashboard', href: '/rider/dashboard' },
                        { label: 'Deliveries', href: '/rider/deliveries' },
                        { label: 'Earnings', href: '/rider/earnings' },
                        { label: 'Profile', href: '/rider/profile' },
                    ]}
                    userMenu={
                        <div className="d-flex align-items-center gap-3">
                            <div className="d-flex align-items-center gap-2 px-2 py-1 rounded-pill bg-soft-success">
                                <span className={`rounded-circle ${rider?.isOnline ? 'bg-success' : 'bg-secondary'}`} style={{ width: 8, height: 8 }} />
                                <span className="fw-medium text-success small">{rider?.isOnline ? 'Online' : 'Offline'}</span>
                            </div>
                            <div className="dropdown">
                                <button className="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {rider?.avatar ? (
                                        <img src={rider.avatar} alt="" className="rounded-circle" width="32" height="32" />
                                    ) : (
                                        <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style={{ width: 32, height: 32 }}>
                                            {rider?.name?.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <span className="fw-medium d-none d-sm-inline">{rider?.name}</span>
                                </button>
                                <ul className="dropdown-menu dropdown-menu-end">
                                    <li><a className="dropdown-item" href="/rider/profile">Profile</a></li>
                                    <li><a className="dropdown-item" href="/rider/earnings">Earnings</a></li>
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
