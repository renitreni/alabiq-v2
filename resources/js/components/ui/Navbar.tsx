import React from 'react';
import { Link } from 'react-router-dom';

interface NavbarProps {
    logo?: string;
    logoAlt?: string;
    links?: Array<{ label: string; href: string }>;
    userMenu?: React.ReactNode;
    className?: string;
}

export const Navbar: React.FC<NavbarProps> = ({
    logo = '/images/alabiq-logo-full.png',
    logoAlt = 'Alabiq',
    links = [],
    userMenu,
    className = '',
}) => {
    return (
        <nav className={`navbar navbar-expand-lg bg-white shadow-[var(--alabiq-shadow-sm)] ${className}`} role="navigation" aria-label="Main navigation">
            <div className="container-fluid px-4">
                <Link to="/" className="navbar-brand" aria-label={`${logoAlt} - Home`}>
                    <img src={logo} alt={logoAlt} height="40" className="me-2" />
                    <span className="fw-bold text-[var(--alabiq-gray-900)]">{logoAlt}</span>
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

                <div className="collapse navbar-collapse" id="navbarNav">
                    <ul className="navbar-nav me-auto mb-2 mb-lg-0">
                        {links.map((link, index) => (
                            <li key={index} className="nav-item">
                                <Link to={link.href} className="nav-link fw-medium text-[var(--alabiq-gray-700)] hover:text-[var(--alabiq-primary)] px-3 py-2">
                                    {link.label}
                                </Link>
                            </li>
                        ))}
                    </ul>

                    <div className="d-flex align-items-center gap-3">
                        {userMenu}
                    </div>
                </div>
            </div>
        </nav>
    );
};