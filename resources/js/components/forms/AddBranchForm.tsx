import { FormEvent, useState } from 'react';
import axios from 'axios';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';
import { BranchSettings } from '@/components/forms/RestaurantOperationsForm';

interface AddBranchFormProps {
    onCreated: (branch: BranchSettings) => void;
}

interface BranchFormValues {
    name: string;
    slug: string;
    address: string;
    phone: string;
    opens_at: string;
    closes_at: string;
    delivery_fee: string;
    minimum_order: string;
    estimated_delivery_time: string;
    is_active: boolean;
    accepts_orders: boolean;
}

const initialValues: BranchFormValues = {
    name: '',
    slug: '',
    address: '',
    phone: '',
    opens_at: '08:00',
    closes_at: '23:00',
    delivery_fee: '0',
    minimum_order: '0',
    estimated_delivery_time: '30',
    is_active: true,
    accepts_orders: true,
};

export default function AddBranchForm({ onCreated }: AddBranchFormProps) {
    const [values, setValues] = useState<BranchFormValues>(initialValues);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);

    const handleChange = (field: keyof BranchFormValues, value: unknown) => {
        setValues((current) => ({ ...current, [field]: value }));
        setErrors({});
        setError(null);
    };

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setCreating(true);
        setErrors({});
        setError(null);

        try {
            const { data } = await api.post('/restaurant/settings/branches', {
                name: values.name,
                slug: values.slug || undefined,
                address: values.address,
                phone: values.phone || undefined,
                opens_at: values.opens_at,
                closes_at: values.closes_at,
                delivery_fee: Number(values.delivery_fee),
                minimum_order: Number(values.minimum_order),
                estimated_delivery_time: Number(values.estimated_delivery_time),
                is_active: values.is_active,
                accepts_orders: values.accepts_orders,
            });

            const branch = data.data as BranchSettings;
            setValues(initialValues);
            onCreated(branch);
        } catch (err: any) {
            if (axios.isAxiosError(err)) {
                const validationErrors = (err.response?.data?.errors ?? {}) as Record<string, string[]>;

                if (Object.keys(validationErrors).length > 0) {
                    setErrors(
                        Object.fromEntries(
                            Object.entries(validationErrors).map(([field, messages]) => [
                                field,
                                messages[0] ?? 'Invalid value',
                            ])
                        )
                    );
                } else {
                    setError(err.response?.data?.message ?? 'Could not create branch.');
                }
                return;
            }

            setError('Could not create branch.');
        } finally {
            setCreating(false);
        }
    };

    return (
        <Card variant="outlined">
            <CardBody className="p-4">
                <div className="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 className="fs-5 fw-bold text-1000 mb-1">Add a branch</h2>
                        <p className="text-700 small mb-0">
                            Create a new branch so you can configure its operating hours, delivery terms, and order
                            settings from Restaurant Settings.
                        </p>
                    </div>
                </div>

                {error && <div className="alert alert-danger mb-3" role="alert">{error}</div>}

                <form onSubmit={handleSubmit} noValidate>
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label htmlFor="branch-name" className="form-label fw-semibold">Branch name</label>
                            <input
                                id="branch-name"
                                type="text"
                                name="name"
                                value={values.name}
                                onChange={(event) => handleChange('name', event.target.value)}
                                className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                                required
                            />
                            {errors.name && <div className="invalid-feedback d-block">{errors.name}</div>}
                        </div>
                        <div className="col-md-6">
                            <label htmlFor="branch-slug" className="form-label fw-semibold">URL slug</label>
                            <input
                                id="branch-slug"
                                type="text"
                                name="slug"
                                value={values.slug}
                                onChange={(event) => handleChange('slug', event.target.value)}
                                className={`form-control ${errors.slug ? 'is-invalid' : ''}`}
                                placeholder="Auto-generated from name"
                            />
                            {errors.slug && <div className="invalid-feedback d-block">{errors.slug}</div>}
                            <div className="form-text">Lowercase letters, numbers, and hyphens only.</div>
                        </div>
                        <div className="col-12">
                            <label htmlFor="branch-address" className="form-label fw-semibold">Street address</label>
                            <input
                                id="branch-address"
                                type="text"
                                name="address"
                                value={values.address}
                                onChange={(event) => handleChange('address', event.target.value)}
                                className={`form-control ${errors.address ? 'is-invalid' : ''}`}
                                required
                            />
                            {errors.address && <div className="invalid-feedback d-block">{errors.address}</div>}
                        </div>
                        <div className="col-md-6">
                            <label htmlFor="branch-phone" className="form-label fw-semibold">Phone</label>
                            <input
                                id="branch-phone"
                                type="tel"
                                name="phone"
                                value={values.phone}
                                onChange={(event) => handleChange('phone', event.target.value)}
                                className={`form-control ${errors.phone ? 'is-invalid' : ''}`}
                            />
                            {errors.phone && <div className="invalid-feedback d-block">{errors.phone}</div>}
                        </div>
                        <div className="col-md-3">
                            <label htmlFor="branch-opens-at" className="form-label fw-semibold">Opens at</label>
                            <input
                                id="branch-opens-at"
                                type="time"
                                name="opens_at"
                                value={values.opens_at}
                                onChange={(event) => handleChange('opens_at', event.target.value)}
                                className={`form-control ${errors.opens_at ? 'is-invalid' : ''}`}
                                required
                            />
                            {errors.opens_at && <div className="invalid-feedback d-block">{errors.opens_at}</div>}
                        </div>
                        <div className="col-md-3">
                            <label htmlFor="branch-closes-at" className="form-label fw-semibold">Closes at</label>
                            <input
                                id="branch-closes-at"
                                type="time"
                                name="closes_at"
                                value={values.closes_at}
                                onChange={(event) => handleChange('closes_at', event.target.value)}
                                className={`form-control ${errors.closes_at ? 'is-invalid' : ''}`}
                                required
                            />
                            {errors.closes_at && <div className="invalid-feedback d-block">{errors.closes_at}</div>}
                        </div>
                        <div className="col-md-3">
                            <label htmlFor="branch-delivery-fee" className="form-label fw-semibold">Delivery fee (SAR)</label>
                            <input
                                id="branch-delivery-fee"
                                type="number"
                                min="0"
                                step="0.01"
                                name="delivery_fee"
                                value={values.delivery_fee}
                                onChange={(event) => handleChange('delivery_fee', event.target.value)}
                                className={`form-control ${errors.delivery_fee ? 'is-invalid' : ''}`}
                            />
                            {errors.delivery_fee && <div className="invalid-feedback d-block">{errors.delivery_fee}</div>}
                        </div>
                        <div className="col-md-3">
                            <label htmlFor="branch-minimum-order" className="form-label fw-semibold">Minimum order (SAR)</label>
                            <input
                                id="branch-minimum-order"
                                type="number"
                                min="0"
                                step="0.01"
                                name="minimum_order"
                                value={values.minimum_order}
                                onChange={(event) => handleChange('minimum_order', event.target.value)}
                                className={`form-control ${errors.minimum_order ? 'is-invalid' : ''}`}
                            />
                            {errors.minimum_order && <div className="invalid-feedback d-block">{errors.minimum_order}</div>}
                        </div>
                        <div className="col-md-3">
                            <label htmlFor="branch-estimated-delivery-time" className="form-label fw-semibold">Est. delivery (min)</label>
                            <input
                                id="branch-estimated-delivery-time"
                                type="number"
                                min="1"
                                max="120"
                                name="estimated_delivery_time"
                                value={values.estimated_delivery_time}
                                onChange={(event) => handleChange('estimated_delivery_time', event.target.value)}
                                className={`form-control ${errors.estimated_delivery_time ? 'is-invalid' : ''}`}
                            />
                            {errors.estimated_delivery_time && <div className="invalid-feedback d-block">{errors.estimated_delivery_time}</div>}
                        </div>
                        <div className="col-md-4">
                            <div className="form-check form-switch mt-6 pt-2">
                                <input
                                    id="branch-is-active"
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={values.is_active}
                                    onChange={(event) => handleChange('is_active', event.target.checked)}
                                />
                                <label className="form-check-label" htmlFor="branch-is-active">
                                    Branch is active
                                </label>
                            </div>
                        </div>
                        <div className="col-md-4">
                            <div className="form-check form-switch mt-6 pt-2">
                                <input
                                    id="branch-accepts-orders"
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={values.accepts_orders}
                                    onChange={(event) => handleChange('accepts_orders', event.target.checked)}
                                />
                                <label className="form-check-label" htmlFor="branch-accepts-orders">
                                    Accept new orders
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="d-flex gap-2 mt-4">
                        <Button type="submit" variant="primary" loading={creating}>
                            Create Branch
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setValues(initialValues)} disabled={creating}>
                            Reset
                        </Button>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}
