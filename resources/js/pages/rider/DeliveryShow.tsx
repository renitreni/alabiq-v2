import { usePage } from '@inertiajs/react';
import { useParams } from 'react-router-dom';
import { RiderLayout } from '@/components/layout/RiderLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const deliverySteps = [
    'Offer accepted',
    'Traveling to restaurant',
    'Order picked up',
    'On the way',
    'Delivered',
];

export default function DeliveryShow() {
    const { delivery } = useParams<{ delivery: string }>();
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

    return (
        <RiderLayout
            title={`Delivery ${delivery ?? ''} - Alabiq`}
            rider={{ name: user?.name ?? 'Rider', avatar: user?.avatar ?? undefined, isOnline: user?.rider?.is_online ?? false }}
        >
            <PageScaffold
                eyebrow="Delivery"
                title={`Delivery #${delivery ?? ''}`}
                description="Review the delivery workflow and open live tracking when available."
                actions={<Button variant="primary" size="sm" as="a" href={`/rider/tracking/${delivery}`}>Open Tracking</Button>}
            >
                <Card>
                    <CardBody className="p-4">
                        <h2 className="fs-5 fw-bold text-1000 mb-4">Delivery Workflow</h2>
                        <div className="d-flex flex-wrap gap-2">
                            {deliverySteps.map((step, index) => (
                                <span key={step} className={`badge px-3 py-2 ${index === 0 ? 'bg-primary text-white' : 'bg-soft-primary text-primary'}`}>
                                    {index + 1}. {step}
                                </span>
                            ))}
                        </div>
                        <p className="text-700 small mt-4 mb-0">
                            Assignment details, customer notes, and pickup instructions will appear here when the rider assignment feed is connected.
                        </p>
                    </CardBody>
                </Card>
            </PageScaffold>
        </RiderLayout>
    );
}
