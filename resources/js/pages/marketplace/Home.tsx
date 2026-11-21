import { Link } from 'react-router-dom';
import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

const features = [
    { icon: '🍽️', title: 'Discover Restaurants', description: 'Browse thousands of local restaurants with menus, reviews, and ratings.' },
    { icon: '🚀', title: 'Fast Delivery', description: 'Real-time tracking with live rider locations and accurate ETAs.' },
    { icon: '💳', title: 'Secure Payments', description: 'Multiple payment options including cards, Apple Pay, and cash on delivery.' },
    { icon: '⭐', title: 'Reviews & Ratings', description: 'Read authentic reviews from other customers before ordering.' },
];

const popularCuisines = [
    { name: 'Burgers', count: 142, icon: '🍔' },
    { name: 'Pizza', count: 98, icon: '🍕' },
    { name: 'Asian', count: 87, icon: '🥢' },
    { name: 'Healthy', count: 76, icon: '🥗' },
    { name: 'Desserts', count: 65, icon: '🍰' },
    { name: 'Coffee', count: 54, icon: '☕' },
];

export default function Home() {
    return (
        <GuestLayout
            title="Alabiq - Multi-Restaurant Food Delivery Marketplace"
            description="Discover the best restaurants near you. Order food online for delivery or pickup."
        >
            <section className="hero-section">
                <div className="container px-4">
                    <div className="row justify-content-center">
                        <div className="col-lg-8 text-center">
                            <div className="hero-content animate-slide-up">
                                <h1 className="hero-title">
                                    Your Favorite Food,{' '}
                                    <span className="text-[var(--alabiq-primary)]">Delivered Fast</span>
                                </h1>
                                <p className="hero-subtitle">
                                    Discover the best local restaurants, order with ease, and track your delivery in real-time.
                                    Join thousands of happy customers.
                                </p>
                                <div className="search-bar animate-fade-in">
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="Enter your delivery address or search for restaurants..."
                                        aria-label="Search restaurants"
                                    />
                                    <button className="btn btn-primary btn-search" type="button">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="me-1">
                                            <circle cx="11" cy="11" r="8" />
                                            <path d="M21 21l-4.35-4.35" />
                                        </svg>
                                        Find Food
                                    </button>
                                </div>
                                <div className="mt-4 text-[var(--alabiq-gray-500)] small">
                                    <span className="me-3">📍 Delivering to Riyadh, Jeddah, Dammam & more</span>
                                    <span>⏱️ Average delivery: 32 minutes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="py-5 bg-white">
                <div className="container px-4">
                    <div className="row justify-content-center text-center mb-5">
                        <div className="col-lg-8">
                            <h2 className="section-title">Why Choose Alabiq?</h2>
                            <p className="section-subtitle">
                                We make food ordering simple, fast, and reliable for everyone.
                            </p>
                        </div>
                    </div>

                    <div className="row g-4">
                        {features.map((feature, index) => (
                            <div key={index} className="col-md-6 col-lg-3">
                                <Card variant="outlined" hoverable className="h-100 animate-slide-up" style={{ animationDelay: `${index * 100}ms` }}>
                                    <CardBody className="text-center p-4">
                                        <div className="d-inline-flex align-items-center justify-content-center bg-[var(--alabiq-primary-light)] text-[var(--alabiq-primary)] rounded-circle mb-3" style={{ width: 64, height: 64 }} aria-hidden="true">
                                            <span style={{ fontSize: '1.5rem' }}>{feature.icon}</span>
                                        </div>
                                        <h5 className="fw-bold text-[var(--alabiq-gray-900)]">{feature.title}</h5>
                                        <p className="text-[var(--alabiq-gray-600)] small mt-2">{feature.description}</p>
                                    </CardBody>
                                </Card>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="py-5 bg-[var(--alabiq-gray-50)]">
                <div className="container px-4">
                    <div className="row justify-content-center text-center mb-5">
                        <div className="col-lg-8">
                            <h2 className="section-title">Popular Cuisines</h2>
                            <p className="section-subtitle">
                                Explore your favorite food categories from top-rated restaurants.
                            </p>
                        </div>
                    </div>

                    <div className="row g-3 justify-content-center">
                        {popularCuisines.map((cuisine) => (
                            <div key={cuisine.name} className="col-6 col-md-4 col-lg-2">
                                <Link to={`/restaurants?cuisine=${cuisine.name.toLowerCase()}`} className="text-decoration-none">
                                    <Card variant="outlined" hoverable className="h-100 text-center animate-fade-in">
                                        <CardBody className="py-4">
                                            <span style={{ fontSize: '2.5rem' }} aria-hidden="true">{cuisine.icon}</span>
                                            <h6 className="fw-bold text-[var(--alabiq-gray-900)] mt-2 mb-1">{cuisine.name}</h6>
                                            <p className="text-[var(--alabiq-gray-500)] small mb-0">{cuisine.count} restaurants</p>
                                        </CardBody>
                                    </Card>
                                </Link>
                            </div>
                        ))}
                    </div>

                    <div className="text-center mt-4">
                        <Button variant="outline" size="lg" as="a" href="/restaurants">
                            View All Cuisines
                        </Button>
                    </div>
                </div>
            </section>

            <section className="py-5 bg-white">
                <div className="container px-4">
                    <div className="row justify-content-center text-center mb-5">
                        <div className="col-lg-8">
                            <h2 className="section-title">How It Works</h2>
                            <p className="section-subtitle">
                                Order your favorite food in just a few taps.
                            </p>
                        </div>
                    </div>

                    <div className="row g-4">
                        <div className="col-md-4">
                            <Card variant="outlined" className="h-100 text-center">
                                <CardBody className="p-4">
                                    <div className="bg-[var(--alabiq-primary)] text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style={{ width: 48, height: 48 }}>
                                        <span className="fw-bold fs-4">1</span>
                                    </div>
                                    <h5 className="fw-bold">Enter Your Address</h5>
                                    <p className="text-[var(--alabiq-gray-600)] small">Tell us where you are so we can show nearby restaurants.</p>
                                </CardBody>
                            </Card>
                        </div>
                        <div className="col-md-4">
                            <Card variant="outlined" className="h-100 text-center">
                                <CardBody className="p-4">
                                    <div className="bg-[var(--alabiq-primary)] text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style={{ width: 48, height: 48 }}>
                                        <span className="fw-bold fs-4">2</span>
                                    </div>
                                    <h5 className="fw-bold">Choose & Customize</h5>
                                    <p className="text-[var(--alabiq-gray-600)] small">Browse menus, customize your order, and add special instructions.</p>
                                </CardBody>
                            </Card>
                        </div>
                        <div className="col-md-4">
                            <Card variant="outlined" className="h-100 text-center">
                                <CardBody className="p-4">
                                    <div className="bg-[var(--alabiq-primary)] text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style={{ width: 48, height: 48 }}>
                                        <span className="fw-bold fs-4">3</span>
                                    </div>
                                    <h5 className="fw-bold">Track & Enjoy</h5>
                                    <p className="text-[var(--alabiq-gray-600)] small">Watch your rider in real-time and enjoy your meal!</p>
                                </CardBody>
                            </Card>
                        </div>
                    </div>
                </div>
            </section>

            <section className="py-5 bg-[var(--alabiq-gray-900)]">
                <div className="container px-4 text-center">
                    <h2 className="text-white fw-bold mb-3" style={{ fontSize: 'clamp(2rem, 4vw, 3rem)' }}>
                        Ready to Order?
                    </h2>
                    <p className="text-[var(--alabiq-gray-400)] mb-4" style={{ maxWidth: 600, margin: '0 auto 1.5rem' }}>
                        Join thousands of customers who trust Alabiq for fast, reliable food delivery.
                    </p>
                    <div className="d-flex gap-3 justify-content-center flex-wrap">
                        <Button variant="secondary" size="lg" as="a" href="/restaurants">
                            Browse Restaurants
                        </Button>
                        <Button variant="outline" size="lg" as="a" href="/register" style={{ borderColor: 'var(--alabiq-gray-400)', color: 'white' }}>
                            Create Free Account
                        </Button>
                    </div>
                </div>
            </section>
        </GuestLayout>
    );
}
