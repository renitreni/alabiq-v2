import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const customerSteps = [
    { title: 'Browse local restaurants', description: 'Search by cuisine, dish, rating, or delivery time.' },
    { title: 'Customize your order', description: 'Select options, add instructions, and review your cart before checkout.' },
    { title: 'Track delivery live', description: 'Follow order status and rider location until your food arrives.' },
];

const partnerSteps = [
    { title: 'Restaurants manage orders', description: 'Accept, prepare, and update orders from the restaurant dashboard.' },
    { title: 'Riders receive assignments', description: 'Go online, review nearby offers, and update pickup or delivery status.' },
    { title: 'Admin monitors operations', description: 'Oversee users, approvals, commissions, settlements, and marketplace health.' },
];

export default function HowItWorks() {
    return (
        <GuestLayout title="How It Works - Alabiq">
            <PageScaffold
                eyebrow="How it works"
                title="One marketplace for customers, restaurants, and riders"
                description="Alabiq connects diners with local restaurants and delivery riders through one simple workflow."
                actions={<Button variant="primary" as="a" href="/register">Get Started</Button>}
            >
                <div className="row g-4">
                    {[customerSteps, partnerSteps].map((steps, columnIndex) => (
                        <div key={columnIndex} className="col-lg-6">
                            <Card variant="outlined" className="h-100">
                                <CardBody className="p-4">
                                    <h2 className="fs-5 fw-bold text-1000 mb-4">
                                        {columnIndex === 0 ? 'For customers' : 'For partners'}
                                    </h2>
                                    <div className="list-group list-group-flush">
                                        {steps.map((step, index) => (
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
                    ))}
                </div>
            </PageScaffold>
        </GuestLayout>
    );
}
