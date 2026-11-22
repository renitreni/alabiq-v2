import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SuperAdminLayout } from '@/components/layout/SuperAdminLayout';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

interface Stats {
    users?: { total: number; by_role: Record<string, number> };
    riders?: { total: number; online: number; delivering_now: number };
    orders?: { total: number; today: number; active: number; delivered: number };
    gmv?: { delivered_total: number; refunded_total: number };
    ledger?: { platform_commission: number; order_clearing: number; settlement_payout: number };
    settlements?: { pending_count: number; pending_amount: number };
}

function StatCard({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
    return (
        <Card>
            <CardBody className="p-3 p-md-4">
                <div className="text-700 small text-uppercase fw-bold">{label}</div>
                <div className="fs-4 fw-bolder">{value}</div>
                {hint && <div className="small text-700">{hint}</div>}
            </CardBody>
        </Card>
    );
}

export default function Dashboard() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [stats, setStats] = useState<Stats>({});
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        api.get('/admin/stats').then(({ data }) => setStats(data?.data ?? {})).catch(() => setError('Could not load statistics.'));
    }, []);

    const money = (v: number | undefined) => (v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return (
        <SuperAdminLayout title="Admin Dashboard - Alabiq" admin={auth?.user}>
            <div className="container py-4">
                <h4 className="fw-bold mb-3">Platform Overview</h4>
                {error && <div className="alert alert-danger">{error}</div>}

                <div className="row g-3">
                    <div className="col-6 col-md-3"><StatCard label="Total Users" value={stats.users?.total ?? 0} /></div>
                    <div className="col-6 col-md-3"><StatCard label="Riders Online" value={stats.riders?.online ?? 0} hint={`${stats.riders?.total ?? 0} total`} /></div>
                    <div className="col-6 col-md-3"><StatCard label="Active Orders" value={stats.orders?.active ?? 0} hint={`${stats.orders?.today ?? 0} today`} /></div>
                    <div className="col-6 col-md-3"><StatCard label="Delivered Orders" value={stats.orders?.delivered ?? 0} /></div>
                    <div className="col-6 col-md-3"><StatCard label="GMV (Delivered)" value={`${money(stats.gmv?.delivered_total)} SAR`} /></div>
                    <div className="col-6 col-md-3"><StatCard label="Platform Commission" value={`${money(stats.ledger?.platform_commission)} SAR`} /></div>
                    <div className="col-6 col-md-3">
                        <StatCard
                            label="Pending Settlements"
                            value={stats.settlements?.pending_count ?? 0}
                            hint={`${money(stats.settlements?.pending_amount)} SAR owed`}
                        />
                    </div>
                </div>

                <div className="row g-4 mt-2">
                    <div className="col-lg-6">
                        <Card>
                            <CardBody className="p-4">
                                <h6 className="fw-bold">Users by Role</h6>
                                <ul className="list-group list-group-flush">
                                    {Object.entries(stats.users?.by_role ?? {}).map(([role, count]) => (
                                        <li key={role} className="list-group-item px-0 d-flex justify-content-between">
                                            <span className="text-capitalize">{role.replace('_', ' ')}</span>
                                            <span className="fw-semibold">{count}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-6">
                        <Card>
                            <CardBody className="p-4">
                                <h6 className="fw-bold">Quick Actions</h6>
                                <div className="list-group list-group-flush">
                                    <a href="/admin/reports" className="list-group-item list-group-item-action px-0">Manage settlements &amp; payouts</a>
                                    <a href="/admin/users" className="list-group-item list-group-item-action px-0">Manage users &amp; roles</a>
                                    <a href="/admin/settings" className="list-group-item list-group-item-action px-0">Platform settings</a>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}
