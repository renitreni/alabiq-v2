import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { RiderLayout } from '@/components/layout/RiderLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface Assignment {
    id: number;
    status: 'assigned' | 'accepted' | 'rejected' | 'completed' | 'cancelled';
    distance_km: string | number | null;
    delivery_fee: string | number | null;
    offered_at: string | null;
    order?: { id: number; status: string; total_amount: string | number; restaurant?: { name: string } };
}

const STATUS_BADGE: Record<string, string> = {
    assigned: 'bg-warning text-dark',
    accepted: 'bg-info text-dark',
    rejected: 'bg-secondary',
    completed: 'bg-success',
    cancelled: 'bg-secondary',
};

export default function Deliveries() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; rider?: { is_online: boolean } | null } | null };
    };
    const rider = auth?.user?.rider;

    const [assignments, setAssignments] = useState<Assignment[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const load = useCallback(async () => {
        try {
            const { data } = await api.get('/rider/assignments');
            setAssignments(data?.data?.data ?? []);
            setError(null);
        } catch {
            setError('Could not load assignments.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
        const t = setInterval(load, 15000);
        return () => clearInterval(t);
    }, [load]);

    const act = async (assignment: Assignment, action: 'accept' | 'reject') => {
        setBusyId(assignment.id);
        setError(null);
        try {
            if (action === 'reject') {
                await api.post(`/rider/assignments/${assignment.id}/reject`, { reason: 'Unavailable' });
            } else {
                await api.post(`/rider/assignments/${assignment.id}/accept`);
            }
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? `Could not ${action} the assignment.`);
        } finally {
            setBusyId(null);
        }
    };

    return (
        <RiderLayout title="My Deliveries - Alabiq" rider={{ name: auth?.user?.name ?? 'Rider', isOnline: rider?.is_online }}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">Deliveries</h4>
                {error && <div className="alert alert-danger">{error}</div>}
                {loading ? (
                    <p className="text-700">Loading…</p>
                ) : assignments.length === 0 ? (
                    <Card><CardBody className="p-4 text-center text-700">No assignments yet. They will appear here when a restaurant dispatches an order to you.</CardBody></Card>
                ) : (
                    <div className="list-group">
                        {assignments.map((a) => (
                            <div key={a.id} className="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <div className="fw-semibold">
                                        Order #{a.order?.id} · {a.order?.restaurant?.name ?? 'Restaurant'}
                                    </div>
                                    <div className="small text-700">
                                        {a.distance_km != null ? `${Number(a.distance_km).toFixed(1)} km · ` : ''}
                                        Fee {a.delivery_fee != null ? `${Number(a.delivery_fee).toFixed(2)} SAR` : '—'}
                                        {a.offered_at ? ` · ${new Date(a.offered_at).toLocaleString()}` : ''}
                                    </div>
                                </div>
                                <div className="d-flex align-items-center gap-2">
                                    <span className={`badge ${STATUS_BADGE[a.status] ?? 'bg-secondary'}`}>{a.status}</span>
                                    {a.status === 'assigned' && (
                                        <>
                                            <button
                                                type="button"
                                                className="btn btn-success btn-sm"
                                                disabled={busyId === a.id}
                                                onClick={() => act(a, 'accept')}
                                            >
                                                Accept
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-outline-secondary btn-sm"
                                                disabled={busyId === a.id}
                                                onClick={() => act(a, 'reject')}
                                            >
                                                Reject
                                            </button>
                                        </>
                                    )}
                                    {a.status === 'accepted' && (
                                        <a className="btn btn-primary btn-sm" href={`/rider/tracking/${a.id}`}>
                                            Track
                                        </a>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </RiderLayout>
    );
}
