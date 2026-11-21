import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface RestaurantRow {
    id: number;
    name: string;
    slug: string;
    city: string;
    is_active: boolean;
    is_approved: boolean;
    branches_count?: number;
    owner?: { name: string; email: string };
}

export default function Restaurants() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [pending, setPending] = useState<RestaurantRow[]>([]);
    const [all, setAll] = useState<RestaurantRow[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const load = useCallback(async () => {
        try {
            const [p, a] = await Promise.all([
                api.get('/admin/restaurants/pending'),
                api.get('/admin/restaurants'),
            ]);
            setPending(p.data?.data?.data ?? []);
            setAll(a.data?.data?.data ?? []);
            setError(null);
        } catch {
            setError('Could not load restaurants.');
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const approve = async (r: RestaurantRow) => {
        setBusyId(r.id);
        try {
            await api.patch(`/admin/restaurants/${r.id}/approval`, { is_approved: true });
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Approval failed.');
        } finally {
            setBusyId(null);
        }
    };

    const toggleActive = async (r: RestaurantRow) => {
        setBusyId(r.id);
        try {
            await api.patch(`/admin/restaurants/${r.id}/approval`, { is_active: !r.is_active });
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Update failed.');
        } finally {
            setBusyId(null);
        }
    };

    const renderRow = (r: RestaurantRow, actions: React.ReactNode) => (
        <div key={r.id} className="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div className="fw-semibold">
                    {r.name} <span className="text-700 small">· {r.city}</span>
                </div>
                <div className="small text-700">
                    {r.owner?.name} ({r.owner?.email}) · {r.branches_count ?? 0} branches
                </div>
            </div>
            <div className="d-flex align-items-center gap-2">
                {!r.is_approved && <span className="badge bg-warning text-dark">Pending approval</span>}
                <span className={`badge ${r.is_active ? 'bg-success' : 'bg-secondary'}`}>{r.is_active ? 'Active' : 'Inactive'}</span>
                {actions}
                <a className="btn btn-outline-primary btn-sm" href={`/admin/restaurants/${r.id}`}>Details</a>
            </div>
        </div>
    );

    return (
        <SuperAdminLayout title="Restaurants - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">Restaurants</h4>
                {error && <div className="alert alert-danger">{error}</div>}

                <Card className="mb-4">
                    <CardBody className="p-4">
                        <h6 className="fw-bold">Pending Approval ({pending.length})</h6>
                        {pending.length === 0 ? (
                            <p className="text-700 mb-0">No restaurants waiting for approval.</p>
                        ) : (
                            <div className="list-group list-group-flush">
                                {pending.map((r) => renderRow(r, (
                                    <button
                                        type="button"
                                        className="btn btn-success btn-sm"
                                        disabled={busyId === r.id}
                                        onClick={() => approve(r)}
                                    >
                                        Approve
                                    </button>
                                )))}
                            </div>
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardBody className="p-4">
                        <h6 className="fw-bold">All Restaurants ({all.length})</h6>
                        <div className="list-group list-group-flush">
                            {all.map((r) => renderRow(r, (
                                <button
                                    type="button"
                                    className={`btn btn-sm ${r.is_active ? 'btn-outline-danger' : 'btn-outline-success'}`}
                                    disabled={busyId === r.id}
                                    onClick={() => toggleActive(r)}
                                >
                                    {r.is_active ? 'Suspend' : 'Reactivate'}
                                </button>
                            )))}
                        </div>
                    </CardBody>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
