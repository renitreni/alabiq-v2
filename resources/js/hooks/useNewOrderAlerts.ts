import { useCallback, useEffect, useRef, useState } from 'react';
import api from '@/lib/axios';

export interface AvailableOrder {
    id: number;
    status: string;
    total_amount: string | number;
    restaurant?: { name: string } | null;
}

const POLL_MS = 10000;

export const beep = () => {
    try {
        const Ctx = window.AudioContext ?? (window as any).webkitAudioContext;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        osc.frequency.value = 880;
        osc.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch {
        // audio is best-effort
    }
};

const logCurrentLocation = () => {
    if (!('geolocation' in navigator)) {
        return;
    }
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            api.post('/rider/location', {
                latitude: coords.latitude,
                longitude: coords.longitude,
                speed: coords.speed != null && coords.speed >= 0 ? coords.speed : null,
                heading: coords.heading != null && !Number.isNaN(coords.heading) ? coords.heading : null,
                accuracy: coords.accuracy,
                force: true,
            }).catch(() => undefined);
        },
        () => undefined,
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
    );
};

/**
 * While the rider is online and the app is visible, polls for orders waiting
 * for a rider. Each newly seen order raises a notification and logs the
 * rider's current location to the server.
 */
export function useNewOrderAlerts(isOnline: boolean) {
    const [orders, setOrders] = useState<AvailableOrder[]>([]);
    const [alertOrders, setAlertOrders] = useState<AvailableOrder[]>([]);
    const seen = useRef<Set<number> | null>(null);

    const poll = useCallback(async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }
        try {
            const { data } = await api.get('/rider/available-orders');
            const list: AvailableOrder[] = data?.data ?? [];
            setOrders(list);

            // First poll only primes the baseline so existing orders don't alert.
            if (seen.current === null) {
                seen.current = new Set(list.map((o) => o.id));
                return;
            }

            const fresh = list.filter((o) => !seen.current!.has(o.id));
            list.forEach((o) => seen.current!.add(o.id));
            if (fresh.length === 0) {
                return;
            }

            setAlertOrders((prev) => [...fresh, ...prev]);
            beep();
            logCurrentLocation();
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('New order available', {
                    body: fresh.length === 1
                        ? `Order #${fresh[0].id}${fresh[0].restaurant?.name ? ` from ${fresh[0].restaurant.name}` : ''}`
                        : `${fresh.length} new orders are waiting for a rider`,
                });
            }
        } catch {
            // transient network errors: try again on next tick
        }
    }, []);

    useEffect(() => {
        if (!isOnline) {
            seen.current = null;
            setOrders([]);
            setAlertOrders([]);
            return;
        }

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => undefined);
        }

        poll();
        const timer = setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', poll);
        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', poll);
        };
    }, [isOnline, poll]);

    const dismiss = useCallback(() => setAlertOrders([]), []);

    return { orders, alertOrders, dismiss };
}
