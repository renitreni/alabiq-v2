import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const values = [
    { title: 'Local first', description: 'Help customers discover independent restaurants and local favorites.' },
    { title: 'Operational clarity', description: 'Give restaurants, riders, and administrators tools that make each step visible.' },
    { title: 'Reliable delivery', description: 'Keep customers informed with clear statuses, ETAs, and tracking.' },
];

export default function About() {
    return (
        <GuestLayout title="About - Alabiq">
            <PageScaffold
                eyebrow="About Alabiq"
                title="A multi-restaurant food delivery marketplace"
                description="Alabiq brings restaurants, riders, and customers together in one coordinated platform."
            >
                <div className="row g-4">
                    <div className="col-lg-7">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Our mission</h2>
                                <p className="text-700">
                                    Alabiq is designed to make ordering food simple for customers while giving restaurants
                                    and riders practical tools to manage busy delivery operations. The platform focuses on
                                    clear order states, flexible marketplace management, and delivery visibility from checkout
                                    to doorstep.
                                </p>
                                <p className="text-700 mb-0">
                                    Customers can browse nearby restaurants, place orders, and track progress. Restaurant
                                    teams can manage menus and incoming orders, riders can coordinate deliveries, and platform
                                    administrators can oversee the health of the marketplace.
                                </p>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-5">
                        <div className="d-flex flex-column gap-3">
                            {values.map((value) => (
                                <Card key={value.title}>
                                    <CardBody className="p-3">
                                        <h3 className="fs-6 fw-bold text-1000 mb-1">{value.title}</h3>
                                        <p className="text-700 small mb-0">{value.description}</p>
                                    </CardBody>
                                </Card>
                            ))}
                        </div>
                    </div>
                </div>
            </PageScaffold>
        </GuestLayout>
    );
}
