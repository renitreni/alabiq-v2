import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface Settlement {
    id: number;
    restaurant?: { name: string };
    period_start: string;
    period_end: string;
    orders_count: number;
    gross_amount: string | number;
    commission_amount: string | number;
    net_amount: string | number;
    status: 'pending' | 'paid';
    paid_at: string | null;
    reference: string;
}

interface AuditEntry {
    id: number;
    action: string;
    user?: { name: string } | null;
    created_at: string;
}

const money = (v: string | number) => Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Reports() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [settlements, setSettlements] = useState<Settlement[]>([]);
    const [audits, setAudits] = useState<AuditEntry[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [form, setForm] = useState({ restaurant_id: '', period_start: '', period_end: '' });

    const load = useCallback(async () => {
        try {
            const [s, a] = await Promise.all([
                api.get('/admin/settlements'),
                api.get('/admin/audit-logs'),
            ]);
            setSettlements(s.data?.data?.data ?? []);
            setAudits(a.data?.data ?? []);
        } catch {
            setError('Could not load reports.');
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const generate = async () => {
        setError(null);
        try {
            await api.post('/admin/settlements/generate', {
                restaurant_id: Number(form.restaurant_id),
                period_start: form.period_start,
                period_end: form.period_end,
            });
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not generate settlement.');
        }
    };

    const pay = async (s: Settlement) => {
        setBusyId(s.id);
        setError(null);
        try {
            await api.post(`/admin/settlements/${s.id}/pay`);
            await load();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Payout failed.');
        } finally {
            setBusyId(null);
        }
    };

    return (
        <SuperAdminLayout title="Reports & Settlements - Alabiq Admin" admin={auth?.user}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">Settlements &amp; Audit Trail</h4>
                {error && <div className="alert alert-danger">{error}</div>}

                <Card className="mb-4">
                    <CardBody className="p-4">
                        <h6 className="fw-bold">Generate Settlement</h6>
                        <div className="row g-2 align-items-end">
                            <div className="col-md-3">
                                <label className="form-label small">Restaurant ID</label>
                                <input type="number" className="form-control" value={form.restaurant_id} onChange={(e) => setForm({ ...form, restaurant_id: e.target.value })} />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label small">Period start</label>
                                <input type="date" className="form-control" value={form.period_start} onChange={(e) => setForm({ ...form, period_start: e.target.value })} />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label small">Period end</label>
                                <input type="date" className="form-control" value={form.period_end} onChange={(e) => setForm({ ...form, period_end: e.target.value })} />
                            </div>
                            <div className="col-md-3">
                                <button type="button" className="btn btn-primary w-100" disabled={!form.restaurant_id || !form.period_start || !form.period_end} onClick={generate}>
                                    Generate
                                </button>
                            </div>
                        </div>
                    </CardBody>
                </Card>

                <Card className="mb-4">
                    <CardBody className="p-4">
                        <h6 className="fw-bold">Settlements ({settlements.length})</h6>
                        <div className="list-group list-group-flush">
                            {settlements.map((s) => (
                                <div key={s.id} className="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <div className="fw-semibold">{s.restaurant?.name ?? `Restaurant #${s.id}`}</div>
                                        <div className="small text-700">
                                            {s.period_start} → {s.period_end} · {s.orders_count} orders · Gross {money(s.gross_amount)} SAR
                                            · Commission {money(s.commission_amount)} · Net <strong>{money(s.net_amount)} SAR</strong>
                                        </div>
                                        <div className="small text-700 font-monospace">{s.reference}</div>
                                    </div>
                                    <div className="d-flex align-items-center gap-2">
                                        <span className={`badge ${s.status === 'paid' ? 'bg-success' : 'bg-warning text-dark'}`}>
                                            {s.status === 'paid' ? `Paid ${s.paid_at ? new Date(s.paid_at).toLocaleDateString() : ''}` : 'Pending'}
                                        </span>
                                        {s.status === 'pending' && (
                                            <button type="button" className="btn btn-success btn-sm" disabled={busyId === s.id} onClick={() => pay(s)}>
                                                Pay Now
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {settlements.length === 0 && <p className="text-700 mb-0">No settlements yet.</p>}
                        </div>
                    </CardBody>
                </Card>

                <Card>
                    <CardBody className="p-4">
                        <h6 className="fw-bold">Recent Audit Events ({audits.length})</h6>
                        <div className="list-group list-group-flush">
                            {audits.slice(0, 20).map((a) => (
                                <div key={a.id} className="list-group-item px-0 d-flex justify-content-between">
                                    <span>
                                        <span className="font-monospace small">{a.action}</span>
                                        <span className="text-700 small"> · {a.user?.name ?? 'System'}</span>
                                    </span>
                                    <span className="text-700 small">{new Date(a.created_at).toLocaleString()}</span>
                                </div>
                            ))}
                        </div>
                    </CardBody>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
