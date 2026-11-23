import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';

export default function RestaurantShow() {
    return (
        <GuestLayout title="Burger Palace - Alabiq" description="Order from Burger Palace - Best burgers in town!">
            <div className="container px-4 py-4">
                <div className="row mb-4">
                    <div className="col-12">
                        <Card variant="outlined" className="overflow-hidden">
                            <div className="row g-0">
                                <div className="col-md-4">
                                    <img src="https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=400&h=300&fit=crop" alt="Burger Palace" className="w-100 h-100" style={{ objectFit: 'cover' }} />
                                </div>
                                <div className="col-md-8">
                                    <div className="p-4 h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div className="d-flex flex-wrap gap-2 mb-2">
                                                <span className="badge bg-soft-primary text-primary">Burgers</span>
                                                <span className="badge bg-soft-primary text-primary">American</span>
                                                <span className="badge bg-soft-primary text-primary">Fast Food</span>
                                            </div>
                                            <h1 className="fw-bold text-1000 mb-2">Burger Palace</h1>
                                            <div className="d-flex flex-wrap gap-3 text-700 small mb-3">
                                                <span>⭐ 4.7 · 1,234 reviews</span>
                                                <span>🚚 25-35 min</span>
                                                <span>💰 10 SAR delivery</span>
                                                <span>📍 Olaya District, Riyadh</span>
                                            </div>
                                            <p className="text-700">The best burgers in town made with 100% Angus beef, fresh ingredients, and secret sauces. Family owned since 2015.</p>
                                        </div>
                                        <div className="d-flex gap-2 flex-wrap">
                                            <Button variant="primary" size="lg" className="flex-1" as="a" href="/customer/cart">Order Now</Button>
                                            <Button variant="outline" size="lg">View Menu</Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>

                <div className="row">
                    <div className="col-lg-8">
                        <div className="mb-4">
                            <h3 className="fw-bold mb-3">Popular Items</h3>
                            <div className="row g-4">
                                {[1, 2, 3, 4].map((i) => (
                                    <div key={i} className="col-md-6">
                                        <Card variant="outlined" hoverable>
                                            <CardBody className="d-flex gap-3">
                                                <img src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=120&h=120&fit=crop" alt="" className="rounded" style={{ width: 80, height: 80, objectFit: 'cover', flexShrink: 0 }} />
                                                <div className="flex-1">
                                                    <div className="d-flex justify-content-between align-items-start">
                                                        <h6 className="fw-bold mb-1">Classic Cheeseburger</h6>
                                                        <span className="fw-bold text-primary">28 SAR</span>
                                                    </div>
                                                    <p className="text-700 small mb-2">Angus beef, cheddar, lettuce, tomato, secret sauce</p>
                                                    <Button variant="outline" size="sm">Add</Button>
                                                </div>
                                            </CardBody>
                                        </Card>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="col-lg-4">
                        <Card variant="outlined" className="sticky-top" style={{ top: 100 }}>
                            <CardBody>
                                <h5 className="fw-bold mb-3">Your Order</h5>
                                <div className="mb-3 p-3 bg-100 rounded text-center text-500">
                                    Your cart is empty
                                </div>
                                <div className="border-top pt-3">
                                    <div className="d-flex justify-content-between mb-2">
                                        <span className="text-700">Subtotal</span>
                                        <span className="fw-bold">0 SAR</span>
                                    </div>
                                    <div className="d-flex justify-content-between mb-2">
                                        <span className="text-700">Delivery Fee</span>
                                        <span className="fw-bold">10 SAR</span>
                                    </div>
                                    <hr />
                                    <div className="d-flex justify-content-between mb-3">
                                        <span className="fw-bold">Total</span>
                                        <span className="fw-bold text-primary fs-5">10 SAR</span>
                                    </div>
                                    <Button variant="primary" className="w-100" disabled>Proceed to Checkout</Button>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
