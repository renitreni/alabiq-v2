import { FormEvent, useEffect, useState } from 'react';
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
}

interface CheckoutPayload {
    branch?: { name: string; delivery_fee: number; estimated_delivery_time: number } | null;
    items: CartItem[];
    subtotal: number;
    delivery_fee: number;
    total: number;
}

interface CustomerAddress {
    id: number;
    label?: string | null;
    formatted_address: string;
    is_default: boolean;
}

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function Checkout() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [checkout, setCheckout] = useState<CheckoutPayload | null>(null);
    const [addresses, setAddresses] = useState<CustomerAddress[]>([]);
    const [selectedAddressId, setSelectedAddressId] = useState<number | ''>('');
    const [instructions, setInstructions] = useState('');
    const [loading, setLoading] = useState(true);
    const [placing, setPlacing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [placedOrderId, setPlacedOrderId] = useState<number | null>(null);

    useEffect(() => {
        const loadCheckout = async () => {
            setLoading(true);
            setError(null);

            try {
                const [{ data: checkoutData }, { data: addressData }] = await Promise.all([
                    api.get('/marketplace/cart/checkout'),
                    api.get('/customer/addresses'),
                ]);

                setCheckout(checkoutData?.data ?? null);
                const savedAddresses = addressData?.data ?? [];
                setAddresses(savedAddresses);
                setSelectedAddressId(savedAddresses.find((address: CustomerAddress) => address.is_default)?.id ?? savedAddresses[0]?.id ?? '');
            } catch (e: any) {
                setError(e?.response?.data?.message ?? 'Could not prepare checkout. Please review your cart and addresses.');
            } finally {
                setLoading(false);
            }
        };

        loadCheckout();
    }, []);

    const placeOrder = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (typeof selectedAddressId !== 'number') {
            setError('Select a delivery address before placing your order.');
            return;
        }

        setPlacing(true);
        setError(null);

        try {
            const { data } = await api.post('/orders/checkout', {
                customer_address_id: selectedAddressId,
                payment_method: 'cash_on_delivery',
                delivery_instructions: instructions || undefined,
            });

            setPlacedOrderId(data?.data?.id ?? null);
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not place your order. Please try again.');
        } finally {
            setPlacing(false);
        }
    };

    return (
        <CustomerLayout title="Checkout - Alabiq" user={auth?.user}>
            <PageScaffold
                eyebrow="Checkout"
                title="Confirm Your Order"
                description="Choose a saved delivery address and place a cash-on-delivery order."
            >
                {error && <div className="alert alert-danger">{error}</div>}
                {placedOrderId && (
                    <div className="alert alert-success d-flex flex-column flex-md-row justify-content-between gap-2">
                        <span>Order #{placedOrderId} was placed successfully.</span>
                        <Button variant="primary" size="sm" as="a" href={`/customer/orders/${placedOrderId}`}>Track Order</Button>
                    </div>
                )}

                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Preparing checkout…</CardBody>
                    </Card>
                ) : checkout?.items?.length ? (
                    <form onSubmit={placeOrder} className="row g-4">
                        <div className="col-lg-7">
                            <Card className="h-100">
                                <CardBody className="p-4">
                                    <h2 className="fs-5 fw-bold text-1000 mb-3">Delivery Address</h2>
                                    {addresses.length ? (
                                        <div className="list-group mb-3">
                                            {addresses.map((address) => (
                                                <label key={address.id} className="list-group-item d-flex gap-2">
                                                    <input
                                                        type="radio"
                                                        name="delivery_address"
                                                        className="form-check-input mt-1"
                                                        checked={selectedAddressId === address.id}
                                                        onChange={() => setSelectedAddressId(address.id)}
                                                        required
                                                    />
                                                    <span>
                                                        <span className="fw-semibold text-1000">
                                                            {address.label ?? 'Saved address'} {address.is_default && <span className="badge bg-soft-primary text-primary ms-1">Default</span>}
                                                        </span>
                                                        <span className="d-block small text-700">{address.formatted_address}</span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    ) : (
                                        <EmptyState
                                            title="No delivery address saved"
                                            description="Add an address before placing this order."
                                            action={<Button variant="primary" size="sm" as="a" href="/customer/addresses">Add Address</Button>}
                                        />
                                    )}

                                    <div className="mb-3">
                                        <label htmlFor="instructions" className="form-label fw-semibold">Delivery instructions</label>
                                        <textarea
                                            id="instructions"
                                            className="form-control"
                                            rows={3}
                                            maxLength={500}
                                            value={instructions}
                                            onChange={(event) => setInstructions(event.target.value)}
                                            placeholder="Optional instructions for the restaurant or rider"
                                        />
                                    </div>
                                </CardBody>
                            </Card>
                        </div>

                        <div className="col-lg-5">
                            <Card variant="elevated" className="h-100">
                                <CardBody className="p-4">
                                    <h2 className="fs-5 fw-bold text-1000 mb-3">Order Summary</h2>
                                    {checkout.branch && (
                                        <p className="text-700 small mb-3">
                                            Delivering from {checkout.branch.name} · Estimated {checkout.branch.estimated_delivery_time} minutes
                                        </p>
                                    )}
                                    <div className="list-group list-group-flush mb-3">
                                        {checkout.items.map((item) => (
                                            <div key={`${item.menu_item_id}-${item.name}`} className="list-group-item px-0 d-flex justify-content-between">
                                                <span className="text-700">{item.quantity} × {item.name}</span>
                                                <span className="fw-semibold text-1000">{money(item.final_price * item.quantity)}</span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="d-flex justify-content-between text-700 mb-2">
                                        <span>Subtotal</span>
                                        <span>{money(checkout.subtotal)}</span>
                                    </div>
                                    <div className="d-flex justify-content-between text-700 mb-2">
                                        <span>Delivery fee</span>
                                        <span>{money(checkout.delivery_fee)}</span>
                                    </div>
                                    <div className="d-flex justify-content-between fw-bold text-1000 border-top pt-3 mb-4">
                                        <span>Total before VAT</span>
                                        <span>{money(checkout.total)}</span>
                                    </div>
                                    <Button type="submit" variant="primary" className="w-100" loading={placing} disabled={!addresses.length || !!placedOrderId}>
                                        {placedOrderId ? 'Order Placed' : 'Place Order'}
                                    </Button>
                                </CardBody>
                            </Card>
                        </div>
                    </form>
                ) : (
                    <EmptyState
                        title="Checkout is unavailable"
                        description="Your cart is empty or the selected branch is not currently accepting orders."
                        action={<Button variant="primary" size="sm" as="a" href="/customer/cart">Review Cart</Button>}
                    />
                )}
            </PageScaffold>
        </CustomerLayout>
    );
}
