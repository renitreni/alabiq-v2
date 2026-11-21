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

const steps = [
    { title: 'Enter Your Address', description: 'Tell us where you are so we can show nearby restaurants.' },
    { title: 'Choose & Customize', description: 'Browse menus, customize your order, and add special instructions.' },
    { title: 'Track & Enjoy', description: 'Watch your rider in real-time and enjoy your meal!' },
];

const heroBackground = {
    backgroundImage: 'url(/courier-v1.0.0/build/assets/img/gallery/hero-header-bg.png)',
    backgroundPosition: 'top center',
    backgroundSize: 'cover',
};

export default function Home() {
    return (
        <GuestLayout
            title="Alabiq - Multi-Restaurant Food Delivery Marketplace"
            description="Discover the best restaurants near you. Order food online for delivery or pickup."
        >
            <section className="py-7 bg-100 position-relative" id="home">
                <div className="bg-holder bg-size" style={heroBackground} />
                <div className="container">
                    <div className="row align-items-center">
                        <div className="col-lg-6 col-xl-5 text-center text-lg-start py-5">
                            <h5 className="text-danger mb-3">FOOD DELIVERY, SIMPLIFIED</h5>
                            <h1 className="fw-bolder fs-4 fs-xxl-6 mb-3">
                                Your Favorite Food,<br />
                                <span className="text-primary">Delivered Fast</span>
                            </h1>
                            <p className="fs-1 text-700 mb-4">
                                Discover the best local restaurants, order with ease, and track your delivery in real-time. Join thousands of happy customers.
                            </p>
                            <div className="search-bar animate-fade-in">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Enter your delivery address or search for restaurants..."
                                    aria-label="Search restaurants"
                                />
                                <button className="btn btn-danger btn-search" type="button">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="me-1">
                                        <circle cx="11" cy="11" r="8" />
                                        <path d="M21 21l-4.35-4.35" />
                                    </svg>
                                    Find Food
                                </button>
                            </div>
                            <div className="mt-3 text-600 small d-flex flex-column flex-sm-row justify-content-center justify-content-lg-start">
                                <span className="me-sm-3 mb-1 mb-sm-0">📍 Delivering to Riyadh, Jeddah, Dammam &amp; more</span>
                                <span>⏱️ Average delivery: 32 minutes</span>
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

            <section className="py-7 bg-white" id="services">
                <div className="container">
                    <div className="row justify-content-center">
                        <div className="col-md-8 col-lg-6 text-center mb-5">
                            <h5 className="text-danger">WHY ALABIQ</h5>
                            <h2 className="fw-bolder">Why Choose Alabiq?</h2>
                            <p className="text-700 mb-0">We make food ordering simple, fast, and reliable for everyone.</p>
                        </div>
                    </div>
                    <div className="row g-4">
                        {features.map((feature, index) => (
                            <div key={feature.title} className="col-md-6 col-lg-3">
                                <Card
                                    variant="outlined"
                                    hoverable
                                    className="h-100 card-span animate-slide-up"
                                    style={{ animationDelay: `${index * 100}ms` }}
                                >
                                    <CardBody className="text-center p-4">
                                        <div
                                            className="d-inline-flex align-items-center justify-content-center bg-soft-primary text-primary rounded-circle mb-3"
                                            style={{ width: 64, height: 64 }}
                                            aria-hidden="true"
                                        >
                                            <span className="fs-3">{feature.icon}</span>
                                        </div>
                                        <h5 className="fw-bold text-1000 mb-2">{feature.title}</h5>
                                        <p className="text-700 small mb-0">{feature.description}</p>
                                    </CardBody>
                                </Card>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="py-7 bg-100">
                <div className="container">
                    <div className="row justify-content-center">
                        <div className="col-md-8 col-lg-6 text-center mb-5">
                            <h5 className="text-danger">POPULAR CUISINES</h5>
                            <h2 className="fw-bolder">Popular Cuisines</h2>
                            <p className="text-700 mb-0">Explore your favorite food categories from top-rated restaurants.</p>
                        </div>
                    </div>
                    <div className="row g-3 justify-content-center">
                        {popularCuisines.map((cuisine) => (
                            <div key={cuisine.name} className="col-6 col-md-4 col-lg-2">
                                <Link to={`/restaurants?cuisine=${cuisine.name.toLowerCase()}`} className="text-decoration-none">
                                    <Card variant="outlined" hoverable className="h-100 text-center animate-fade-in">
                                        <CardBody className="py-4">
                                            <span className="fs-2" aria-hidden="true">{cuisine.icon}</span>
                                            <h6 className="fw-bold text-1000 mt-2 mb-1">{cuisine.name}</h6>
                                            <p className="text-700 small mb-0">{cuisine.count} restaurants</p>
                                        </CardBody>
                                    </Card>
                                </Link>
                            </div>
                        ))}
                    </div>
                    <div className="text-center mt-4">
                        <Button variant="primary" size="lg" as="a" href="/restaurants">
                            View All Cuisines
                        </Button>
                    </div>
                </div>
            </section>

            <section className="py-7 bg-white" id="how-it-works">
                <div className="container">
                    <div className="row justify-content-center">
                        <div className="col-md-8 col-lg-6 text-center mb-5">
                            <h5 className="text-danger">HOW IT WORKS</h5>
                            <h2 className="fw-bolder">How It Works</h2>
                            <p className="text-700 mb-0">Order your favorite food in just a few taps.</p>
                        </div>
                    </div>
                    <div className="row g-4">
                        {steps.map((step, index) => (
                            <div key={step.title} className="col-md-4">
                                <Card variant="outlined" className="h-100 text-center card-span">
                                    <CardBody className="p-4">
                                        <div
                                            className="d-inline-flex align-items-center justify-content-center bg-soft-primary text-primary rounded-circle mb-3"
                                            style={{ width: 56, height: 56 }}
                                        >
                                            <span className="fw-bold fs-4">{index + 1}</span>
                                        </div>
                                        <h5 className="fw-bold text-1000 mb-2">{step.title}</h5>
                                        <p className="text-700 small mb-0">{step.description}</p>
                                    </CardBody>
                                </Card>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="py-7 bg-1000 text-white">
                <div className="container text-center">
                    <h5 className="text-primary">READY TO ORDER?</h5>
                    <h2 className="fw-bolder text-white mb-3">Ready to Order?</h2>
                    <p className="text-500 mb-4 mx-auto" style={{ maxWidth: 600 }}>
                        Join thousands of customers who trust Alabiq for fast, reliable food delivery.
                    </p>
                    <div className="d-flex gap-3 justify-content-center flex-wrap">
                        <Button variant="primary" size="lg" as="a" href="/restaurants">
                            Browse Restaurants
                        </Button>
                        <Button variant="outline" size="lg" as="a" href="/register" className="text-white">
                            Create Free Account
                        </Button>
                    </div>
                </div>
            </section>
        </GuestLayout>
    );
}
