import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface RiderRow {
    id: number;
    vehicle_type: string;
    is_online: boolean;
    is_active: boolean;
    total_deliveries: number;
    rating: string | null;
    user?: { name: string; email: string };
    assignments_count?: number;
}

export default function Riders() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [riders, setRiders] = useState<RiderRow[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const load = useCallback(async () => {
        try {
            const { data } = await api.get('/admin/riders');
            setRiders(data?.data?.data ?? []);
        } catch {
            setError('Could not load riders.');
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const toggleActive = async (r: RiderRow) => {
        setBusyId(r.id);
        try {
            await api.patch(`/admin/riders/${r.id}/approval`, { is_active: !r.is_active });
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Update failed.');
        } finally {
            setBusyId(null);
        }
    };

    return (
        <SuperAdminLayout title="Riders - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">Riders</h4>
                {error && <div className="alert alert-danger">{error}</div>}

                <Card>
                    <CardBody className="p-4">
                        <div className="list-group list-group-flush">
                            {riders.map((r) => (
                                <div key={r.id} className="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <div className="fw-semibold">
                                            {r.user?.name ?? `Rider #${r.id}`}
                                            <span className="text-700 small"> · {r.vehicle_type}</span>
                                        </div>
                                        <div className="small text-700">
                                            {r.user?.email} · {r.assignments_count ?? r.total_deliveries} deliveries
                                            {r.rating ? ` · ★ ${Number(r.rating).toFixed(1)}` : ''}
                                        </div>
                                    </div>
                                    <div className="d-flex align-items-center gap-2">
                                        <span className={`badge ${r.is_online ? 'bg-success' : 'bg-secondary'}`}>{r.is_online ? 'Online' : 'Offline'}</span>
                                        <span className={`badge ${r.is_active ? 'bg-info text-dark' : 'bg-danger'}`}>{r.is_active ? 'Active' : 'Deactivated'}</span>
                                        <button
                                            type="button"
                                            className={`btn btn-sm ${r.is_active ? 'btn-outline-danger' : 'btn-outline-success'}`}
                                            disabled={busyId === r.id}
                                            onClick={() => toggleActive(r)}
                                        >
                                            {r.is_active ? 'Deactivate' : 'Activate'}
                                        </button>
                                        <a className="btn btn-outline-primary btn-sm" href={`/admin/riders/${r.id}`}>Details</a>
                                    </div>
                                </div>
                            ))}
                            {riders.length === 0 && <p className="text-700 mb-0">No riders registered yet.</p>}
                        </div>
                    </CardBody>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
