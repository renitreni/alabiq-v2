import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';

const cuisines = ['Burgers', 'Pizza', 'Asian', 'Healthy', 'Desserts', 'Coffee'];
const deliveryFees = ['Free', 'Under 10 SAR', 'Under 20 SAR', 'Any'];
const ratings = ['4.5+', '4.0+', '3.5+', 'Any'];
const restaurantIds = [1, 2, 3, 4, 5, 6];

export default function Search() {
    return (
        <GuestLayout title="Search Restaurants - Alabiq" description="Search and filter restaurants by cuisine, location, rating, and more.">
            <div className="container px-4 py-5">
                <div className="row">
                    <div className="col-lg-3 mb-4">
                        <Card variant="outlined">
                            <CardBody>
                                <h5 className="fw-bold mb-4">Filters</h5>
                                <div className="mb-4">
                                    <label className="form-label fw-medium">Cuisine</label>
                                    <div className="d-flex flex-wrap gap-2">
                                        {cuisines.map((c) => (
                                            <button key={c} className="category-badge" type="button">{c}</button>
                                        ))}
                                    </div>
                                </div>
                                <div className="mb-4">
                                    <label className="form-label fw-medium">Delivery Fee</label>
                                    <div className="d-flex flex-wrap gap-2">
                                        {deliveryFees.map((f) => (
                                            <button key={f} className="category-badge" type="button">{f}</button>
                                        ))}
                                    </div>
                                </div>
                                <div className="mb-4">
                                    <label className="form-label fw-medium">Rating</label>
                                    <div className="d-flex flex-wrap gap-2">
                                        {ratings.map((r) => (
                                            <button key={r} className="category-badge" type="button">{r}</button>
                                        ))}
                                    </div>
                                </div>
                                <Button variant="outline" className="w-100" type="button">Clear All Filters</Button>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-9">
                        <div className="d-flex justify-content-between align-items-center mb-4">
                            <h2 className="fw-bold mb-0">Restaurants (<span className="text-[var(--alabiq-primary)]">124</span>)</h2>
                            <div className="d-flex gap-2">
                                <select className="form-select form-select-sm" style={{ width: 'auto' }}>
                                    <option>Relevance</option>
                                    <option>Rating</option>
                                    <option>Delivery Time</option>
                                    <option>Delivery Fee</option>
                                </select>
                            </div>
                        </div>
                        <div className="row g-4">
                            {restaurantIds.map((i) => (
                                <div key={i} className="col-md-6 col-lg-4">
                                    <Card variant="default" hoverable className="h-100 restaurant-card">
                                        <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=400&h=200&fit=crop" alt="" className="restaurant-card-image" />
                                        <div className="restaurant-card-content">
                                            <h5 className="restaurant-card-name">Restaurant {i}</h5>
                                            <p className="restaurant-card-cuisine">Burgers, American, Fast Food</p>
                                            <div className="restaurant-card-meta">
                                                <span className="restaurant-card-rating">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" className="me-1"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                                    4.{Math.floor(Math.random() * 5) + 5}
                                                </span>
                                                <span>🚚 {Math.floor(Math.random() * 20) + 15} min</span>
                                                <span>💰 {Math.floor(Math.random() * 15) + 5} SAR</span>
                                            </div>
                                        </div>
                                    </Card>
                                </div>
                            ))}
                        </div>
                        <nav className="mt-4" aria-label="Pagination">
                            <ul className="pagination justify-content-center">
                                <li className="page-item disabled"><span className="page-link">Previous</span></li>
                                <li className="page-item active"><span className="page-link">1</span></li>
                                <li className="page-item"><a className="page-link" href="#">2</a></li>
                                <li className="page-item"><a className="page-link" href="#">3</a></li>
                                <li className="page-item"><span className="page-link">...</span></li>
                                <li className="page-item"><a className="page-link" href="#">12</a></li>
                                <li className="page-item"><a className="page-link" href="#">Next</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
