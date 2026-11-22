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

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function Dashboard() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [orders, setOrders] = useState<OrderSummary[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/orders')
            .then(({ data }) => setOrders(data?.data ?? []))
            .catch(() => setOrders([]))
            .finally(() => setLoading(false));
    }, []);

    const recentOrders = orders.slice(0, 3);
    const activeOrders = orders.filter((order) => !order.is_terminal);

    return (
        <CustomerLayout title="Customer Dashboard - Alabiq" user={auth?.user}>
            <PageScaffold
                eyebrow="Customer"
                title={`Welcome back, ${auth?.user?.name ?? 'Customer'}`}
                description="Review active orders, manage delivery addresses, or start a new order."
                actions={<Button variant="primary" as="a" href="/">Order Now</Button>}
            >
                <div className="row g-3 mb-4">
                    <div className="col-md-4">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-3">
                                <div className="text-700 small text-uppercase fw-bold">Active orders</div>
                                <div className="fs-4 fw-bolder text-1000">{activeOrders.length}</div>
                                <a href="/customer/orders" className="small fw-semibold">View order history</a>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-md-4">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-3">
                                <div className="text-700 small text-uppercase fw-bold">Saved addresses</div>
                                <div className="fs-4 fw-bolder text-1000">Delivery</div>
                                <a href="/customer/addresses" className="small fw-semibold">Manage addresses</a>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-md-4">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-3">
                                <div className="text-700 small text-uppercase fw-bold">Cart</div>
                                <div className="fs-4 fw-bolder text-1000">Checkout</div>
                                <a href="/customer/cart" className="small fw-semibold">Review cart</a>
                            </CardBody>
                        </Card>
                    </div>
                </div>

                <Card>
                    <CardBody className="p-4">
                        <div className="d-flex justify-content-between align-items-center mb-3">
                            <h2 className="fs-5 fw-bold text-1000 mb-0">Recent Orders</h2>
                            <a href="/customer/orders" className="small fw-semibold">View all</a>
                        </div>
                        {loading ? (
                            <p className="text-700 mb-0">Loading your recent orders…</p>
                        ) : recentOrders.length ? (
                            <div className="list-group list-group-flush">
                                {recentOrders.map((order) => (
                                    <a key={order.id} href={`/customer/orders/${order.id}`} className="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div className="fw-semibold text-1000">Order #{order.id}</div>
                                            <div className="small text-700">
                                                {order.restaurant?.name ?? 'Restaurant'} · {new Date(order.created_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                        <div className="text-end">
                                            <div className="fw-bold text-1000">{money(order.total_amount)}</div>
                                            <span className="badge bg-soft-primary text-primary">{order.status_label}</span>
                                        </div>
                                    </a>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                title="No orders yet"
                                description="Your recent orders will appear here after you place your first order."
                                action={<Button variant="outline" size="sm" as="a" href="/">Start an Order</Button>}
                            />
                        )}
                    </CardBody>
                </Card>
            </PageScaffold>
        </CustomerLayout>
    );
}
