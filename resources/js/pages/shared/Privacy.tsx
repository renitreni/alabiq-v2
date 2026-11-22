import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const sections = [
    {
        title: 'Information we collect',
        body: 'We collect account details, delivery addresses, order history, payment status, and operational data needed to provide the marketplace service.',
    },
    {
        title: 'How information is used',
        body: 'Information is used to process orders, coordinate delivery, manage restaurant and rider operations, prevent fraud, and improve the platform.',
    },
    {
        title: 'Service providers',
        body: 'Operational data may be shared with restaurants, riders, payment providers, and infrastructure providers only as needed to complete a service request.',
    },
    {
        title: 'Retention and security',
        body: 'Data is retained while an account is active or as required for legal, accounting, fraud-prevention, and dispute-resolution purposes.',
    },
];

export default function Privacy() {
    return (
        <GuestLayout title="Privacy Policy - Alabiq">
            <PageScaffold
                eyebrow="Privacy"
                title="Privacy Policy"
                description="A clear summary of how Alabiq handles personal and operational information."
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
