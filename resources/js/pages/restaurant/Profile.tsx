import { usePage } from '@inertiajs/react';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

interface AuthUser {
    id: number | string;
    name: string;
    email: string;
    restaurant?: { id: number | string; name: string; slug: string; logo?: string | null } | null;
}

export default function Profile() {
    const { auth } = usePage().props as unknown as { auth: { user?: AuthUser | null } };
    const user = auth?.user;
    const restaurant = user?.restaurant;

    return (
        <RestaurantLayout
            title="Restaurant Profile - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Profile"
                title="Restaurant Profile"
                description="Review the restaurant account shown to customers and operations teams."
            >
                <div className="row g-4">
                    <div className="col-lg-7">
                        <Card>
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Restaurant Details</h2>
                                <dl className="row mb-0">
                                    <dt className="col-sm-3 text-700">Restaurant</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{restaurant?.name ?? 'Not linked yet'}</dd>
                                    <dt className="col-sm-3 text-700">Owner</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{user?.name ?? 'Not available'}</dd>
                                    <dt className="col-sm-3 text-700">Email</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{user?.email ?? 'Not available'}</dd>
                                    <dt className="col-sm-3 text-700">Slug</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{restaurant?.slug ?? 'Not available'}</dd>
                                </dl>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-5">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Manage</h2>
                                <div className="list-group list-group-flush">
                                    <a href="/restaurant/orders" className="list-group-item list-group-item-action px-0">Restaurant orders</a>
                                    <a href="/restaurant/menu" className="list-group-item list-group-item-action px-0">Menu catalog</a>
                                    <a href="/restaurant/settings" className="list-group-item list-group-item-action px-0">Restaurant settings</a>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </PageScaffold>
        </RestaurantLayout>
    );
}
