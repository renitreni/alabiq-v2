import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import api from '@/lib/axios';
import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';

interface MenuItem {
    id: number;
    name: string;
    description?: string | null;
    price: number;
    branch_price?: number | null;
    image?: string | null;
    category?: string | null;
}

interface MenuCategory {
    id: string;
    name: string;
    items: MenuItem[];
}

interface Branch {
    id: string;
    name: string;
    delivery_fee: number;
    minimum_order: number;
    estimated_delivery_time: number;
    is_open: boolean;
    address?: string | null;
    opens_at?: string | null;
    closes_at?: string | null;
}

interface RestaurantData {
    id: string;
    name: string;
    slug: string;
    description: string;
    logo?: string | null;
    cover_image?: string | null;
    phone?: string | null;
    address?: string | null;
    district?: string | null;
    city?: string | null;
    estimated_delivery_time: number;
    cuisines: Array<{ id: string; name: string }>;
}

interface PageProps {
    restaurant: RestaurantData;
    branch: Branch | null;
    categories: MenuCategory[];
}

const money = (value: number | null | undefined) => `${Number(value || 0).toFixed(2)} SAR`;

export default function RestaurantShow() {
    const { restaurant, branch, categories } = usePage().props as unknown as PageProps;
    const menuSection = useRef<HTMLHeadingElement>(null);
    const [cart, setCart] = useState({ item_count: 0, subtotal: 0 });
    const [added, setAdded] = useState<number | null>(null);

    useEffect(() => {
        const loadCart = async () => {
            try {
                const { data } = await api.get('/marketplace/cart');
                const cartData = data?.data ?? data ?? {};
                setCart({
                    item_count: cartData.item_count ?? 0,
                    subtotal: cartData.subtotal ?? 0,
                });
            } catch (error) {
                console.error('Failed to load cart', error);
            }
        };

        loadCart();
    }, []);

    const addToCart = useCallback(
        async (menuItemId: number) => {
            setAdded(menuItemId);

            try {
                const { data } = await api.post('/marketplace/cart', {
                    menu_item_id: menuItemId,
                    quantity: 1,
                });

                const cartData = data.data ?? data;
                setCart({
                    item_count: cartData.item_count ?? 0,
                    subtotal: cartData.subtotal ?? 0,
                });

                setTimeout(() => setAdded(null), 1200);
            } catch (error) {
                setAdded(null);
                console.error('Failed to add item to cart', error);
            }
        },
        []
    );

    const deliveryFee = branch?.delivery_fee ?? 0;
    const total = cart.subtotal + deliveryFee;
    const hasItems = cart.item_count > 0;

    return (
        <GuestLayout title={`${restaurant.name} - Alabiq`} description={restaurant.description}>
            <div className="container px-4 py-4">
                <div className="row mb-4">
                    <div className="col-12">
                        <Card variant="outlined" className="overflow-hidden">
                            <div className="row g-0">
                                <div className="col-md-4">
                                    <img
                                        src={restaurant.cover_image ?? 'https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=400&h=300&fit=crop'}
                                        alt={restaurant.name}
                                        className="w-100 h-100"
                                        style={{ objectFit: 'cover' }}
                                    />
                                </div>
                                <div className="col-md-8">
                                    <div className="p-4 h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div className="d-flex flex-wrap gap-2 mb-2">
                                                {restaurant.cuisines.map((cuisine) => (
                                                    <span key={cuisine.id} className="badge bg-soft-primary text-primary">
                                                        {cuisine.name}
                                                    </span>
                                                ))}
                                                {branch && (
                                                    <>
                                                        <span className={`badge bg-${branch.is_open ? 'success' : 'danger'}`}>
                                                            {branch.is_open ? 'Open now' : 'Closed'}
                                                        </span>
                                                        <span className="badge bg-light text-dark">⏱ {branch.estimated_delivery_time ?? restaurant.estimated_delivery_time} min</span>
                                                        <span className="badge bg-light text-dark">🚚 {money(branch.delivery_fee)} delivery</span>
                                                    </>
                                                )}
                                            </div>
                                            <h1 className="fw-bold text-1000 mb-2">{restaurant.name}</h1>
                                            {branch?.address && (
                                                <div className="d-flex flex-wrap gap-3 text-700 small mb-3">
                                                    <span>📍 {branch.address}</span>
                                                </div>
                                            )}
                                            <p className="text-700">{restaurant.description || 'No description available.'}</p>
                                        </div>
                                        <div className="d-flex gap-2 flex-wrap">
                                            <Button variant="primary" size="lg" className="flex-1" as="a" href="/customer/cart">
                                                Order Now
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="lg"
                                                onClick={() => menuSection.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                                            >
                                                View Menu
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>

                {categories.length ? (
                    <div className="row">
                        <div className="col-lg-8">
                            <div className="mb-4">
                                <h3 className="fw-bold mb-3" ref={menuSection}>
                                    Menu
                                </h3>
                                <div className="row g-4">
                                    {categories.flatMap((category) =>
                                        category.items.map((item) => (
                                            <div key={item.id} className="col-md-6">
                                                <Card variant="outlined" hoverable>
                                                    <CardBody className="d-flex gap-3">
                                                        {item.image && (
                                                            <img
                                                                src={item.image}
                                                                alt=""
                                                                className="rounded"
                                                                style={{ width: 80, height: 80, objectFit: 'cover', flexShrink: 0 }}
                                                            />
                                                        )}
                                                        <div className="flex-1 d-flex flex-column">
                                                            <div className="d-flex justify-content-between align-items-start mb-1">
                                                                <h6 className="fw-bold mb-0">{item.name}</h6>
                                                                <span className="fw-bold text-primary">{money(item.branch_price ?? item.price)}</span>
                                                            </div>
                                                            {item.description && (
                                                                <p className="text-700 small mb-2">{item.description}</p>
                                                            )}
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                loading={added === item.id}
                                                                disabled={added === item.id}
                                                                onClick={() => addToCart(item.id)}
                                                            >
                                                                {added === item.id ? 'Adding…' : 'Add'}
                                                            </Button>
                                                        </div>
                                                    </CardBody>
                                                </Card>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>
                        </div>
                        <div className="col-lg-4">
                            <Card variant="outlined" className="sticky-top" style={{ top: 100 }}>
                                <CardBody>
                                    <h5 className="fw-bold mb-3">Your Order</h5>
                                    {hasItems ? (
                                        <>
                                            <div className="mb-3 p-3 bg-100 rounded text-center text-500">
                                                {cart.item_count} item{cart.item_count !== 1 ? 's' : ''} in your cart
                                            </div>
                                            <div className="border-top pt-3">
                                                <div className="d-flex justify-content-between mb-2">
                                                    <span className="text-700">Subtotal</span>
                                                    <span className="fw-bold">{money(cart.subtotal)}</span>
                                                </div>
                                                <div className="d-flex justify-content-between mb-2">
                                                    <span className="text-700">Delivery Fee</span>
                                                    <span className="fw-bold">{money(deliveryFee)}</span>
                                                </div>
                                                <hr />
                                                <div className="d-flex justify-content-between mb-3">
                                                    <span className="fw-bold">Total</span>
                                                    <span className="fw-bold text-primary fs-5">{money(total)}</span>
                                                </div>
                                                <Button variant="primary" className="w-100" as="a" href="/customer/cart">
                                                    Proceed to Checkout
                                                </Button>
                                            </div>
                                        </>
                                    ) : (
                                        <>
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
                                                <span className="fw-bold">{money(deliveryFee)}</span>
                                            </div>
                                            <hr />
                                            <div className="d-flex justify-content-between mb-3">
                                                <span className="fw-bold">Total</span>
                                                <span className="fw-bold text-primary fs-5">{money(total)}</span>
                                            </div>
                                            <Button variant="primary" className="w-100" disabled>
                                                Proceed to Checkout
                                            </Button>
                                        </div>
                                        </>
                                    )}
                                </CardBody>
                            </Card>
                        </div>
                    </div>
                ) : (
                    <EmptyState
                        title="No menu items available"
                        description="This restaurant has no items to order at the moment."
                        icon="🚫"
                    />
                )}
            </div>
        </GuestLayout>
    );
}
