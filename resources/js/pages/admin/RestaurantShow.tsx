import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useParams } from 'react-router-dom';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface RestaurantDetail {
    id: number;
    name: string;
    slug: string;
    city: string;
    is_active: boolean;
    is_approved: boolean;
    commission_rate?: string | number;
    owner?: { name: string; email: string };
}

export default function RestaurantShow() {
    const { restaurant: restaurantParam } = useParams<{ restaurant: string }>();
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [restaurant, setRestaurant] = useState<RestaurantDetail | null>(null);
    const [rate, setRate] = useState('15');
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        try {
            const { data } = await api.get('/admin/restaurants', { params: {} });
            const found = (data?.data?.data ?? []).find((r: RestaurantDetail) => r.id === Number(restaurantParam));
            setRestaurant(found ?? null);
        } catch {
            setError('Could not load restaurant.');
        }
    }, [restaurantParam]);

    useEffect(() => {
        load();
    }, [load]);

    const saveCommission = async () => {
        if (!restaurant) return;
        setBusy(true);
        setError(null);
        setMessage(null);
        try {
            await api.put(`/admin/restaurants/${restaurant.id}/commission`, { rate: Number(rate) });
            setMessage('Commission override saved.');
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not save commission.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <SuperAdminLayout title="Restaurant Details - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <a href="/admin/restaurants" className="btn btn-outline-secondary btn-sm mb-3">← Back to restaurants</a>
                {error && <div className="alert alert-danger">{error}</div>}
                {message && <div className="alert alert-success">{message}</div>}

                {!restaurant ? (
                    <Card><CardBody className="p-4 text-center text-700">Restaurant not found.</CardBody></Card>
                ) : (
                    <div className="row g-4">
                        <div className="col-lg-6">
                            <Card>
                                <CardBody className="p-4">
                                    <h5 className="fw-bold">{restaurant.name}</h5>
                                    <p className="text-700 mb-1">{restaurant.slug} · {restaurant.city}</p>
                                    <p className="text-700 mb-1">Owner: {restaurant.owner?.name} ({restaurant.owner?.email})</p>
                                    <p className="mb-3">
                                        <span className={`badge ${restaurant.is_approved ? 'bg-success' : 'bg-warning text-dark'}`}>
                                            {restaurant.is_approved ? 'Approved' : 'Pending approval'}
                                        </span>{' '}
                                        <span className={`badge ${restaurant.is_active ? 'bg-info text-dark' : 'bg-secondary'}`}>
                                            {restaurant.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </p>
                                    <p className="text-700 small">Default commission: {restaurant.commission_rate ?? '15'}%</p>
                                </CardBody>
                            </Card>
                        </div>

                        <div className="col-lg-6">
                            <Card>
                                <CardBody className="p-4">
                                    <h6 className="fw-bold">Commission Override</h6>
                                    <p className="small text-700">Overrides the platform default rate for this restaurant.</p>
                                    <div className="input-group">
                                        <input
                                            type="number"
                                            className="form-control"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value={rate}
                                            onChange={(e) => setRate(e.target.value)}
                                        />
                                        <span className="input-group-text">%</span>
                                        <button type="button" className="btn btn-primary" disabled={busy} onClick={saveCommission}>
                                            {busy ? 'Saving…' : 'Save'}
                                        </button>
                                    </div>
                                </CardBody>
                            </Card>
                        </div>
                    </div>
                )}
            </div>
        </SuperAdminLayout>
    );
}
