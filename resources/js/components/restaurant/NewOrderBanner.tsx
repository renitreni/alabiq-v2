import React, { useCallback, useEffect, useRef, useState } from 'react';
import { beep } from '@/hooks/useNewOrderAlerts';
import api from '@/lib/axios';

interface PendingOrder {
    id: number;
    total_amount: number | string;
    created_at: string;
    user?: { name: string } | null;
}

export const ORDERS_CHANGED_EVENT = 'restaurant-orders-changed';

const POLL_MS = 10000;
const MAX_SHOWN = 3;

/**
 * Polls for new (placed) orders while the tab is visible, alerts the owner
 * (sound, browser notification, tab title) and lets them accept or reject
 * without leaving the current page.
 */
export const NewOrderBanner: React.FC = () => {
    const [pending, setPending] = useState<PendingOrder[]>([]);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const seen = useRef<Set<number> | null>(null);
    const baseTitle = useRef(document.title);

    const poll = useCallback(async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }
        try {
            const { data } = await api.get('/restaurant/orders', { params: { status: 'placed' } });
            const list: PendingOrder[] = data?.data ?? [];
            setPending(list);

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

            beep();
            window.dispatchEvent(new Event(ORDERS_CHANGED_EVENT));
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('New order received', {
                    body: fresh.length === 1 ? `Order #${fresh[0].id} is waiting for you` : `${fresh.length} new orders are waiting`,
                });
            }
        } catch {
            // transient network errors: try again on next tick
        }
    }, []);

    useEffect(() => {
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
    }, [poll]);

    useEffect(() => {
        document.title = pending.length ? `(${pending.length}) New order! – ${baseTitle.current}` : baseTitle.current;
    }, [pending.length]);

    const act = async (order: PendingOrder, status: 'accepted' | 'cancelled') => {
        if (status === 'cancelled' && !window.confirm(`Reject order #${order.id}?`)) {
            return;
        }
        setBusyId(order.id);
        setError(null);
        try {
            await api.patch(`/orders/${order.id}/status`, {
                status,
                note: status === 'cancelled' ? 'Restaurant rejected the order' : undefined,
            });
            setPending((prev) => prev.filter((o) => o.id !== order.id));
            window.dispatchEvent(new Event(ORDERS_CHANGED_EVENT));
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not update the order.');
        } finally {
            setBusyId(null);
        }
    };

    if (pending.length === 0) {
        return null;
    }

    return (
        <div className="container px-4 pt-3" role="alert" aria-live="assertive">
            <div className="alert alert-warning mb-0">
                <div className="fw-bold mb-2">
                    {pending.length === 1 ? 'New order waiting for your response' : `${pending.length} new orders waiting for your response`}
                </div>
                {error && <div className="text-danger small mb-2">{error}</div>}
                {pending.slice(0, MAX_SHOWN).map((order) => (
                    <div key={order.id} className="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-top">
                        <a href={`/restaurant/orders/${order.id}`} className="text-decoration-none text-1000">
                            <strong>Order #{order.id}</strong>
                            <span className="small text-700">
                                {' '}· {order.user?.name ?? 'Customer'} · {Number(order.total_amount).toFixed(2)} SAR
                            </span>
                        </a>
                        <span className="d-flex gap-2">
                            <button type="button" className="btn btn-sm btn-success" disabled={busyId !== null} onClick={() => act(order, 'accepted')}>
                                {busyId === order.id ? 'Updating…' : 'Accept'}
                            </button>
                            <button type="button" className="btn btn-sm btn-outline-danger" disabled={busyId !== null} onClick={() => act(order, 'cancelled')}>
                                Reject
                            </button>
                        </span>
                    </div>
                ))}
                {pending.length > MAX_SHOWN && (
                    <a href="/restaurant/orders" className="small d-inline-block mt-2">View all {pending.length} orders</a>
                )}
            </div>
        </div>
    );
};
