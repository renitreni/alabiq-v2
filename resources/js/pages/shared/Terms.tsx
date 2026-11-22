import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const sections = [
    {
        title: 'Using Alabiq',
        body: 'Accounts must provide accurate information and use the platform only for lawful food ordering, restaurant operations, delivery coordination, and administration.',
    },
    {
        title: 'Orders and payments',
        body: 'Orders are confirmed according to restaurant availability, delivery coverage, and payment status. Totals shown at checkout may include item, delivery, tax, and discount adjustments.',
    },
    {
        title: 'Restaurant and rider responsibilities',
        body: 'Restaurants and riders are responsible for keeping menu, availability, and assignment information current, and for completing accepted orders professionally.',
    },
    {
        title: 'Cancellations and disputes',
        body: 'Cancellations, refunds, and order disputes are handled according to order state, platform policy, and applicable law.',
    },
];

export default function Terms() {
    return (
        <GuestLayout title="Terms of Service - Alabiq">
            <PageScaffold
                eyebrow="Terms"
                title="Terms of Service"
                description="The basic terms governing use of the Alabiq marketplace."
            >
                <Card variant="outlined">
                    <CardBody className="p-4">
                        <div className="d-flex flex-column gap-4">
                            {sections.map((section, index) => (
                                <section key={section.title}>
                                    <h2 className="fs-5 fw-bold text-1000 mb-2">
                                        <span className="text-danger me-2">{index + 1}.</span>
                                        {section.title}
                                    </h2>
                                    <p className="text-700 mb-0">{section.body}</p>
                                </section>
                            ))}
                        </div>
                    </CardBody>
                </Card>
            </PageScaffold>
        </GuestLayout>
    );
}
