import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';

export default function NotFound() {
    return (
        <GuestLayout title="Page Not Found - Alabiq">
            <div className="min-vh-100 d-flex align-items-center justify-content-center px-4">
                <div className="text-center">
                    <h1 className="display-1 fw-bold text-primary mb-3">404</h1>
                    <h2 className="fw-bold text-1000 mb-3">Page Not Found</h2>
                    <p className="text-700 mb-4" style={{ maxWidth: 400, margin: '0 auto 1.5rem' }}>
                        Sorry, we couldn't find the page you're looking for. It might have been moved or doesn't exist.
                    </p>
                    <div className="d-flex gap-3 justify-content-center flex-wrap">
                        <Button variant="primary" as="a" href="/">Go Home</Button>
                        <Button variant="outline" as="a" href="/">View Menu</Button>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
