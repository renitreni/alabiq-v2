import { usePage } from '@inertiajs/react';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const workflow = [
    { title: 'Review incoming orders', description: 'Accept or reject new customer orders as they arrive.' },
    { title: 'Prepare and update status', description: 'Move accepted orders through preparation so customers and riders stay informed.' },
    { title: 'Coordinate handoff', description: 'Mark orders ready and coordinate pickup with the assigned rider.' },
];

export default function Dashboard() {
    const { auth } = usePage().props as unknown as {
        auth: {
            user?: {
                name: string;
                restaurant?: { name: string; logo?: string | null } | null;
            } | null;
        };
    };
    const restaurant = auth?.user?.restaurant;

    return (
        <RestaurantLayout
            title="Restaurant Dashboard - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Restaurant"
                title={restaurant?.name ?? 'Restaurant Dashboard'}
                description="Manage incoming orders, menu availability, and restaurant operations from one place."
                actions={<Button variant="primary" as="a" href="/restaurant/orders">Review Orders</Button>}
            >
                <div className="row g-4">
                    <div className="col-lg-8">
                        <Card>
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-4">Daily Workflow</h2>
                                <div className="list-group list-group-flush">
                                    {workflow.map((step, index) => (
                                        <div key={step.title} className="list-group-item px-0 d-flex gap-3">
                                            <div className="rounded-circle bg-soft-primary text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style={{ width: 36, height: 36 }}>
                                                {index + 1}
                                            </div>
                                            <div>
                                                <h3 className="fs-6 fw-bold text-1000 mb-1">{step.title}</h3>
                                                <p className="text-700 small mb-0">{step.description}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-4">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Quick Links</h2>
                                <div className="list-group list-group-flush">
                                    <a href="/restaurant/menu" className="list-group-item list-group-item-action px-0">Manage menu</a>
                                    <a href="/restaurant/profile" className="list-group-item list-group-item-action px-0">Restaurant profile</a>
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
