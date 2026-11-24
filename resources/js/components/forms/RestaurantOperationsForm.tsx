import { FormEvent, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import api from '@/lib/axios';

export interface BranchSettings {
    id: number;
    name: string;
    address: string;
    is_active: boolean;
    accepts_orders: boolean;
    opens_at: string;
    closes_at: string;
    delivery_fee: number;
    minimum_order: number;
    estimated_delivery_time: number;
    is_open: boolean;
}

export interface RestaurantOperationsSettings {
    restaurant: {
        id: number;
        name: string;
        accepts_orders: boolean;
    };
    branches: BranchSettings[];
    payments: {
        cod_available: boolean;
        online_payments_available: boolean;
    };
}

interface BranchSettingsFormValues {
    is_active: boolean;
    accepts_orders: boolean;
    opens_at: string;
    closes_at: string;
    delivery_fee: string;
    minimum_order: string;
    estimated_delivery_time: string;
}

type BranchSettingsFormErrors = Partial<Record<keyof BranchSettingsFormValues, string>>;

interface ApiErrorData {
    message?: string;
    errors?: Record<string, string[]>;
}

interface RestaurantOperationsFormProps {
    settings: RestaurantOperationsSettings;
    onSaved: (branch: BranchSettings) => void;
}

const toFormValues = (branch: BranchSettings): BranchSettingsFormValues => ({
    is_active: branch.is_active,
    accepts_orders: branch.accepts_orders,
    opens_at: branch.opens_at,
    closes_at: branch.closes_at,
    delivery_fee: String(branch.delivery_fee),
    minimum_order: String(branch.minimum_order),
    estimated_delivery_time: String(branch.estimated_delivery_time),
});

const formatCurrency = (value: number) => `${Number(value).toFixed(2)} SAR`;

export default function RestaurantOperationsForm({ settings, onSaved }: RestaurantOperationsFormProps) {
    const [selectedBranchId, setSelectedBranchId] = useState<number | null>(settings.branches[0]?.id ?? null);
    const [drafts, setDrafts] = useState<Record<number, BranchSettingsFormValues>>({});
    const [fieldErrors, setFieldErrors] = useState<BranchSettingsFormErrors>({});
    const [saveError, setSaveError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (settings.branches.length === 0) {
            setSelectedBranchId(null);
            return;
        }

        if (selectedBranchId === null || !settings.branches.some((branch) => branch.id === selectedBranchId)) {
            setSelectedBranchId(settings.branches[0].id);
        }
    }, [settings.branches, selectedBranchId]);

    useEffect(() => {
        setFieldErrors({});
        setSaveError(null);
        setSuccessMessage(null);
    }, [selectedBranchId]);

    const selectedBranch = settings.branches.find((branch) => branch.id === selectedBranchId) ?? null;
    const initialValues = selectedBranch ? toFormValues(selectedBranch) : null;
    const formValues = selectedBranch ? drafts[selectedBranch.id] ?? initialValues : null;

    const isDirty = useMemo(() => {
        if (!initialValues || !formValues) {
            return false;
        }

        return JSON.stringify(initialValues) !== JSON.stringify(formValues);
    }, [initialValues, formValues]);

    if (!selectedBranch || !initialValues || !formValues) {
        return (
            <Card variant="outlined">
                <CardBody className="p-4 text-700">
                    Add a branch before configuring restaurant operations.
                </CardBody>
            </Card>
        );
    }

    const updateField = <K extends keyof BranchSettingsFormValues>(
        field: K,
        value: BranchSettingsFormValues[K]
    ) => {
        setDrafts((current) => ({
            ...current,
            [selectedBranch.id]: {
                ...formValues,
                [field]: value,
            },
        }));
    };

    const resetDraft = () => {
        setDrafts((current) => {
            const next = { ...current };
            delete next[selectedBranch.id];
            return next;
        });
        setFieldErrors({});
        setSaveError(null);
        setSuccessMessage(null);
    };

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setSaving(true);
        setFieldErrors({});
        setSaveError(null);
        setSuccessMessage(null);

        try {
            const { data } = await api.put(`/restaurant/settings/branches/${selectedBranch.id}`, {
                is_active: formValues.is_active,
                accepts_orders: formValues.accepts_orders,
                opens_at: formValues.opens_at,
                closes_at: formValues.closes_at,
                delivery_fee: formValues.delivery_fee,
                minimum_order: formValues.minimum_order,
                estimated_delivery_time: formValues.estimated_delivery_time,
            });

            const updatedBranch = data.data as BranchSettings;
            setDrafts((current) => {
                const next = { ...current };
                delete next[updatedBranch.id];
                return next;
            });
            onSaved(updatedBranch);
            setSuccessMessage(`${updatedBranch.name} settings saved.`);
        } catch (error) {
            if (axios.isAxiosError<ApiErrorData>(error)) {
                const validationErrors = error.response?.data?.errors ?? {};
                const hasFieldErrors = Object.keys(validationErrors).length > 0;

                setFieldErrors(Object.fromEntries(
                    Object.entries(validationErrors).map(([field, messages]) => [
                        field,
                        messages[0] ?? 'Invalid value',
                    ])
                ) as BranchSettingsFormErrors);
                setSaveError(hasFieldErrors
                    ? null
                    : error.response?.data?.message ?? 'Could not save branch settings.');
                return;
            }

            setSaveError('Could not save branch settings.');
        } finally {
            setSaving(false);
        }
    };

    const paymentMethods = [
        {
            name: 'Cash on delivery',
            description: 'Customers pay when their order arrives.',
            available: settings.payments.cod_available,
        },
        {
            name: 'Online payments',
            description: 'Customers pay before the order is confirmed.',
            available: settings.payments.online_payments_available,
        },
    ];

    return (
        <div>
            <div className="mb-4">
                <label htmlFor="settings-branch" className="form-label fw-semibold">Branch</label>
                <select
                    id="settings-branch"
                    className="form-select"
                    value={selectedBranch.id}
                    onChange={(event) => setSelectedBranchId(Number(event.target.value))}
                >
                    {settings.branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>{branch.name}</option>
                    ))}
                </select>
                <div className="form-text">Drafts are kept separately when switching branches.</div>
            </div>

            {successMessage && <div className="alert alert-success" role="status">{successMessage}</div>}
            {saveError && <div className="alert alert-danger" role="alert">{saveError}</div>}

            <form onSubmit={handleSubmit} noValidate>
                <div className="row g-4">
                    <div className="col-lg-7">
                        <Card className="h-100">
                            <CardBody className="p-4">
                                <div className="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <h2 className="fs-5 fw-bold text-1000 mb-1">{selectedBranch.name}</h2>
                                        <p className="text-700 small mb-0">{selectedBranch.address}</p>
                                    </div>
                                    <span className={`badge ${selectedBranch.is_open ? 'text-bg-success' : 'text-bg-secondary'}`}>
                                        {selectedBranch.is_open ? 'Open now' : 'Closed'}
                                    </span>
                                </div>

                                <div className="form-check form-switch mb-3">
                                    <input
                                        id="branch-is-active"
                                        type="checkbox"
                                        className={`form-check-input ${fieldErrors.is_active ? 'is-invalid' : ''}`}
                                        checked={formValues.is_active}
                                        onChange={(event) => updateField('is_active', event.target.checked)}
                                    />
                                    <label className="form-check-label" htmlFor="branch-is-active">
                                        Branch is active
                                    </label>
                                    {fieldErrors.is_active && <div className="invalid-feedback d-block">{fieldErrors.is_active}</div>}
                                </div>

                                <div className="form-check form-switch mb-4">
                                    <input
                                        id="branch-accepts-orders"
                                        type="checkbox"
                                        className={`form-check-input ${fieldErrors.accepts_orders ? 'is-invalid' : ''}`}
                                        checked={formValues.accepts_orders}
                                        onChange={(event) => updateField('accepts_orders', event.target.checked)}
                                    />
                                    <label className="form-check-label" htmlFor="branch-accepts-orders">
                                        Accept new orders
                                    </label>
                                    {fieldErrors.accepts_orders && <div className="invalid-feedback d-block">{fieldErrors.accepts_orders}</div>}
                                </div>

                                <div className="row g-3">
                                    <div className="col-sm-6">
                                        <label htmlFor="opens-at" className="form-label fw-semibold">Opens at</label>
                                        <input
                                            id="opens-at"
                                            type="time"
                                            className={`form-control ${fieldErrors.opens_at ? 'is-invalid' : ''}`}
                                            value={formValues.opens_at}
                                            onChange={(event) => updateField('opens_at', event.target.value)}
                                            required
                                        />
                                        {fieldErrors.opens_at && <div className="invalid-feedback">{fieldErrors.opens_at}</div>}
                                    </div>
                                    <div className="col-sm-6">
                                        <label htmlFor="closes-at" className="form-label fw-semibold">Closes at</label>
                                        <input
                                            id="closes-at"
                                            type="time"
                                            className={`form-control ${fieldErrors.closes_at ? 'is-invalid' : ''}`}
                                            value={formValues.closes_at}
                                            onChange={(event) => updateField('closes_at', event.target.value)}
                                            required
                                        />
                                        {fieldErrors.closes_at && <div className="invalid-feedback">{fieldErrors.closes_at}</div>}
                                    </div>
                                    <div className="col-sm-6">
                                        <label htmlFor="estimated-delivery-time" className="form-label fw-semibold">Estimated delivery</label>
                                        <div className="input-group">
                                            <input
                                                id="estimated-delivery-time"
                                                type="number"
                                                min="1"
                                                max="120"
                                                className={`form-control ${fieldErrors.estimated_delivery_time ? 'is-invalid' : ''}`}
                                                value={formValues.estimated_delivery_time}
                                                onChange={(event) => updateField('estimated_delivery_time', event.target.value)}
                                                required
                                            />
                                            <span className="input-group-text">min</span>
                                            {fieldErrors.estimated_delivery_time && <div className="invalid-feedback">{fieldErrors.estimated_delivery_time}</div>}
                                        </div>
                                    </div>
                                </div>
                            </CardBody>
                        </Card>
                    </div>

                    <div className="col-lg-5">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-1">Payments and delivery terms</h2>
                                <p className="text-700 small mb-4">Delivery fees apply when the selected branch has no matching custom delivery zone.</p>

                                <div className="mb-3">
                                    <label htmlFor="delivery-fee" className="form-label fw-semibold">Delivery fee (SAR)</label>
                                    <input
                                        id="delivery-fee"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className={`form-control ${fieldErrors.delivery_fee ? 'is-invalid' : ''}`}
                                        value={formValues.delivery_fee}
                                        onChange={(event) => updateField('delivery_fee', event.target.value)}
                                        required
                                    />
                                    {fieldErrors.delivery_fee && <div className="invalid-feedback">{fieldErrors.delivery_fee}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="minimum-order" className="form-label fw-semibold">Minimum order (SAR)</label>
                                    <input
                                        id="minimum-order"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className={`form-control ${fieldErrors.minimum_order ? 'is-invalid' : ''}`}
                                        value={formValues.minimum_order}
                                        onChange={(event) => updateField('minimum_order', event.target.value)}
                                        required
                                    />
                                    {fieldErrors.minimum_order && <div className="invalid-feedback">{fieldErrors.minimum_order}</div>}
                                </div>

                                <h3 className="fs-6 fw-bold text-1000 mb-3">Available payment methods</h3>
                                <div className="list-group list-group-flush">
                                    {paymentMethods.map((method) => (
                                        <div key={method.name} className="list-group-item px-0 d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div className="fw-semibold text-1000">{method.name}</div>
                                                <div className="text-700 small">{method.description}</div>
                                            </div>
                                            <span className={`badge ${method.available ? 'text-bg-success' : 'text-bg-secondary'}`}>
                                                {method.available ? 'Available' : 'Unavailable'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-600 small mt-3 mb-0">
                                    Payment availability is managed by the platform administrator.
                                    Current branch terms: {formatCurrency(selectedBranch.delivery_fee)} delivery,
                                    minimum order {formatCurrency(selectedBranch.minimum_order)}.
                                </p>
                            </CardBody>
                        </Card>
                    </div>
                </div>

                <div className="d-flex flex-column flex-sm-row gap-2 mt-4">
                    <Button type="submit" variant="primary" loading={saving} disabled={!isDirty}>
                        Save Branch Settings
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={resetDraft}
                        disabled={!isDirty || saving}
                    >
                        Reset Changes
                    </Button>
                </div>
            </form>
        </div>
    );
}
