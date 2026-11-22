import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';

const heroBackground = {
    backgroundImage: 'url(/courier-v1.0.0/build/assets/img/gallery/hero-header-bg.png)',
    backgroundPosition: 'top center',
    backgroundSize: 'cover',
};

/**
 * Pre-setup fallback landing — only reachable when the deployment's
 * restaurant has not been created yet. Once setup completes, `/` redirects
 * straight to the restaurant storefront.
 */
export default function Home() {
    return (
        <GuestLayout
            title="Alabiq - Order Food Online"
            description="Online food ordering for delivery or pickup."
        >
            <section className="py-7 bg-100 position-relative" id="home">
                <div className="bg-holder bg-size" style={heroBackground} />
                <div className="container">
                    <div className="row align-items-center">
                        <div className="col-lg-6 col-xl-5 text-center text-lg-start py-5">
                            <h5 className="text-danger mb-3">COMING SOON</h5>
                            <h1 className="fw-bolder fs-4 fs-xxl-6 mb-3">
                                Online Ordering,<br />
                                <span className="text-primary">Almost Ready</span>
                            </h1>
                            <p className="fs-1 text-700 mb-4">
                                This restaurant is being set up on Alabiq. Check back soon to browse the menu and order for
                                delivery or pickup.
                            </p>
                            <div className="d-flex gap-3 justify-content-center justify-content-lg-start">
                                <Button variant="primary" size="lg" as="a" href="/login">Log In</Button>
                                <Button variant="outline" size="lg" as="a" href="/register">Create an Account</Button>
                            </div>
                        </div>
                        <div className="col-lg-6 col-xl-7 d-none d-lg-block text-end">
                            <img
                                className="img-fluid w-100"
                                src="/courier-v1.0.0/build/assets/img/illustrations/hero.png"
                                alt="Food delivery illustration"
                            />
                        </div>
                    </div>
                </div>
            </section>
        </GuestLayout>
    );
}
