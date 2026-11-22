import { GuestLayout } from '@/components/layout/GuestLayout';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';

const contactOptions = [
    {
        icon: '💬',
        title: 'Customer support',
        description: 'Use your account dashboard for order-specific help and delivery updates.',
        href: '/login',
        linkLabel: 'Go to your account',
    },
    {
        icon: '🍽️',
        title: 'Restaurant partnerships',
        description: 'Register a restaurant account to begin onboarding and menu setup.',
        href: '/register',
        linkLabel: 'Register a restaurant',
    },
    {
        icon: '🛵',
        title: 'Rider opportunities',
        description: 'Create a rider account and complete your profile to start receiving delivery offers.',
        href: '/register',
        linkLabel: 'Become a rider',
    },
];

export default function Contact() {
    return (
        <GuestLayout title="Contact - Alabiq">
            <PageScaffold
                eyebrow="Contact"
                title="How can we help?"
                description="Choose the path that best matches your question so we can route you to the right place."
            >
                <div className="row g-4">
                    {contactOptions.map((option) => (
                        <div key={option.title} className="col-md-6 col-lg-4">
                            <Card variant="outlined" hoverable className="h-100">
                                <CardBody className="p-4">
                                    <div className="fs-2 mb-3" aria-hidden="true">{option.icon}</div>
                                    <h2 className="fs-5 fw-bold text-1000 mb-2">{option.title}</h2>
                                    <p className="text-700 small mb-3">{option.description}</p>
                                    <a href={option.href} className="fw-semibold">{option.linkLabel}</a>
                                </CardBody>
                            </Card>
                        </div>
                    ))}
                </div>
            </PageScaffold>
        </GuestLayout>
    );
}
