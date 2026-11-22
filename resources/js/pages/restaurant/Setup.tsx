import type { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

interface SetupForm {
    name: string;
    slug: string;
    phone: string;
    description: string;
    address: string;
    city: string;
    delivery_fee: string;
    minimum_order: string;
    estimated_delivery_time: string;
}

const slugify = (value: string) =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

export default function Setup() {
    const { data, setData, post, processing, errors } = useForm<SetupForm>({
        name: '',
        slug: '',
        phone: '',
        description: '',
        address: '',
        city: '',
        delivery_fee: '',
        minimum_order: '',
        estimated_delivery_time: '30',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/restaurant/setup');
    };

    return (
        <>
            <Head>
                <title>Restaurant Setup - Alabiq</title>
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <link rel="icon" href="/favicon.ico" />
            </Head>

            <div className="min-vh-100 bg-100 d-flex flex-column">
                <header className="py-3 bg-white border-bottom">
                    <div className="container px-4 d-flex align-items-center">
                        <img src="/images/alabiq-logo-full.png" alt="Alabiq" height="36" />
                    </div>
                </header>

                <main className="flex-1 py-5">
                    <div className="container px-4">
                        <div className="row justify-content-center">
                            <div className="col-lg-8 col-xl-7">
                                <div className="mb-4">
                                    <h1 className="fs-3 fw-bold text-1000 mb-1">Set up your restaurant</h1>
                                    <p className="text-700 mb-0">
                                        This system serves a single restaurant. Add its details to start accepting orders — you can
                                        refine everything later from your dashboard.
                                    </p>
                                </div>

                                <Card variant="elevated">
                                    <CardBody className="p-4 p-md-5">
                                        <form onSubmit={handleSubmit} noValidate>
                                            <div className="row g-3">
                                                <div className="col-md-6">
                                                    <label htmlFor="name" className="form-label fw-semibold">Restaurant name</label>
                                                    <input
                                                        id="name"
                                                        type="text"
                                                        name="name"
                                                        value={data.name}
                                                        onChange={(event) => {
                                                            setData('name', event.target.value);
                                                            setData('slug', slugify(event.target.value));
                                                        }}
                                                        className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                                                        autoComplete="organization"
                                                        autoFocus
                                                        required
                                                    />
                                                    {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                                                </div>
                                                <div className="col-md-6">
                                                    <label htmlFor="slug" className="form-label fw-semibold">Storefront URL slug</label>
                                                    <input
                                                        id="slug"
                                                        type="text"
                                                        name="slug"
                                                        value={data.slug}
                                                        onChange={(event) => setData('slug', slugify(event.target.value))}
                                                        className={`form-control ${errors.slug ? 'is-invalid' : ''}`}
                                                        pattern="[a-z0-9-]+"
                                                        required
                                                    />
                                                    {errors.slug && <div className="invalid-feedback">{errors.slug}</div>}
                                                    <div className="form-text">Lowercase letters, numbers, and hyphens only.</div>
                                                </div>
                                                <div className="col-md-6">
                                                    <label htmlFor="phone" className="form-label fw-semibold">Phone</label>
                                                    <input
                                                        id="phone"
                                                        type="tel"
                                                        name="phone"
                                                        value={data.phone}
                                                        onChange={(event) => setData('phone', event.target.value)}
                                                        className={`form-control ${errors.phone ? 'is-invalid' : ''}`}
                                                        autoComplete="tel"
                                                        required
                                                    />
                                                    {errors.phone && <div className="invalid-feedback">{errors.phone}</div>}
                                                </div>
                                                <div className="col-md-6">
                                                    <label htmlFor="city" className="form-label fw-semibold">City</label>
                                                    <input
                                                        id="city"
                                                        type="text"
                                                        name="city"
                                                        value={data.city}
                                                        onChange={(event) => setData('city', event.target.value)}
                                                        className={`form-control ${errors.city ? 'is-invalid' : ''}`}
                                                        autoComplete="address-level2"
                                                        required
                                                    />
                                                    {errors.city && <div className="invalid-feedback">{errors.city}</div>}
                                                </div>
                                                <div className="col-12">
                                                    <label htmlFor="address" className="form-label fw-semibold">Street address</label>
                                                    <input
                                                        id="address"
                                                        type="text"
                                                        name="address"
                                                        value={data.address}
                                                        onChange={(event) => setData('address', event.target.value)}
                                                        className={`form-control ${errors.address ? 'is-invalid' : ''}`}
                                                        autoComplete="street-address"
                                                        required
                                                    />
                                                    {errors.address && <div className="invalid-feedback">{errors.address}</div>}
                                                </div>
                                                <div className="col-12">
                                                    <label htmlFor="description" className="form-label fw-semibold">Description <span className="text-600 fw-normal">(optional)</span></label>
                                                    <textarea
                                                        id="description"
                                                        name="description"
                                                        value={data.description}
                                                        onChange={(event) => setData('description', event.target.value)}
                                                        className={`form-control ${errors.description ? 'is-invalid' : ''}`}
                                                        rows={3}
                                                    />
                                                    {errors.description && <div className="invalid-feedback">{errors.description}</div>}
                                                </div>
                                                <div className="col-md-4">
                                                    <label htmlFor="delivery_fee" className="form-label fw-semibold">Delivery fee (SAR)</label>
                                                    <input
                                                        id="delivery_fee"
                                                        type="number"
                                                        name="delivery_fee"
                                                        min="0"
                                                        step="0.01"
                                                        value={data.delivery_fee}
                                                        onChange={(event) => setData('delivery_fee', event.target.value)}
                                                        className={`form-control ${errors.delivery_fee ? 'is-invalid' : ''}`}
                                                    />
                                                    {errors.delivery_fee && <div className="invalid-feedback">{errors.delivery_fee}</div>}
                                                </div>
                                                <div className="col-md-4">
                                                    <label htmlFor="minimum_order" className="form-label fw-semibold">Minimum order (SAR)</label>
                                                    <input
                                                        id="minimum_order"
                                                        type="number"
                                                        name="minimum_order"
                                                        min="0"
                                                        step="0.01"
                                                        value={data.minimum_order}
                                                        onChange={(event) => setData('minimum_order', event.target.value)}
                                                        className={`form-control ${errors.minimum_order ? 'is-invalid' : ''}`}
                                                    />
                                                    {errors.minimum_order && <div className="invalid-feedback">{errors.minimum_order}</div>}
                                                </div>
                                                <div className="col-md-4">
                                                    <label htmlFor="estimated_delivery_time" className="form-label fw-semibold">Est. delivery (min)</label>
                                                    <input
                                                        id="estimated_delivery_time"
                                                        type="number"
                                                        name="estimated_delivery_time"
                                                        min="1"
                                                        max="120"
                                                        value={data.estimated_delivery_time}
                                                        onChange={(event) => setData('estimated_delivery_time', event.target.value)}
                                                        className={`form-control ${errors.estimated_delivery_time ? 'is-invalid' : ''}`}
                                                    />
                                                    {errors.estimated_delivery_time && <div className="invalid-feedback">{errors.estimated_delivery_time}</div>}
                                                </div>
                                            </div>

                                            <Button type="submit" variant="primary" className="w-100 mt-4" loading={processing}>
                                                Create My Restaurant
                                            </Button>
                                        </form>
                                    </CardBody>
                                </Card>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
