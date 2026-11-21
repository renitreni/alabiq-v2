import React from 'react';
import { Head } from '@inertiajs/react';
import { Navbar } from '@/components/ui/Navbar';

interface GuestLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export const GuestLayout: React.FC<GuestLayoutProps> = ({
    children,
    title = 'Alabiq - Multi-Restaurant Food Delivery Marketplace',
    description = 'Discover the best restaurants near you. Order food online for delivery or pickup.',
}) => {
    return (
        <>
            <Head>
                <title>{title}</title>
                <meta name="description" content={description} />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <link rel="icon" href="/favicon.ico" />
                <link rel="apple-touch-icon" href="/images/alabiq-icon.png" />
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
            </Head>

            <div className="min-h-screen bg-[var(--alabiq-gray-50)] flex flex-col">
                <Navbar
                    logo="/images/alabiq-logo-full.png"
                    logoAlt="Alabiq"
                    links={[
                        { label: 'Restaurants', href: '/restaurants' },
                        { label: 'How it Works', href: '/how-it-works' },
                        { label: 'About', href: '/about' },
                    ]}
                    userMenu={
                        <div className="d-flex align-items-center gap-2">
                            <a href="/login" className="btn btn-outline-primary btn-sm">Log In</a>
                            <a href="/register" className="btn btn-primary btn-sm">Sign Up</a>
                        </div>
                    }
                />

                <main className="flex-1">{children}</main>

                <Footer />
            </div>
        </>
    );
};

const Footer: React.FC = () => (
    <footer className="bg-[var(--alabiq-gray-900)] text-white py-5 mt-auto">
        <div className="container px-4">
            <div className="row">
                <div className="col-md-4 mb-4 mb-md-0">
                    <img src="/images/alabiq-logo-dark.png" alt="Alabiq" height="36" className="mb-3" />
                    <p className="text-[var(--alabiq-gray-400)] small">
                        Your favorite food delivered fast. Discover local restaurants and order with ease.
                    </p>
                </div>
                <div className="col-md-2 mb-4 mb-md-0">
                    <h6 className="fw-bold mb-3">Company</h6>
                    <ul className="list-unstyled">
                        <li className="mb-2"><a href="/about" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">About Us</a></li>
                        <li className="mb-2"><a href="/careers" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Careers</a></li>
                        <li className="mb-2"><a href="/press" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Press</a></li>
                    </ul>
                </div>
                <div className="col-md-2 mb-4 mb-md-0">
                    <h6 className="fw-bold mb-3">Support</h6>
                    <ul className="list-unstyled">
                        <li className="mb-2"><a href="/help" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Help Center</a></li>
                        <li className="mb-2"><a href="/contact" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Contact Us</a></li>
                        <li className="mb-2"><a href="/faq" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div className="col-md-2 mb-4 mb-md-0">
                    <h6 className="fw-bold mb-3">Legal</h6>
                    <ul className="list-unstyled">
                        <li className="mb-2"><a href="/privacy" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Privacy Policy</a></li>
                        <li className="mb-2"><a href="/terms" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Terms of Service</a></li>
                        <li className="mb-2"><a href="/cookies" className="text-[var(--alabiq-gray-400)] text-decoration-none small hover:text-white">Cookie Policy</a></li>
                    </ul>
                </div>
                <div className="col-md-2">
                    <h6 className="fw-bold mb-3">Download App</h6>
                    <div className="d-flex gap-2">
                        <a href="#" className="btn btn-outline-light btn-sm px-3" aria-label="Download on App Store">App Store</a>
                        <a href="#" className="btn btn-outline-light btn-sm px-3" aria-label="Get it on Google Play">Google Play</a>
                    </div>
                </div>
            </div>
            <hr className="border-[var(--alabiq-gray-800)] my-4" />
            <div className="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <p className="text-[var(--alabiq-gray-500)] small mb-0">&copy; 2025 Alabiq. All rights reserved.</p>
                <div className="d-flex gap-4">
                    <a href="#" className="text-[var(--alabiq-gray-500)] hover:text-white" aria-label="Facebook"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
                    <a href="#" className="text-[var(--alabiq-gray-500)] hover:text-white" aria-label="Twitter"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg></a>
                    <a href="#" className="text-[var(--alabiq-gray-500)] hover:text-white" aria-label="Instagram"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
                </div>
            </div>
        </div>
    </footer>
);
