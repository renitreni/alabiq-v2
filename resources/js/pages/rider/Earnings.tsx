import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { RiderLayout } from '@/components/layout/RiderLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';
import { StatCard } from '@/components/ui/StatCard';
import api from '@/lib/axios';

interface EarningsPayload {
    completed_deliveries: number;
    earnings: number;
    lifetime_deliveries?: number;
    rating?: number | null;
    period?: {
        from?: string | null;
        to?: string | null;
    };
}

const money = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function Earnings() {
    const { auth } = usePage().props as unknown as {
        auth: {
            user?: {
                name: string;
                avatar?: string | null;
                rider?: { id: number | string; is_online: boolean; vehicle_type: string } | null;
            } | null;
        };
    };
    const user = auth?.user;
    const [earnings, setEarnings] = useState<EarningsPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        api.get('/rider/reports/earnings')
            .then(({ data }) => setEarnings(data?.data ?? null))
            .catch((e: any) => setError(e?.response?.data?.message ?? 'Could not load rider earnings.'))
            .finally(() => setLoading(false));
    }, []);

    return (
        <RiderLayout
            title="Rider Earnings - Alabiq"
            rider={{ name: user?.name ?? 'Rider', avatar: user?.avatar ?? undefined, isOnline: user?.rider?.is_online ?? false }}
        >
            <PageScaffold
                eyebrow="Earnings"
                title="Rider Earnings"
                description="Review completed deliveries, earnings, and performance for your rider account."
                actions={<Button variant="outline" size="sm" as="a" href="/rider/deliveries">Delivery History</Button>}
            >
                {error && <div className="alert alert-danger">{error}</div>}
                {loading ? (
                    <Card>
                        <CardBody className="p-4 text-700">Loading earnings…</CardBody>
                    </Card>
                ) : earnings ? (
                    <>
                        <div className="row g-3">
                            <div className="col-6 col-md-3">
                                <StatCard label="Completed Deliveries" value={earnings.completed_deliveries} />
                            </div>
                            <div className="col-6 col-md-3">
                                <StatCard label="Earnings" value={money(earnings.earnings)} />
                            </div>
                            <div className="col-6 col-md-3">
                                <StatCard label="Lifetime Deliveries" value={earnings.lifetime_deliveries ?? 0} />
                            </div>
                            <div className="col-6 col-md-3">
                                <StatCard label="Rating" value={earnings.rating?.toFixed(1) ?? '—'} />
                            </div>
                        </div>
                        <Card className="mt-4">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-2">Reporting Period</h2>
                                <p className="text-700 mb-0">
                                    {earnings.period?.from || earnings.period?.to
                                        ? `${earnings.period.from ?? 'Start'} to ${earnings.period.to ?? 'Today'}`
                                        : 'All completed deliveries are included.'}
                                </p>
                            </CardBody>
                        </Card>
                    </>
                ) : (
                    <Card>
                        <CardBody className="p-4 text-700">No earnings data is available yet.</CardBody>
                    </Card>
                )}
            </PageScaffold>
        </RiderLayout>
    );
}
