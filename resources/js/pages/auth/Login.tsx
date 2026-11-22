import type { FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import type { LoginFormData } from '@/lib/validation';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm<LoginFormData>({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/login', {
            onFinish: () => setData('password', ''),
        });
    };

    return (
        <GuestLayout title="Log In - Alabiq" description="Log in to your Alabiq account.">
            <div className="container py-7">
                <div className="row justify-content-center">
                    <div className="col-md-8 col-lg-5">
                        <Card variant="elevated">
                            <CardBody className="p-4 p-md-5">
                                <div className="text-center mb-4">
                                    <img src="/images/alabiq-icon.png" alt="" height="48" />
                                    <h1 className="fs-3 fw-bold text-1000 mt-3 mb-1">Welcome back</h1>
                                    <p className="text-700 mb-0">Log in to continue your order.</p>
                                </div>

                                <form onSubmit={handleSubmit} noValidate>
                                    <div className="mb-3">
                                        <label htmlFor="email" className="form-label fw-semibold">
                                            Email address
                                        </label>
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
                                            aria-invalid={errors.email ? 'true' : 'false'}
                                            aria-describedby={errors.email ? 'email-error' : undefined}
                                        />
                                        {errors.email && (
                                            <div id="email-error" className="invalid-feedback">
                                                {errors.email}
                                            </div>
                                        )}
                                    </div>

                                    <div className="mb-3">
                                        <div className="d-flex align-items-center justify-content-between">
                                            <label htmlFor="password" className="form-label fw-semibold">
                                                Password
                                            </label>
                                            <Link href="/forgot-password" className="small">
                                                Forgot password?
                                            </Link>
                                        </div>
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            value={data.password}
                                            onChange={(event) => setData('password', event.target.value)}
                                            className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                            autoComplete="current-password"
                                            required
                                            aria-invalid={errors.password ? 'true' : 'false'}
                                            aria-describedby={errors.password ? 'password-error' : undefined}
                                        />
                                        {errors.password && (
                                            <div id="password-error" className="invalid-feedback">
                                                {errors.password}
                                            </div>
                                        )}
                                    </div>

                                    <div className="form-check mb-4">
                                        <input
                                            id="remember"
                                            type="checkbox"
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(event) => setData('remember', event.target.checked)}
                                            className="form-check-input"
                                        />
                                        <label htmlFor="remember" className="form-check-label">
                                            Remember me
                                        </label>
                                    </div>

                                    <Button type="submit" variant="primary" className="w-100" loading={processing}>
                                        Log In
                                    </Button>
                                </form>

                                <div className="text-center mt-4">
                                    <span className="text-700">New to Alabiq? </span>
                                    <Link href="/register">Create an account</Link>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
