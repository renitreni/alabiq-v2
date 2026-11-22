import { usePage } from '@inertiajs/react';
import { CustomerLayout } from '@/components/layout/CustomerLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

interface AuthUser {
    id: number | string;
    name: string;
    email: string;
    role: string;
    avatar?: string | null;
    created_at?: string;
}

export default function Profile() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: AuthUser | null };
    };
    const user = auth?.user;

    return (
        <CustomerLayout title="Profile - Alabiq" user={user ? { name: user.name, avatar: user.avatar ?? undefined } : null}>
            <PageScaffold
                eyebrow="Profile"
                title="Account Profile"
                description="Review the account details used across your customer experience."
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
                                    <dt className="col-sm-3 text-700">Role</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000 text-capitalize">{user?.role?.replace('_', ' ') ?? 'Customer'}</dd>
                                    <dt className="col-sm-3 text-700">Member since</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">
                                        {user?.created_at ? new Date(user.created_at).toLocaleDateString() : 'Not available'}
                                    </dd>
                                </dl>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-5">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Quick Links</h2>
                                <div className="list-group list-group-flush">
                                    <a href="/customer/orders" className="list-group-item list-group-item-action px-0">Order history</a>
                                    <a href="/customer/addresses" className="list-group-item list-group-item-action px-0">Delivery addresses</a>
                                    <a href="/customer/cart" className="list-group-item list-group-item-action px-0">Current cart</a>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </PageScaffold>
        </CustomerLayout>
    );
}
