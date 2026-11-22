import type { FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { GuestLayout } from '@/components/layout/GuestLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: 'customer' | 'rider';
}

export default function Register() {
    const { data, setData, post, processing, errors } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'customer',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/register');
    };

    return (
        <GuestLayout title="Create Account - Alabiq" description="Create your Alabiq account.">
            <div className="container py-7">
                <div className="row justify-content-center">
                    <div className="col-md-9 col-lg-6">
                        <Card variant="elevated">
                            <CardBody className="p-4 p-md-5">
                                <div className="text-center mb-4">
                                    <img src="/images/alabiq-icon.png" alt="" height="48" />
                                    <h1 className="fs-3 fw-bold text-1000 mt-3 mb-1">Create your account</h1>
                                    <p className="text-700 mb-0">Start ordering, selling, or delivering with Alabiq.</p>
                                </div>

                                <form onSubmit={handleSubmit} noValidate>
                                    <div className="mb-3">
                                        <label htmlFor="role" className="form-label fw-semibold">Account type</label>
                                        <select
                                            id="role"
                                            name="role"
                                            value={data.role}
                                            onChange={(event) => setData('role', event.target.value as RegisterForm['role'])}
                                            className={`form-select ${errors.role ? 'is-invalid' : ''}`}
                                        >
                                            <option value="customer">Customer</option>
                                            <option value="rider">Rider</option>
                                        </select>
                                        {errors.role && <div className="invalid-feedback">{errors.role}</div>}
                                    </div>

                                    <div className="mb-3">
                                        <label htmlFor="name" className="form-label fw-semibold">Full name</label>
                                        <input
                                            id="name"
                                            type="text"
                                            name="name"
                                            value={data.name}
                                            onChange={(event) => setData('name', event.target.value)}
                                            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                                            autoComplete="name"
                                            autoFocus
                                            required
                                        />
                                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                                    </div>

                                    <div className="mb-3">
                                        <label htmlFor="email" className="form-label fw-semibold">Email address</label>
                                        <input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            onChange={(event) => setData('email', event.target.value)}
                                            className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                                            autoComplete="email"
                                            required
                                        />
                                        {errors.email && <div className="invalid-feedback">{errors.email}</div>}
                                    </div>

                                    <div className="row g-3">
                                        <div className="col-md-6 mb-3 mb-md-0">
                                            <label htmlFor="password" className="form-label fw-semibold">Password</label>
                                            <input
                                                id="password"
                                                type="password"
                                                name="password"
                                                value={data.password}
                                                onChange={(event) => setData('password', event.target.value)}
                                                className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                                autoComplete="new-password"
                                                required
                                            />
                                            {errors.password && <div className="invalid-feedback">{errors.password}</div>}
                                        </div>
                                        <div className="col-md-6">
                                            <label htmlFor="password_confirmation" className="form-label fw-semibold">Confirm password</label>
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
                                    </div>

                                    <Button type="submit" variant="primary" className="w-100 mt-4" loading={processing}>
                                        Create Account
                                    </Button>
                                </form>

                                <div className="text-center mt-4">
                                    <span className="text-700">Already have an account? </span>
                                    <Link href="/login">Log in</Link>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
