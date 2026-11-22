import { usePage } from '@inertiajs/react';
import { RiderLayout } from '@/components/layout/RiderLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

interface AuthUser {
    id: number | string;
    name: string;
    email: string;
    avatar?: string | null;
    rider?: {
        id: number | string;
        is_online: boolean;
        vehicle_type: string;
    } | null;
}

export default function Profile() {
    const { auth } = usePage().props as unknown as { auth: { user?: AuthUser | null } };
    const user = auth?.user;
    const rider = user?.rider;

    return (
        <RiderLayout
            title="Rider Profile - Alabiq"
            rider={{ name: user?.name ?? 'Rider', avatar: user?.avatar ?? undefined, isOnline: rider?.is_online ?? false }}
        >
            <PageScaffold
                eyebrow="Profile"
                title="Rider Profile"
                description="Review the account and vehicle details used for delivery assignments."
            >
                <div className="row g-4">
                    <div className="col-lg-7">
                        <Card>
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Account Details</h2>
                                <dl className="row mb-0">
                                    <dt className="col-sm-3 text-700">Name</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{user?.name ?? 'Not available'}</dd>
                                    <dt className="col-sm-3 text-700">Email</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{user?.email ?? 'Not available'}</dd>
                                    <dt className="col-sm-3 text-700">Status</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{rider?.is_online ? 'Online' : 'Offline'}</dd>
                                </dl>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-5">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Vehicle</h2>
                                <dl className="row mb-0">
                                    <dt className="col-sm-4 text-700">Type</dt>
                                    <dd className="col-sm-8 fw-semibold text-1000 text-capitalize">{rider?.vehicle_type ?? 'Not linked yet'}</dd>
                                    <dt className="col-sm-4 text-700">Rider ID</dt>
                                    <dd className="col-sm-8 fw-semibold text-1000">{rider?.id ?? 'Not linked yet'}</dd>
                                </dl>
                                <div className="list-group list-group-flush mt-4">
                                    <a href="/rider/dashboard" className="list-group-item list-group-item-action px-0">Dashboard</a>
                                    <a href="/rider/deliveries" className="list-group-item list-group-item-action px-0">Deliveries</a>
                                    <a href="/rider/earnings" className="list-group-item list-group-item-action px-0">Earnings</a>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </PageScaffold>
        </RiderLayout>
    );
}
