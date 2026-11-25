import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useParams } from '@/hooks/useParams';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface RiderDetail {
    id: number;
    vehicle_type: string;
    plate_number: string | null;
    is_online: boolean;
    is_active: boolean;
    total_deliveries: number;
    rating: string | null;
    user?: { name: string; email: string };
    assignments_count?: number;
}

export default function RiderShow() {
    const { rider: riderParam } = useParams<{ rider: string }>();
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [rider, setRider] = useState<RiderDetail | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        api.get('/admin/riders')
            .then(({ data }) => {
                const found = (data?.data?.data ?? []).find((r: RiderDetail) => r.id === Number(riderParam));
                setRider(found ?? null);
            })
            .catch(() => setError('Could not load rider.'));
    }, [riderParam]);

    return (
        <SuperAdminLayout title="Rider Details - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <a href="/admin/riders" className="btn btn-outline-secondary btn-sm mb-3">← Back to riders</a>
                {error && <div className="alert alert-danger">{error}</div>}

                {!rider ? (
                    <Card><CardBody className="p-4 text-center text-700">Rider not found.</CardBody></Card>
                ) : (
                    <Card>
                        <CardBody className="p-4">
                            <h5 className="fw-bold">{rider.user?.name ?? `Rider #${rider.id}`}</h5>
                            <p className="text-700 mb-1">{rider.user?.email}</p>
                            <p className="text-700 mb-1">
                                Vehicle: {rider.vehicle_type}{rider.plate_number ? ` · Plate ${rider.plate_number}` : ''}
                            </p>
                            <p className="mb-2">
                                <span className={`badge ${rider.is_online ? 'bg-success' : 'bg-secondary'}`}>{rider.is_online ? 'Online' : 'Offline'}</span>{' '}
                                <span className={`badge ${rider.is_active ? 'bg-info text-dark' : 'bg-danger'}`}>{rider.is_active ? 'Active' : 'Deactivated'}</span>
                            </p>
                            <p className="text-700 mb-0">
                                {rider.assignments_count ?? rider.total_deliveries} lifetime deliveries
                                {rider.rating ? ` · Rating ${Number(rider.rating).toFixed(1)}` : ''}
                            </p>
                        </CardBody>
                    </Card>
                )}
            </div>
        </SuperAdminLayout>
    );
}
