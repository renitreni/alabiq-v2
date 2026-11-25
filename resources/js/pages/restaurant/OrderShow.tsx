import { useCallback, useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useParams } from '@/hooks/useParams';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface OrderItem {
    id: number;
    name: string;
    quantity: number;
    final_price: number;
    subtotal: number;
    options_snapshot?: Array<{ option_name: string; value_name: string }>;
}

interface StatusHistory {
    id: number;
    from_status?: string | null;
    to_status: string;
    note?: string | null;
    created_at: string;
}

interface Assignment {
    id: number;
    status: string;
    offered_at?: string | null;
    accepted_at?: string | null;
    rejected_reason?: string | null;
    rider?: { user?: { name: string } | null } | null;
}

interface AvailableRider {
    id: number;
    name?: string | null;
    vehicle_type?: string | null;
    rating?: string | number | null;
    total_deliveries?: number | null;
    distance_km?: number | null;
}

interface RestaurantOrderDetail {
    id: number;
    status: string;
    status_label: string;
    subtotal: number;
    delivery_fee: number;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    delivery_instructions?: string | null;
    created_at: string;
    user?: { name: string; email?: string } | null;
    customerAddress?: { formatted_address: string } | null;
    branch?: { name: string } | null;
    items: OrderItem[];
    statusHistories: StatusHistory[];
    assignments?: Assignment[];
}

const workflow = [
    'pending_payment',
    'placed',
    'accepted',
    'preparing',
    'ready',
    'rider_assigned',
    'picked_up',
    'on_the_way',
    'delivered',
];

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;
const label = (status: string) => status.replaceAll('_', ' ');

const assignmentLabels: Record<string, string> = {
    assigned: 'Offer sent',
    accepted: 'Accepted',
    rejected: 'Rejected',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

const pickMessage = (err: any, fallback: string) =>
    (err && err.response && err.response.data && err.response.data.message) || fallback;

export default function OrderShow() {
    const { order: orderParam } = useParams<{ order: string }>();
    const { auth } = usePage().props as unknown as {
        auth: {
            user?: {
                name: string;
                restaurant?: { name: string; logo?: string | null } | null;
            } | null;
        };
    };
    const restaurant = auth?.user?.restaurant;
    const [order, setOrder] = useState<RestaurantOrderDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [busyStatus, setBusyStatus] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [riders, setRiders] = useState<AvailableRider[]>([]);
    const [ridersLoading, setRidersLoading] = useState(false);
    const [assigningRider, setAssigningRider] = useState<number | null>(null);

    const loadOrder = useCallback(async () => {
        if (!orderParam) return;

        setLoading(true);
        setError(null);

        try {
            const { data } = await api.get(`/restaurant/orders/${orderParam}`);
            setOrder(data?.data ?? null);
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not load this order.');
        } finally {
            setLoading(false);
        }
    }, [orderParam]);

    useEffect(() => {
        loadOrder();
    }, [loadOrder]);

    const updateStatus = async (status: string, requiresConfirmation = false) => {
        if (!order) return;

        if (requiresConfirmation && !window.confirm('Reject this order?')) {
            return;
        }

        setBusyStatus(status);
        setError(null);
        setNotice(null);

        try {
            await api.patch(`/orders/${order.id}/status`, {
                status,
                note: status === 'cancelled' ? 'Restaurant rejected the order' : undefined,
            });
            setNotice('Order status updated.');
            await loadOrder();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not update the order status.');
        } finally {
            setBusyStatus(null);
        }
    };

    const openPicker = async () => {
        if (!order) return;

        setPickerOpen(true);
        setRidersLoading(true);
        setError(null);

        try {
            const { data } = await api.get(`/restaurant/orders/${order.id}/available-riders`);
            setRiders(data?.data ?? []);
        } catch (err: any) {
            setError(pickMessage(err, 'Could not load available riders.'));
            setPickerOpen(false);
        } finally {
            setRidersLoading(false);
        }
    };

    const assignRider = async (riderId: number) => {
        if (!order) return;

        setAssigningRider(riderId);
        setError(null);
        setNotice(null);

        try {
            await api.post(`/orders/${order.id}/assign-rider`, { rider_id: riderId });
            setNotice('Rider assigned. Waiting for the rider to accept.');
            setPickerOpen(false);
            await loadOrder();
        } catch (err: any) {
            setError(pickMessage(err, 'Could not assign the rider.'));
        } finally {
            setAssigningRider(null);
        }
    };

    const assignments = order?.assignments ?? [];
    const latestAssignment = assignments[0] ?? null;
    const assignmentActive = latestAssignment
        ? ['assigned', 'accepted'].includes(latestAssignment.status)
        : false;
    const canDispatch = !!order
        && (order.status === 'ready' || (order.status === 'rider_assigned' && !assignmentActive));

    const actions = useMemo(() => {
        if (!order || ['delivered', 'cancelled', 'refunded'].includes(order.status)) {
            return [];
        }

        if (order.status === 'placed') {
            return [
                { label: 'Accept Order', status: 'accepted', variant: 'primary' as const, confirm: false },
                { label: 'Reject Order', status: 'cancelled', variant: 'danger' as const, confirm: true },
            ];
        }

        if (order.status === 'accepted') {
            return [
                { label: 'Start Preparing', status: 'preparing', variant: 'primary' as const, confirm: false },
                { label: 'Reject Order', status: 'cancelled', variant: 'danger' as const, confirm: true },
            ];
        }

        if (order.status === 'preparing') {
            return [
                { label: 'Mark Ready', status: 'ready', variant: 'primary' as const, confirm: false },
            ];
        }

        return [];
    }, [order]);

    return (
        <RestaurantLayout
            title={`Order ${orderParam ?? ''} - Alabiq`}
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Order"
                title={`Order #${orderParam ?? ''}`}
                description="Review customer details, items, and update the restaurant workflow."
                actions={<Button variant="outline" size="sm" as="a" href="/restaurant/orders">All Orders</Button>}
            >
                {error && <div className="alert alert-danger">{error}</div>}
                {notice && <div className="alert alert-success">{notice}</div>}

                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading order…</CardBody>
                    </Card>
                ) : order ? (
                    <>
                        <Card className="mb-4">
                            <CardBody className="p-4">
                                <div className="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                                    <div>
                                        <h2 className="fs-5 fw-bold text-1000 mb-1">{order.status_label}</h2>
                                        <p className="text-700 mb-0">
                                            {order.user?.name ?? 'Customer'} · {new Date(order.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                    {(actions.length > 0 || canDispatch) && (
                                        <div className="d-flex gap-2 flex-wrap">
                                            {actions.map((action) => (
                                                <Button
                                                    key={action.status}
                                                    variant={action.variant}
                                                    size="sm"
                                                    loading={busyStatus === action.status}
                                                    disabled={busyStatus !== null}
                                                    onClick={() => updateStatus(action.status, action.confirm)}
                                                >
                                                    {action.label}
                                                </Button>
                                            ))}
                                            {canDispatch && (
                                                <Button variant="primary" size="sm" onClick={openPicker}>
                                                    {order.status === 'rider_assigned' ? 'Re-dispatch Rider' : 'Assign Rider'}
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="d-flex flex-wrap gap-2">
                                    {workflow.map((status) => {
                                        const currentIndex = workflow.indexOf(order.status);
                                        const stepIndex = workflow.indexOf(status);
                                        const className = stepIndex < currentIndex
                                            ? 'bg-soft-success text-success'
                                            : stepIndex === currentIndex
                                                ? 'bg-primary text-white'
                                                : 'bg-soft-primary text-primary';

                                        return <span key={status} className={`badge px-3 py-2 ${className}`}>{label(status)}</span>;
                                    })}
                                </div>
                            </CardBody>
                        </Card>

                        {pickerOpen && (
                            <Card className="mb-4">
                                <CardBody className="p-4">
                                    <div className="d-flex justify-content-between align-items-center mb-3">
                                        <h2 className="fs-5 fw-bold text-1000 mb-0">Available riders</h2>
                                        <Button variant="outline" size="sm" onClick={() => setPickerOpen(false)}>Close</Button>
                                    </div>
                                    {ridersLoading ? (
                                        <p className="text-700 mb-0">Loading riders…</p>
                                    ) : riders.length === 0 ? (
                                        <p className="text-700 mb-0">No riders available right now. Try again shortly.</p>
                                    ) : (
                                        <div className="list-group list-group-flush">
                                            {riders.map((rider) => (
                                                <div key={rider.id} className="list-group-item px-0 d-flex justify-content-between align-items-center gap-3">
                                                    <div>
                                                        <div className="fw-semibold text-1000">{rider.name ?? `Rider #${rider.id}`}</div>
                                                        <div className="small text-700">
                                                            {[
                                                                rider.vehicle_type ? label(String(rider.vehicle_type)) : null,
                                                                rider.rating != null ? `Rating ${Number(rider.rating).toFixed(1)}` : null,
                                                                rider.total_deliveries != null ? `${rider.total_deliveries} deliveries` : null,
                                                                rider.distance_km != null ? `${rider.distance_km.toFixed(1)} km away` : 'location unknown',
                                                            ].filter(Boolean).join(' · ')}
                                                        </div>
                                                    </div>
                                                    <Button
                                                        variant="primary"
                                                        size="sm"
                                                        loading={assigningRider === rider.id}
                                                        disabled={assigningRider !== null}
                                                        onClick={() => assignRider(rider.id)}
                                                    >
                                                        Assign
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardBody>
                            </Card>
                        )}

                        <div className="row g-4">
                            <div className="col-lg-7">
                                <Card className="h-100">
                                    <CardBody className="p-4">
                                        <h2 className="fs-5 fw-bold text-1000 mb-3">Items</h2>
                                        <div className="list-group list-group-flush">
                                            {order.items.map((item) => (
                                                <div key={item.id} className="list-group-item px-0">
                                                    <div className="d-flex justify-content-between gap-3">
                                                        <div>
                                                            <div className="fw-semibold text-1000">{item.quantity} × {item.name}</div>
                                                            {item.options_snapshot?.length ? (
                                                                <div className="small text-700">
                                                                    {item.options_snapshot.map((option) => `${option.option_name}: ${option.value_name}`).join(' · ')}
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                        <div className="fw-bold text-1000">{money(item.subtotal)}</div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardBody>
                                </Card>
                            </div>

                            <div className="col-lg-5">
                                <div className="d-flex flex-column gap-4">
                                    <Card>
                                        <CardBody className="p-4">
                                            <h2 className="fs-5 fw-bold text-1000 mb-3">Order Summary</h2>
                                            <div className="d-flex justify-content-between text-700 mb-2"><span>Subtotal</span><span>{money(order.subtotal)}</span></div>
                                            <div className="d-flex justify-content-between text-700 mb-2"><span>Delivery fee</span><span>{money(order.delivery_fee)}</span></div>
                                            <div className="d-flex justify-content-between text-700 mb-2"><span>Tax</span><span>{money(order.tax_amount)}</span></div>
                                            {order.discount_amount > 0 && (
                                                <div className="d-flex justify-content-between text-700 mb-2"><span>Discount</span><span>-{money(order.discount_amount)}</span></div>
                                            )}
                                            <div className="d-flex justify-content-between fw-bold text-1000 border-top pt-3"><span>Total</span><span>{money(order.total_amount)}</span></div>
                                        </CardBody>
                                    </Card>

                                    {latestAssignment && (
                                        <Card>
                                            <CardBody className="p-4">
                                                <h2 className="fs-5 fw-bold text-1000 mb-3">Rider</h2>
                                                <div className="d-flex justify-content-between gap-3 mb-2">
                                                    <span className="fw-semibold text-1000">{latestAssignment.rider?.user?.name ?? 'Rider'}</span>
                                                    <span className={`badge ${assignmentActive ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'}`}>
                                                        {assignmentLabels[latestAssignment.status] ?? label(latestAssignment.status)}
                                                    </span>
                                                </div>
                                                <p className="text-700 small mb-1">
                                                    Offered {latestAssignment.offered_at ? new Date(latestAssignment.offered_at).toLocaleString() : '—'}
                                                    {latestAssignment.accepted_at && ` · Accepted ${new Date(latestAssignment.accepted_at).toLocaleString()}`}
                                                </p>
                                                {latestAssignment.status === 'rejected' && latestAssignment.rejected_reason && (
                                                    <p className="text-danger small mb-1">Rejected: {latestAssignment.rejected_reason}</p>
                                                )}
                                                {latestAssignment.status === 'rejected' && (
                                                    <p className="text-700 small mb-0">Re-dispatch this order to another rider.</p>
                                                )}
                                                {assignments.length > 1 && (
                                                    <p className="text-700 small mb-0 mt-2">
                                                        {assignments.length - 1} earlier attempt{assignments.length > 2 ? 's' : ''} rejected.
                                                    </p>
                                                )}
                                            </CardBody>
                                        </Card>
                                    )}

                                    <Card>
                                        <CardBody className="p-4">
                                            <h2 className="fs-5 fw-bold text-1000 mb-3">Delivery</h2>
                                            <p className="text-700 mb-1">{order.customerAddress?.formatted_address ?? 'No delivery address saved for this order.'}</p>
                                            {order.branch && <p className="text-700 small mb-1">Branch: {order.branch.name}</p>}
                                            {order.delivery_instructions && <p className="text-700 small mb-0">Instructions: {order.delivery_instructions}</p>}
                                        </CardBody>
                                    </Card>

                                    <Card>
                                        <CardBody className="p-4">
                                            <h2 className="fs-5 fw-bold text-1000 mb-3">Status History</h2>
                                            <div className="list-group list-group-flush">
                                                {order.statusHistories.map((history) => (
                                                    <div key={history.id} className="list-group-item px-0">
                                                        <div className="d-flex justify-content-between gap-3">
                                                            <span className="fw-semibold text-1000 text-capitalize">{label(history.to_status)}</span>
                                                            <span className="text-700 small">{new Date(history.created_at).toLocaleString()}</span>
                                                        </div>
                                                        {history.note && <div className="small text-700">{history.note}</div>}
                                                    </div>
                                                ))}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </>
                ) : (
                    <Card>
                        <CardBody className="p-4 text-700">Order not found.</CardBody>
                    </Card>
                )}
            </PageScaffold>
        </RestaurantLayout>
    );
}
