import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CustomerLayout } from '@/components/layout/CustomerLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface CartItem {
    menu_item_id: number;
    name: string;
    final_price: number;
    quantity: number;
    image?: string;
    category?: string;
    options?: Array<{ option_name: string; value_name: string }>;
}

interface CartPayload {
    items: CartItem[];
    subtotal: number;
    item_count: number;
}

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function Cart() {
    const { auth, restaurant } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
        restaurant: { menuUrl: string | null };
    };
    const [cart, setCart] = useState<CartPayload>({ items: [], subtotal: 0, item_count: 0 });
    const [loading, setLoading] = useState(true);
    const [clearing, setClearing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        api.get('/marketplace/cart')
            .then(({ data }) => setCart(data?.data ?? { items: [], subtotal: 0, item_count: 0 }))
            .catch(() => setError('Could not load your cart.'))
            .finally(() => setLoading(false));
    }, []);

    const clearCart = async () => {
        setClearing(true);
        setError(null);

        try {
            await api.delete('/marketplace/cart');
            setCart({ items: [], subtotal: 0, item_count: 0 });
        } catch {
            setError('Could not clear the cart. Please try again.');
        } finally {
            setClearing(false);
        }
    };

    return (
        <CustomerLayout title="Cart - Alabiq" user={auth?.user}>
            <PageScaffold
                eyebrow="Cart"
                title="Your Cart"
                description="Review your selected items before checkout."
                actions={cart.items.length > 0 ? (
                    <Button variant="outline" size="sm" loading={clearing} onClick={clearCart}>Clear Cart</Button>
                ) : undefined}
            >
                {error && <div className="alert alert-danger">{error}</div>}

                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading your cart…</CardBody>
                    </Card>
                ) : cart.items.length ? (
                    <div className="row g-4">
                        <div className="col-lg-8">
                            <Card>
                                <CardBody className="p-0">
                                    <div className="list-group list-group-flush">
                                        {cart.items.map((item) => (
                                            <div key={`${item.menu_item_id}-${item.name}`} className="list-group-item p-3 p-md-4">
                                                <div className="d-flex justify-content-between gap-3">
                                                    <div>
                                                        <div className="fw-semibold text-1000">{item.name}</div>
                                                        {item.category && <div className="small text-700">{item.category}</div>}
                                                        {item.options?.length ? (
                                                            <div className="small text-700 mt-1">
                                                                {item.options.map((option) => `${option.option_name}: ${option.value_name}`).join(' · ')}
                                                            </div>
                                                        ) : null}
                                                        <div className="small text-700 mt-1">Quantity: {item.quantity}</div>
                                                    </div>
                                                    <div className="fw-bold text-1000 text-nowrap">{money(item.final_price * item.quantity)}</div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardBody>
                            </Card>
                        </div>
                        <div className="col-lg-4">
                            <Card variant="elevated">
                                <CardBody className="p-4">
                                    <h2 className="fs-5 fw-bold text-1000 mb-3">Summary</h2>
                                    <div className="d-flex justify-content-between text-700 mb-2">
                                        <span>Items</span>
                                        <span>{cart.item_count}</span>
                                    </div>
                                    <div className="d-flex justify-content-between fw-bold text-1000 border-top pt-3">
                                        <span>Subtotal</span>
                                        <span>{money(cart.subtotal)}</span>
                                    </div>
                                    <Button variant="primary" className="w-100 mt-4" as="a" href="/customer/checkout">Proceed to Checkout</Button>
                                </CardBody>
                            </Card>
                        </div>
                    </div>
                ) : (
                    <EmptyState
                        title="Your cart is empty"
                        description="Add items from a restaurant to start checkout."
                        icon="🛒"
                        action={<Button variant="primary" size="sm" as="a" href={restaurant.menuUrl ?? '/customer/cart'}>Order Now</Button>}
                    />
                )}
            </PageScaffold>
        </CustomerLayout>
    );
}
