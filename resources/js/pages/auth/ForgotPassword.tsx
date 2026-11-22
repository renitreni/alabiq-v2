import type { FormEvent } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

interface FlashProps {
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function ForgotPassword() {
    const { flash } = usePage().props as unknown as FlashProps;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/forgot-password', {
            onFinish: () => reset(),
        });
    };

    return (
        <GuestLayout title="Forgot Password - Alabiq" description="Request a password reset link.">
            <div className="container py-7">
                <div className="row justify-content-center">
                    <div className="col-md-8 col-lg-5">
                        <Card variant="elevated">
                            <CardBody className="p-4 p-md-5">
                                <div className="text-center mb-4">
                                    <h1 className="fs-3 fw-bold text-1000 mb-1">Reset your password</h1>
                                    <p className="text-700 mb-0">Enter your account email and we will send you a reset link.</p>
                                </div>

                                {flash?.success && <div className="alert alert-success">{flash.success}</div>}
                                {flash?.error && <div className="alert alert-danger">{flash.error}</div>}

                                <form onSubmit={handleSubmit} noValidate>
                                    <div className="mb-4">
                                        <label htmlFor="email" className="form-label fw-semibold">Email address</label>
                                        <input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            onChange={(event) => setData('email', event.target.value)}
                                            className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                                            autoComplete="email"
                                            autoFocus
                                            required
                                        />
                                        {errors.email && <div className="invalid-feedback">{errors.email}</div>}
                                    </div>

                                    <Button type="submit" variant="primary" className="w-100" loading={processing}>
                                        Send Reset Link
                                    </Button>
                                </form>

                                <div className="text-center mt-4">
                                    <Link href="/login">Back to login</Link>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
