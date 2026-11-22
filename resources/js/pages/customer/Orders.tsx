import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CustomerLayout } from '@/components/layout/CustomerLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface OrderSummary {
    id: number;
    status: string;
    status_label: string;
    total_amount: number;
    is_terminal: boolean;
    created_at: string;
    restaurant?: { name: string } | null;
}

const statuses = [
    { value: '', label: 'All orders' },
    { value: 'placed', label: 'Placed' },
    { value: 'preparing', label: 'Preparing' },
    { value: 'on_the_way', label: 'On the way' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function Orders() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [orders, setOrders] = useState<OrderSummary[]>([]);
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setLoading(true);
        setError(null);

        api.get('/orders', {
            params: status ? { status } : {},
        })
            .then(({ data }) => setOrders(data?.data ?? []))
            .catch(() => setError('Could not load your orders. Please try again.'))
            .finally(() => setLoading(false));
    }, [status]);

    return (
        <CustomerLayout title="My Orders - Alabiq" user={auth?.user}>
            <PageScaffold
                eyebrow="Orders"
                title="Order History"
                description="Track current deliveries and review previous orders."
            >
                <div className="row justify-content-between align-items-center g-3 mb-4">
                    <div className="col-md-4">
                        <label htmlFor="status" className="form-label fw-semibold">Filter by status</label>
                        <select
                            id="status"
                            className="form-select"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            {statuses.map((option) => (
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
                                    <a key={order.id} href={`/customer/orders/${order.id}`} className="list-group-item list-group-item-action p-3 p-md-4">
                                        <div className="d-flex justify-content-between gap-3">
                                            <div>
                                                <div className="fw-semibold text-1000">Order #{order.id}</div>
                                                <div className="small text-700">
                                                    {order.restaurant?.name ?? 'Restaurant'} · {new Date(order.created_at).toLocaleString()}
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
                        description={status ? `No ${status.replace('_', ' ')} orders match this filter.` : 'Orders you place will appear here.'}
                        action={<Button variant="primary" size="sm" as="a" href="/">Order Now</Button>}
                    />
                )}
            </PageScaffold>
        </CustomerLayout>
    );
}
