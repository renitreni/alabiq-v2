import { usePage } from '@inertiajs/react';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const settingsAreas = [
    { title: 'Branch availability', description: 'Configure opening hours, order acceptance, delivery coverage, and estimated delivery times.' },
    { title: 'Payments and fees', description: 'Review payment methods, delivery fees, minimum order values, and settlement preferences.' },
    { title: 'Notifications', description: 'Control alerts for new orders, cancellations, rider assignments, and operational updates.' },
];

export default function Settings() {
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
            title="Restaurant Settings - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: auth?.user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Settings"
                title="Restaurant Settings"
                description="Configure restaurant operations once the settings endpoints are connected."
            >
                <div className="row g-4">
                    {settingsAreas.map((area) => (
                        <div key={area.title} className="col-md-6 col-lg-4">
                            <Card variant="outlined" className="h-100">
                                <CardBody className="p-4">
                                    <h2 className="fs-5 fw-bold text-1000 mb-2">{area.title}</h2>
                                    <p className="text-700 small mb-0">{area.description}</p>
                                </CardBody>
                            </Card>
                        </div>
                    ))}
                </div>
            </PageScaffold>
        </RestaurantLayout>
    );
}
