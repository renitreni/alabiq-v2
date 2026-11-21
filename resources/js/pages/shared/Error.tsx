import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';

interface ErrorPageProps {
    status?: number;
    message?: string;
}

export default function ErrorPage({ status = 500, message }: ErrorPageProps) {
    return (
        <GuestLayout title="Error - Alabiq">
            <div className="min-vh-100 d-flex align-items-center justify-content-center px-4">
                <div className="text-center">
                    <h1 className="display-1 fw-bold text-[var(--alabiq-danger)] mb-3">{status}</h1>
                    <h2 className="fw-bold text-[var(--alabiq-gray-900)] mb-3">
                        {status === 500 ? 'Server Error' : 'Something Went Wrong'}
                    </h2>
                    <p className="text-[var(--alabiq-gray-600)] mb-4" style={{ maxWidth: 400, margin: '0 auto 1.5rem' }}>
                        {message || 'An unexpected error occurred. Our team has been notified and we\'re working on fixing it.'}
                    </p>
                    <div className="d-flex gap-3 justify-content-center flex-wrap">
                        <Button variant="primary" onClick={() => window.location.reload()}>Refresh Page</Button>
                        <Button variant="outline" as="a" href="/">Go Home</Button>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
