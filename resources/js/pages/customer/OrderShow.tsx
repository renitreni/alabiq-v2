import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useParams } from '@/hooks/useParams';
import { CustomerLayout } from '@/components/layout/CustomerLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { useMapbox } from '@/hooks/useMapbox';
import api from '@/lib/axios';

interface TrackingEvent {
    id: number;
    event: string;
    created_at: string;
}

interface TrackingPayload {
    order_id: number;
    status: string;
    location?: { latitude: number; longitude: number; recorded_at?: string } | null;
    eta_minutes?: number | null;
    restaurant?: { name?: string; latitude: number; longitude: number } | null;
    rider?: { id: number; name?: string; vehicle_type: string } | null;
    assignment?: { status: string } | null;
    events: TrackingEvent[];
}

const STATUS_FLOW: Record<string, string> = {
    pending_payment: 'Awaiting payment',
    placed: 'Order placed',
    accepted: 'Restaurant accepted',
    preparing: 'Preparing your food',
    ready: 'Ready for pickup',
    rider_assigned: 'Rider assigned',
    picked_up: 'Picked up',
    on_the_way: 'On the way',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    refunded: 'Refunded',
};

export default function OrderShow() {
    const { order: orderParam } = useParams<{ order: string }>();
    const { auth, app } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
        app: { mapbox_token?: string | null };
    };

    const mapToken = app?.mapbox_token ?? null;
    const { initMap, addMarker, centerOn, map } = useMapbox({ accessToken: mapToken ?? '' });

    const [tracking, setTracking] = useState<TrackingPayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [unavailable, setUnavailable] = useState(false);
    const mapContainerRef = useRef<HTMLDivElement>(null);
    const markerRef = useRef<any>(null);
    const restaurantMarkerRef = useRef<any>(null);
    const failures = useRef(0);

    const load = useCallback(async () => {
        try {
            const { data } = await api.get(`/orders/${orderParam}/tracking`);
            setTracking(data?.data ?? null);
            failures.current = 0;
            setError(null);
        } catch (e: any) {
            if (e?.response?.status === 403) {
                setUnavailable(true);
                return;
            }
            // Tracking polls every 4s; ignore a single blip and only surface persistent failures.
            failures.current += 1;
            if (failures.current >= 2) {
                const status = e?.response?.status;
                setError(`Could not load tracking information${status ? ` (error ${status})` : ' (network problem)'}.`);
            }
        }
    }, [orderParam]);

    useEffect(() => {
        load();
        const t = setInterval(load, 4000);
        return () => clearInterval(t);
    }, [load]);

    useEffect(() => {
        if (!mapToken || !mapContainerRef.current || map) return;
        initMap(mapContainerRef.current, { zoom: 13 });
    }, [mapToken, map, initMap]);

    useEffect(() => {
        const loc = tracking?.location;
        if (!loc || !mapToken) return;

        const coords = { lng: Number(loc.longitude), lat: Number(loc.latitude) };
        if (!markerRef.current) {
            markerRef.current = addMarker(coords, { color: '#E53E3E' });
        } else {
            markerRef.current.setLngLat([coords.lng, coords.lat]);
        }
        centerOn(coords, 14);
    }, [tracking?.location, mapToken, addMarker, centerOn]);

    useEffect(() => {
        const origin = tracking?.restaurant;
        if (!origin || !map || !window.mapboxgl) return;

        const lngLat: [number, number] = [Number(origin.longitude), Number(origin.latitude)];
        if (!restaurantMarkerRef.current) {
            const el = document.createElement('div');
            el.title = origin.name ? `${origin.name} (pickup)` : 'Restaurant (pickup)';
            el.style.cssText =
                'width:36px;height:36px;border-radius:50%;background:#2563EB;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:18px;';
            el.textContent = '🍴';
            restaurantMarkerRef.current = new window.mapboxgl.Marker({ element: el })
                .setLngLat(lngLat)
                .setPopup(new window.mapboxgl.Popup({ offset: 20 }).setText(origin.name ?? 'Restaurant'))
                .addTo(map);
            if (!tracking?.location) centerOn({ lng: lngLat[0], lat: lngLat[1] }, 14);
        } else {
            restaurantMarkerRef.current.setLngLat(lngLat);
        }
    }, [tracking?.restaurant, tracking?.location, map, centerOn]);

    if (unavailable) {
        return (
            <CustomerLayout title="Order - Alabiq" user={auth?.user}>
                <div className="container py-4">
                    <div className="alert alert-warning">You do not have access to this order.</div>
                </div>
            </CustomerLayout>
        );
    }

    const isActive = tracking && !['delivered', 'cancelled', 'refunded'].includes(tracking.status);

    return (
        <CustomerLayout title="Track Order - Alabiq" user={auth?.user}>
            <div className="container py-4">
                {error && <div className="alert alert-danger">{error}</div>}

                <div className="row g-4">
                    <div className="col-lg-5">
                        <Card>
                            <CardBody className="p-4">
                                <h5 className="fw-bold">Order #{orderParam}</h5>
                                {tracking ? (
                                    <>
                                        <p className="mb-1">
                                            Status: <span className="badge bg-primary">{STATUS_FLOW[tracking.status] ?? tracking.status}</span>
                                        </p>
                                        {tracking.rider && (
                                            <p className="mb-1 text-700">
                                                Rider: {tracking.rider.name ?? `Rider #${tracking.rider.id}`} ({tracking.rider.vehicle_type})
                                            </p>
                                        )}
                                        {tracking.eta_minutes != null && isActive && (
                                            <p className="mb-1 text-700">Estimated arrival: ~{tracking.eta_minutes} min</p>
                                        )}
                                        {tracking.location && (
                                            <p className="mb-0 font-monospace small text-700">
                                                {Number(tracking.location.latitude).toFixed(5)}, {Number(tracking.location.longitude).toFixed(5)}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <p className="text-700 mb-0">Loading…</p>
                                )}
                            </CardBody>
                        </Card>

                        <Card className="mt-4">
                            <CardBody className="p-4">
                                <h6 className="fw-bold">Recent Events</h6>
                                {tracking?.events?.length ? (
                                    <ul className="list-group list-group-flush">
                                        {tracking.events.slice(0, 8).map((e) => (
                                            <li key={e.id} className="list-group-item px-0 d-flex justify-content-between">
                                                <span className="text-capitalize">{e.event.replace('_', ' ')}</span>
                                                <span className="text-700 small">{new Date(e.created_at).toLocaleTimeString()}</span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-700 mb-0">No events yet.</p>
                                )}
                            </CardBody>
                        </Card>
                    </div>

                    <div className="col-lg-7">
                        <Card>
                            <CardBody className="p-0">
                                {mapToken ? (
                                    <div ref={mapContainerRef} style={{ height: 420, borderRadius: '0.375rem' }} />
                                ) : (
                                    <div className="p-4 text-center text-700">
                                        Map preview is unavailable (no map token configured), but live coordinates still update above.
                                    </div>
                                )}
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </CustomerLayout>
    );
}
