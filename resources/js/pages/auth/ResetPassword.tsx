import type { FormEvent } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

interface ResetPasswordProps {
    token: string;
    email?: string;
}

interface FlashProps {
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { flash } = usePage().props as unknown as FlashProps;
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/reset-password');
    };

    return (
        <GuestLayout title="Set New Password - Alabiq" description="Choose a new account password.">
            <div className="container py-7">
                <div className="row justify-content-center">
                    <div className="col-md-8 col-lg-5">
                        <Card variant="elevated">
                            <CardBody className="p-4 p-md-5">
                                <div className="text-center mb-4">
                                    <h1 className="fs-3 fw-bold text-1000 mb-1">Set a new password</h1>
                                    <p className="text-700 mb-0">Choose a strong password for {data.email}.</p>
                                </div>

                                {flash?.success && <div className="alert alert-success">{flash.success}</div>}
                                {flash?.error && <div className="alert alert-danger">{flash.error}</div>}

                                <form onSubmit={handleSubmit} noValidate>
                                    <div className="mb-3">
                                        <label htmlFor="password" className="form-label fw-semibold">New password</label>
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            value={data.password}
                                            onChange={(event) => setData('password', event.target.value)}
                                            className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                            autoComplete="new-password"
                                            autoFocus
                                            required
                                        />
                                        {errors.password && <div className="invalid-feedback">{errors.password}</div>}
                                    </div>

                                    <div className="mb-4">
                                        <label htmlFor="password_confirmation" className="form-label fw-semibold">Confirm new password</label>
                                        <input
                                            id="password_confirmation"
                                            type="password"
                                            name="password_confirmation"
                                            value={data.password_confirmation}
                                            onChange={(event) => setData('password_confirmation', event.target.value)}
                                            className="form-control"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </div>

                                    {errors.email && <div className="alert alert-danger">{errors.email}</div>}

                                    <Button type="submit" variant="primary" className="w-100" loading={processing}>
                                        Update Password
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
