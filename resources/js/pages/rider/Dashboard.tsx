import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { RiderLayout } from '@/components/layout/RiderLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface SharedAuth {
    user?: {
        name: string;
        rider?: { id: number; is_online: boolean; vehicle_type: string } | null;
    } | null;
}

export default function Dashboard() {
    const { auth } = usePage().props as unknown as { auth: SharedAuth };
    const user = auth?.user;
    const rider = user?.rider;

    const [isOnline, setIsOnline] = useState(rider?.is_online ?? false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const toggle = async (online: boolean) => {
        setBusy(true);
        setError(null);
        try {
            await api.put('/rider/availability', { status: online ? 'online' : 'offline' });
            setIsOnline(online);
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not update availability');
        } finally {
            setBusy(false);
        }
    };

    return (
        <RiderLayout rider={{ name: user?.name ?? 'Rider', isOnline }}>
            <div className="container py-4">
                <div className="row g-4">
                    <div className="col-lg-8">
                        <Card>
                            <CardBody className="p-4">
                                <h4 className="fw-bold mb-1">Welcome back, {user?.name}</h4>
                                <p className="text-700 mb-4">
                                    {isOnline
                                        ? 'You are online and can receive delivery offers.'
                                        : 'You are offline. Go online to start receiving delivery offers.'}
                                </p>

                                {error && <div className="alert alert-danger">{error}</div>}

                                <div className="d-flex gap-3">
                                    <button
                                        type="button"
                                        className={`btn ${isOnline ? 'btn-outline-danger' : 'btn-success'}`}
                                        disabled={busy}
                                        onClick={() => toggle(!isOnline)}
                                    >
                                        {busy ? 'Updating…' : isOnline ? 'Go Offline' : 'Go Online'}
                                    </button>
                                    <span className={`badge align-self-center ${isOnline ? 'bg-success' : 'bg-secondary'}`}>
                                        {isOnline ? 'Online' : 'Offline'}
                                    </span>
                                    <span className="badge bg-soft-primary text-primary align-self-center text-uppercase">
                                        {rider?.vehicle_type ?? 'bike'}
                                    </span>
                                </div>
                            </CardBody>
                        </Card>
                    </div>

                    <div className="col-lg-4">
                        <Card>
                            <CardBody className="p-4">
                                <h6 className="text-700 text-uppercase small fw-bold">Quick Links</h6>
                                <div className="list-group list-group-flush">
                                    <a href="/rider/deliveries" className="list-group-item list-group-item-action px-0">
                                        My Deliveries
                                    </a>
                                    <a href="/rider/earnings" className="list-group-item list-group-item-action px-0">
                                        Earnings
                                    </a>
                                    <a href="/rider/profile" className="list-group-item list-group-item-action px-0">
                                        Profile
                                    </a>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </RiderLayout>
    );
}
