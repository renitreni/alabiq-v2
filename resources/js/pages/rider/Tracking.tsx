import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useParams } from 'react-router-dom';
import { RiderLayout } from '@/components/layout/RiderLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface AssignmentDetail {
    id: number;
    status: string;
    distance_km: string | number | null;
    delivery_fee: string | number | null;
    order?: {
        id: number;
        status: string;
        restaurant?: { name: string };
        branch?: { name: string; address: string };
    };
}

export default function Tracking() {
    const { delivery } = useParams<{ delivery: string }>();
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; rider?: { is_online: boolean } | null } | null };
    };

    const [assignment, setAssignment] = useState<AssignmentDetail | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [tracking, setTracking] = useState(false);
    const [position, setPosition] = useState<{ lat: number; lng: number } | null>(null);
    const watchIdRef = useRef<number | null>(null);

    const load = useCallback(async () => {
        try {
            const { data } = await api.get(`/rider/assignments`, { params: {} });
            const found = (data?.data?.data ?? []).find((a: AssignmentDetail) => a.id === Number(delivery));
            setAssignment(found ?? null);
        } catch {
            setError('Could not load the assignment.');
        }
    }, [delivery]);

    useEffect(() => {
        load();
    }, [load]);

    const stopWatching = useCallback(() => {
        if (watchIdRef.current !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchIdRef.current);
            watchIdRef.current = null;
        }
        setTracking(false);
    }, []);

    const startWatching = useCallback(() => {
        if (!navigator.geolocation) {
            setError('Geolocation is not supported by this browser.');
            return;
        }

        setTracking(true);
        watchIdRef.current = navigator.geolocation.watchPosition(
            async (pos) => {
                const payload = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    speed: pos.coords.speed ?? undefined,
                    heading: pos.coords.heading ?? undefined,
                    accuracy: pos.coords.accuracy,
                };
                setPosition({ lat: payload.latitude, lng: payload.longitude });
                try {
                    await api.post('/rider/location', payload);
                } catch {
                    // Throttled or offline — the next tick retries.
                }
            },
            (err) => setError(err.message),
            { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
        );
    }, []);

    useEffect(() => stopWatching, [stopWatching]);

    return (
        <RiderLayout title="Delivery Tracking - Alabiq" rider={{ name: auth?.user?.name ?? 'Rider', isOnline: auth?.user?.rider?.is_online }}>
            <div className="container py-4">
                {error && <div className="alert alert-danger">{error}</div>}

                {!assignment ? (
                    <Card><CardBody className="p-4 text-center text-700">Assignment not found.</CardBody></Card>
                ) : (
                    <div className="row g-4">
                        <div className="col-lg-6">
                            <Card>
                                <CardBody className="p-4">
                                    <h5 className="fw-bold mb-3">Order #{assignment.order?.id}</h5>
                                    <p className="mb-1"><strong>{assignment.order?.restaurant?.name ?? 'Restaurant'}</strong> · {assignment.order?.branch?.name ?? ''}</p>
                                    <p className="text-700 small">{assignment.order?.branch?.address ?? ''}</p>
                                    <p className="mb-1">
                                        Status: <span className="badge bg-info text-dark">{assignment.status}</span>
                                    </p>
                                    <p className="mb-3 text-700">
                                        {assignment.distance_km != null ? `${Number(assignment.distance_km).toFixed(1)} km · ` : ''}
                                        Fee: {assignment.delivery_fee != null ? `${Number(assignment.delivery_fee).toFixed(2)} SAR` : '—'}
                                    </p>

                                    {assignment.status === 'accepted' && (
                                        tracking ? (
                                            <button type="button" className="btn btn-outline-danger" onClick={stopWatching}>
                                                Stop Sharing Location
                                            </button>
                                        ) : (
                                            <button type="button" className="btn btn-primary" onClick={startWatching}>
                                                Start Sharing Location
                                            </button>
                                        )
                                    )}
                                </CardBody>
                            </Card>
                        </div>

                        <div className="col-lg-6">
                            <Card>
                                <CardBody className="p-4">
                                    <h6 className="fw-bold">Live Position</h6>
                                    {position ? (
                                        <p className="mb-0 font-monospace">
                                            {position.lat.toFixed(6)}, {position.lng.toFixed(6)}
                                        </p>
                                    ) : (
                                        <p className="text-700 mb-0">
                                            {tracking ? 'Waiting for GPS fix…' : 'Location sharing is off.'}
                                        </p>
                                    )}
                                </CardBody>
                            </Card>
                        </div>
                    </div>
                )}
            </div>
        </RiderLayout>
    );
}
