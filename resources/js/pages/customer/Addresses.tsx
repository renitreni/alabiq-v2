import { FormEvent, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CustomerLayout } from '@/components/layout/CustomerLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageScaffold } from '@/components/ui/PageScaffold';
import api from '@/lib/axios';

interface CustomerAddress {
    id: number;
    label?: string | null;
    street: string;
    building?: string | null;
    floor?: string | null;
    apartment?: string | null;
    landmark?: string | null;
    city: string;
    district?: string | null;
    postal_code?: string | null;
    formatted_address: string;
    is_default: boolean;
}

interface AddressForm {
    label: string;
    street: string;
    building: string;
    floor: string;
    apartment: string;
    landmark: string;
    city: string;
    district: string;
    postal_code: string;
    is_default: boolean;
}

const emptyForm: AddressForm = {
    label: '',
    street: '',
    building: '',
    floor: '',
    apartment: '',
    landmark: '',
    city: '',
    district: '',
    postal_code: '',
    is_default: false,
};

export default function Addresses() {
    const { auth } = usePage().props as unknown as {
        auth: { user?: { name: string; avatar?: string } | null };
    };
    const [addresses, setAddresses] = useState<CustomerAddress[]>([]);
    const [form, setForm] = useState<AddressForm>(emptyForm);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const loadAddresses = async () => {
        const { data } = await api.get('/customer/addresses');
        setAddresses(data?.data ?? []);
    };

    useEffect(() => {
        loadAddresses()
            .catch(() => setError('Could not load your saved addresses.'))
            .finally(() => setLoading(false));
    }, []);

    const updateField = (field: keyof AddressForm, value: string | boolean) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const saveAddress = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSaving(true);
        setError(null);
        setNotice(null);

        try {
            await api.post('/customer/addresses', form);
            setForm(emptyForm);
            setNotice('Address saved.');
            await loadAddresses();
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not save the address. Please check the required fields.');
        } finally {
            setSaving(false);
        }
    };

    const setDefault = async (id: number) => {
        setBusyId(id);
        setError(null);

        try {
            await api.patch(`/customer/addresses/${id}/default`);
            await loadAddresses();
        } catch {
            setError('Could not update the default address.');
        } finally {
            setBusyId(null);
        }
    };

    const removeAddress = async (id: number) => {
        setBusyId(id);
        setError(null);

        try {
            await api.delete(`/customer/addresses/${id}`);
            await loadAddresses();
        } catch {
            setError('Could not remove the address.');
        } finally {
            setBusyId(null);
        }
    };

    return (
        <CustomerLayout title="Delivery Addresses - Alabiq" user={auth?.user}>
            <PageScaffold
                eyebrow="Addresses"
                title="Delivery Addresses"
                description="Save and manage the addresses available at checkout."
            >
                {error && <div className="alert alert-danger">{error}</div>}
                {notice && <div className="alert alert-success">{notice}</div>}

                <div className="row g-4">
                    <div className="col-lg-5">
                        <Card>
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Add New Address</h2>
                                <form onSubmit={saveAddress}>
                                    <div className="mb-3">
                                        <label htmlFor="label" className="form-label fw-semibold">Label</label>
                                        <input id="label" className="form-control" value={form.label} onChange={(event) => updateField('label', event.target.value)} placeholder="Home, Office, Other" />
                                    </div>
                                    <div className="mb-3">
                                        <label htmlFor="street" className="form-label fw-semibold">Street address</label>
                                        <input id="street" className="form-control" value={form.street} onChange={(event) => updateField('street', event.target.value)} required />
                                    </div>
                                    <div className="row g-3">
                                        <div className="col-md-6">
                                            <label htmlFor="building" className="form-label fw-semibold">Building</label>
                                            <input id="building" className="form-control" value={form.building} onChange={(event) => updateField('building', event.target.value)} />
                                        </div>
                                        <div className="col-md-3">
                                            <label htmlFor="floor" className="form-label fw-semibold">Floor</label>
                                            <input id="floor" className="form-control" value={form.floor} onChange={(event) => updateField('floor', event.target.value)} />
                                        </div>
                                        <div className="col-md-3">
                                            <label htmlFor="apartment" className="form-label fw-semibold">Unit</label>
                                            <input id="apartment" className="form-control" value={form.apartment} onChange={(event) => updateField('apartment', event.target.value)} />
                                        </div>
                                    </div>
                                    <div className="row g-3 mt-0">
                                        <div className="col-md-6">
                                            <label htmlFor="city" className="form-label fw-semibold">City</label>
                                            <input id="city" className="form-control" value={form.city} onChange={(event) => updateField('city', event.target.value)} required />
                                        </div>
                                        <div className="col-md-6">
                                            <label htmlFor="district" className="form-label fw-semibold">District</label>
                                            <input id="district" className="form-control" value={form.district} onChange={(event) => updateField('district', event.target.value)} />
                                        </div>
                                    </div>
                                    <div className="mb-3 mt-3">
                                        <label htmlFor="landmark" className="form-label fw-semibold">Landmark</label>
                                        <input id="landmark" className="form-control" value={form.landmark} onChange={(event) => updateField('landmark', event.target.value)} />
                                    </div>
                                    <div className="form-check mb-3">
                                        <input
                                            id="is_default"
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={form.is_default}
                                            onChange={(event) => updateField('is_default', event.target.checked)}
                                        />
                                        <label htmlFor="is_default" className="form-check-label">Set as default address</label>
                                    </div>
                                    <Button type="submit" variant="primary" className="w-100" loading={saving}>Save Address</Button>
                                </form>
                            </CardBody>
                        </Card>
                    </div>

                    <div className="col-lg-7">
                        {loading ? (
                            <Card><CardBody className="p-4 text-700">Loading addresses…</CardBody></Card>
                        ) : addresses.length ? (
                            <div className="d-flex flex-column gap-3">
                                {addresses.map((address) => (
                                    <Card key={address.id} variant="outlined">
                                        <CardBody className="p-4">
                                            <div className="d-flex justify-content-between gap-3">
                                                <div>
                                                    <h3 className="fs-6 fw-bold text-1000 mb-1">
                                                        {address.label ?? 'Saved address'} {address.is_default && <span className="badge bg-soft-primary text-primary ms-1">Default</span>}
                                                    </h3>
                                                    <p className="text-700 small mb-0">{address.formatted_address}</p>
                                                </div>
                                                <div className="d-flex gap-2 align-items-start">
                                                    {!address.is_default && (
                                                        <Button variant="outline" size="sm" loading={busyId === address.id} onClick={() => setDefault(address.id)}>Set Default</Button>
                                                    )}
                                                    <Button variant="ghost" size="sm" loading={busyId === address.id} onClick={() => removeAddress(address.id)}>Remove</Button>
                                                </div>
                                            </div>
                                        </CardBody>
                                    </Card>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                title="No addresses saved"
                                description="Add a delivery address to make checkout faster."
                            />
                        )}
                    </div>
                </div>
            </PageScaffold>
        </CustomerLayout>
    );
}
