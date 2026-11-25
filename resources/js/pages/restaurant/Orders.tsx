import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageScaffold } from '@/components/ui/PageScaffold';
import { ORDERS_CHANGED_EVENT } from '@/components/restaurant/NewOrderBanner';
import api from '@/lib/axios';

interface RestaurantOrder {
    id: number;
    status: string;
    status_label: string;
    total_amount: number;
    is_terminal: boolean;
    created_at: string;
    user?: { name: string } | null;
    branch?: { name: string } | null;
    items?: Array<{ quantity: number }>;
}

const statusOptions = [
    { value: '', label: 'All orders' },
    { value: 'placed', label: 'New' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'preparing', label: 'Preparing' },
    { value: 'ready', label: 'Ready for pickup' },
    { value: 'rider_assigned', label: 'Rider assigned' },
    { value: 'picked_up', label: 'Picked up' },
    { value: 'on_the_way', label: 'On the way' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function Orders() {
    const { auth } = usePage().props as unknown as {
        auth: {
            user?: {
                name: string;
                restaurant?: { name: string; logo?: string | null } | null;
            } | null;
        };
    };
    const restaurant = auth?.user?.restaurant;
    const [orders, setOrders] = useState<RestaurantOrder[]>([]);
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let first = true;
        const load = () => {
            if (first) {
                setLoading(true);
            } else if (document.visibilityState !== 'visible') {
                return;
            }
            setError(null);

            api.get('/restaurant/orders', {
                params: status ? { status } : {},
            })
                .then(({ data }) => setOrders(data?.data ?? []))
                .catch((e: any) => setError(e?.response?.data?.message ?? 'Could not load restaurant orders.'))
                .finally(() => {
                    first = false;
                    setLoading(false);
                });
        };

        load();
        const timer = setInterval(load, 15000);
        window.addEventListener(ORDERS_CHANGED_EVENT, load);
        return () => {
            clearInterval(timer);
            window.removeEventListener(ORDERS_CHANGED_EVENT, load);
        };
    }, [status]);

    return (
        <RestaurantLayout
            title="Restaurant Orders - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Orders"
                title="Incoming Orders"
                description="Review new orders and track preparation status."
            >
                <div className="row justify-content-between align-items-center g-3 mb-4">
                    <div className="col-md-4">
                        <label htmlFor="order-status" className="form-label fw-semibold">Filter by status</label>
                        <select
                            id="order-status"
                            className="form-select"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {error && <div className="alert alert-danger">{error}</div>}

                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading orders…</CardBody>
                    </Card>
                ) : orders.length ? (
                    <Card>
                        <CardBody className="p-0">
                            <div className="list-group list-group-flush">
                                {orders.map((order) => (
                                    <a key={order.id} href={`/restaurant/orders/${order.id}`} className="list-group-item list-group-item-action p-3 p-md-4">
                                        <div className="d-flex justify-content-between gap-3">
                                            <div>
                                                <div className="fw-semibold text-1000">Order #{order.id}</div>
                                                <div className="small text-700">
                                                    {order.user?.name ?? 'Customer'} · {order.items?.length ?? 0} item types · {new Date(order.created_at).toLocaleString()}
                                                </div>
                                            </div>
                                            <div className="text-end">
                                                <div className="fw-bold text-1000">{money(order.total_amount)}</div>
                                                <span className={`badge ${order.is_terminal ? 'bg-soft-secondary text-secondary' : 'bg-soft-primary text-primary'}`}>
                                                    {order.status_label}
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                ))}
                            </div>
                        </CardBody>
                    </Card>
                ) : (
                    <EmptyState
                        title="No orders found"
                        description={status ? `No ${status.replace('_', ' ')} orders are currently available.` : 'Incoming customer orders will appear here.'}
                        icon="🧾"
                    />
                )}
            </PageScaffold>
        </RestaurantLayout>
    );
}
