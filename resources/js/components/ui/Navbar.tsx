import React from 'react';
import { Link } from 'react-router-dom';
import { usePage } from '@inertiajs/react';

interface NavbarProps {
    logo?: string;
    logoAlt?: string;
    links?: Array<{ label: string; href: string }>;
    userMenu?: React.ReactNode;
    user?: { name: string; avatar?: string | null } | null;
    className?: string;
}

type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    avatar?: string | null;
};

const initials = (name?: string | null): string => (name ? name.charAt(0).toUpperCase() : '?');

const GuestMenu: React.FC = () => (
    <div className="d-flex align-items-center gap-2">
        <a href="/login" className="btn btn-outline-danger btn-sm">Log In</a>
        <a href="/register" className="btn btn-danger btn-sm">Sign Up</a>
    </div>
);

const CustomerMenu: React.FC<{ user: NonNullable<NavbarProps['user']> }> = ({ user }) => (
    <div className="d-flex align-items-center gap-3">
        <div className="dropdown">
            <button className="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {user.avatar ? (
                    <img src={user.avatar} alt="" className="rounded-circle" width="32" height="32" />
                ) : (
                    <div className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style={{ width: 32, height: 32 }}>
                        {initials(user.name)}
                    </div>
                )}
                <span className="fw-medium d-none d-sm-inline">{user.name}</span>
            </button>
            <ul className="dropdown-menu dropdown-menu-end">
                <li><a className="dropdown-item" href="/customer/profile">Profile</a></li>
                <li><a className="dropdown-item" href="/customer/orders">My Orders</a></li>
                <li><a className="dropdown-item" href="/customer/addresses">Addresses</a></li>
                <li><hr className="dropdown-divider" /></li>
                <li>
                    <form method="POST" action="/logout">
                        <button type="submit" className="dropdown-item text-danger">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
);

export const Navbar: React.FC<NavbarProps> = ({
    logo = '/images/alabiq-logo-full.png',
    logoAlt = 'Alabiq',
    links = [],
    userMenu,
    user: userProp,
    className = '',
}) => {
    const { auth } = usePage().props as unknown as {
        auth: { user: AuthUser | null };
    };

    const user = userProp ?? auth?.user ?? null;

    return (
        <nav className={`navbar navbar-expand-lg navbar-light bg-white py-3 d-block ${className}`.trim()} role="navigation" aria-label="Main navigation">
            <div className="container">
                <Link to="/" className="navbar-brand d-flex align-items-center" aria-label={`${logoAlt} - Home`}>
                    <img src={logo} alt={logoAlt} height="45" className="me-2" />
                    <span className="fw-bold text-1000 d-none d-sm-inline">{logoAlt}</span>
                </Link>

                <button
                    className="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <span className="navbar-toggler-icon" />
                </button>

                <div className="collapse navbar-collapse border-top border-lg-0 mt-4 mt-lg-0" id="navbarNav">
                    <ul className="navbar-nav ms-auto pt-2 pt-lg-0 font-base">
                        {links.map((link, index) => (
                            <li key={index} className="nav-item px-2">
                                <Link to={link.href} className="nav-link">
                                    {link.label}
                                </Link>
                            </li>
                        ))}
                    </ul>

                    <div className="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                        {userMenu ?? (user ? <CustomerMenu user={user} /> : <GuestMenu />)}
                    </div>
                </div>
            </div>
        </nav>
    );
};