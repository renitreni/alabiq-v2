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
    title = 'Alabiq - Order Food Online',
    description = 'Order your favorite food online for delivery or pickup.',
}) => {
    return (
        <>
            <Head>
                <title>{title}</title>
                <meta name="description" content={description} />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <link rel="icon" href="/favicon.ico" />
                <link rel="apple-touch-icon" href="/images/alabiq-icon.png" />
            </Head>

            <div className="min-vh-100 bg-100 d-flex flex-column">
                <Navbar
                    logo="/images/alabiq-logo-full.png"
                    logoAlt="Alabiq"
                    links={[
                        { label: 'Menu', href: '/' },
                        { label: 'How it Works', href: '/how-it-works' },
                        { label: 'About', href: '/about' },
                    ]}
                />

                <main className="flex-1">{children}</main>

                <Footer />
                <FooterBottom />
            </div>
        </>
    );
};

const Footer: React.FC = () => (
    <footer className="bg-900 pt-5 text-white mt-auto">
        <div className="container">
            <div className="row">
                <div className="col-12 col-lg-5 mb-4">
                    <div className="d-flex align-items-center mb-3">
                        <img src="/images/alabiq-icon.png" alt="" height="45" />
                        <span className="fs-4 fw-bold ms-2">Alabiq</span>
                    </div>
                    <p className="text-500">
                        Your favorite food delivered fast. Discover local restaurants and order with ease.
                    </p>
                </div>
                <div className="col-6 col-sm-4 col-lg-2 mb-4">
                    <h5 className="fw-bold mb-3 text-light">Company</h5>
                    <ul className="list-unstyled mb-0">
                        <li className="mb-2"><a href="/about" className="text-500">About Us</a></li>
                        <li className="mb-2"><a href="/careers" className="text-500">Careers</a></li>
                        <li><a href="/press" className="text-500">Press</a></li>
                    </ul>
                </div>
                <div className="col-6 col-sm-4 col-lg-2 mb-4">
                    <h5 className="fw-bold mb-3 text-light">Support</h5>
                    <ul className="list-unstyled mb-0">
                        <li className="mb-2"><a href="/help" className="text-500">Help Center</a></li>
                        <li className="mb-2"><a href="/contact" className="text-500">Contact Us</a></li>
                        <li><a href="/faq" className="text-500">FAQ</a></li>
                    </ul>
                </div>
                <div className="col-6 col-sm-4 col-lg-2 mb-4">
                    <h5 className="fw-bold mb-3 text-light">Legal</h5>
                    <ul className="list-unstyled mb-0">
                        <li className="mb-2"><a href="/privacy" className="text-500">Privacy Policy</a></li>
                        <li className="mb-2"><a href="/terms" className="text-500">Terms of Service</a></li>
                        <li><a href="/cookies" className="text-500">Cookie Policy</a></li>
                    </ul>
                </div>
                <div className="col-6 col-sm-4 col-lg-2 mb-4">
                    <h5 className="fw-bold mb-3 text-light">Download App</h5>
                    <div className="d-flex flex-column gap-2">
                        <a href="#" className="btn btn-outline-light btn-sm">App Store</a>
                        <a href="#" className="btn btn-outline-light btn-sm">Google Play</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
);

export const FooterBottom: React.FC = () => (
    <section className="bg-1000 py-4">
        <div className="container">
            <div className="row justify-content-between align-items-center">
                <div className="col-12 col-md-auto text-center text-md-start">
                    <p className="fs--1 text-200 mb-0">&copy; 2026 Alabiq. All rights reserved.</p>
                </div>
                <div className="col-12 col-md-auto">
                    <div className="d-flex justify-content-center gap-4">
                        <a href="#" className="text-200" aria-label="Facebook"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" /></svg></a>
                        <a href="#" className="text-200" aria-label="Twitter"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z" /></svg></a>
                        <a href="#" className="text-200" aria-label="Instagram"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" /><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" /><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" /></svg></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
);
