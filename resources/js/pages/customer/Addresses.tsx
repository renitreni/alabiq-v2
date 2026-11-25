import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CustomerLayout } from '@/components/layout/CustomerLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { ConfirmModal } from '@/components/ui/ConfirmModal';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageScaffold } from '@/components/ui/PageScaffold';
import AddressMap from '@/components/map/AddressMap';
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
    latitude?: number | null;
    longitude?: number | null;
    place_id?: string | null;
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

interface MapCoordinates {
    lat: number;
    lng: number;
}

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
    const [pickedCoords, setPickedCoords] = useState<MapCoordinates | null>(null);
    const [placeId, setPlaceId] = useState<string | null>(null);
    const [geocoding, setGeocoding] = useState(false);
    const [reverseGeocoding, setReverseGeocoding] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<CustomerAddress | null>(null);
    const formRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!notice) return;
        const timer = setTimeout(() => setNotice(null), 4000);
        return () => clearTimeout(timer);
    }, [notice]);

    const openAddForm = () => {
        setEditingId(null);
        setForm(emptyForm);
        clearPick();
        setError(null);
        setShowForm(true);
        setTimeout(() => formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    };

    const openEditForm = (address: CustomerAddress) => {
        setEditingId(address.id);
        setForm({
            label: address.label ?? '',
            street: address.street ?? '',
            building: address.building ?? '',
            floor: address.floor ?? '',
            apartment: address.apartment ?? '',
            landmark: address.landmark ?? '',
            city: address.city ?? '',
            district: address.district ?? '',
            postal_code: address.postal_code ?? '',
            is_default: address.is_default,
        });
        if (address.latitude != null && address.longitude != null) {
            setPickedCoords({ lat: Number(address.latitude), lng: Number(address.longitude) });
        } else {
            setPickedCoords(null);
        }
        setPlaceId(address.place_id ?? null);
        setError(null);
        setNotice(null);
        setShowForm(true);
        setTimeout(() => formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditingId(null);
        setForm(emptyForm);
        clearPick();
    };

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
            const payload = {
                ...form,
                ...(pickedCoords ? { latitude: pickedCoords.lat, longitude: pickedCoords.lng } : {}),
                ...(placeId ? { place_id: placeId } : {}),
            };
            if (editingId) {
                await api.put(`/customer/addresses/${editingId}`, payload);
            } else {
                await api.post('/customer/addresses', payload);
            }
            const wasEditing = editingId !== null;
            closeForm();
            setNotice(wasEditing ? 'Address updated.' : 'Address saved.');
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

    const removeAddress = async () => {
        if (!deleteTarget) return;
        const id = deleteTarget.id;
        setBusyId(id);
        setError(null);

        try {
            await api.delete(`/customer/addresses/${id}`);
            if (editingId === id) closeForm();
            setDeleteTarget(null);
            setNotice('Address removed.');
            await loadAddresses();
        } catch {
            setError('Could not remove the address.');
            setDeleteTarget(null);
        } finally {
            setBusyId(null);
        }
    };

    const geocodeAddress = async () => {
        const { street, city, district, postal_code } = form;
        const query = [street, district, city, postal_code].filter(Boolean).join(', ');
        if (!query) {
            setError('Enter a street address before geocoding.');
            return;
        }

        setGeocoding(true);
        setError(null);

        try {
            const { data } = await api.get('/customer/addresses/geocode', { params: { q: query } });
            const results = data?.data ?? [];
            if (!results.length) {
                setError('No coordinates found for this address.');
                return;
            }
            const top = results[0];
            setPickedCoords({ lat: top.latitude, lng: top.longitude });
            setPlaceId(top.place_id ?? null);
            setNotice('Location pinned on the map.');
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not geocode the address.');
        } finally {
            setGeocoding(false);
        }
    };

    const applyReverseGeocode = async (coords: MapCoordinates) => {
        setReverseGeocoding(true);
        setError(null);

        try {
            const { data } = await api.get('/customer/addresses/reverse-geocode', {
                params: { lat: coords.lat, lng: coords.lng },
            });
            const result = data?.data ?? {};
            if (result.address) {
                setForm((current) => ({
                    ...current,
                    street: result.street ?? current.street,
                    city: result.city ?? current.city,
                    district: result.district ?? current.district,
                    postal_code: result.postal_code ?? current.postal_code,
                }));
                setNotice('Address details filled from the pin. You can still edit them.');
            } else {
                setNotice('Location pinned. Enter the address details manually.');
            }
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Could not resolve the address.');
        } finally {
            setReverseGeocoding(false);
        }
    };

    const reverseGeocodePicked = async () => {
        if (!pickedCoords) return;
        await applyReverseGeocode(pickedCoords);
    };

    const handleLocationPick = async (coordinates: MapCoordinates) => {
        setPickedCoords(coordinates);
        await applyReverseGeocode(coordinates);
    };

    const useCurrentLocation = async () => {
        setGeocoding(true);
        setError(null);

        try {
            if (!navigator.geolocation) throw new Error('Geolocation not supported');
            const position = await new Promise<GeolocationPosition>((resolve, reject) =>
                navigator.geolocation!.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                })
            );
            setPickedCoords({
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            });
            setNotice('Current location pinned on the map.');
        } catch (e: any) {
            setError(e?.message ?? 'Could not access your location.');
        } finally {
            setGeocoding(false);
        }
    };

    const clearPick = useCallback(() => {
        setPickedCoords(null);
        setPlaceId(null);
    }, []);

    const addressTitle = (address: CustomerAddress) => address.label || address.street;

    return (
        <CustomerLayout title="Delivery Addresses - Alabiq" user={auth?.user}>
            <PageScaffold
                eyebrow="Addresses"
                title="Delivery Addresses"
                description="Save and manage the addresses available at checkout."
                actions={
                    !showForm && (
                        <Button type="button" variant="primary" onClick={openAddForm}>+ Add new address</Button>
                    )
                }
            >
                {error && <div className="alert alert-danger">{error}</div>}
                {notice && <div className="alert alert-success">{notice}</div>}

                {showForm && (
                    <div ref={formRef} className="mb-4">
                        <Card>
                            <CardBody className="p-3 p-md-4">
                                <div className="d-flex justify-content-between align-items-center mb-3">
                                    <h2 className="fs-5 fw-bold text-1000 mb-0">{editingId ? 'Edit Address' : 'Add New Address'}</h2>
                                    <button type="button" className="btn-close" aria-label="Close" onClick={closeForm} />
                                </div>
                                <form onSubmit={saveAddress}>
                                    <div className="row g-4">
                                        <div className="col-lg-6">
                                            <div className="mb-3">
                                                <label htmlFor="label" className="form-label fw-semibold">Label</label>
                                                <input id="label" className="form-control" value={form.label} onChange={(event) => updateField('label', event.target.value)} placeholder="Home, Office, Other" />
                                            </div>
                                            <div className="mb-3">
                                                <label htmlFor="street" className="form-label fw-semibold">Street address</label>
                                                <input id="street" className="form-control" value={form.street} onChange={(event) => updateField('street', event.target.value)} required />
                                            </div>
                                            <div className="row g-3">
                                                <div className="col-12 col-sm-6">
                                                    <label htmlFor="building" className="form-label fw-semibold">Building</label>
                                                    <input id="building" className="form-control" value={form.building} onChange={(event) => updateField('building', event.target.value)} />
                                                </div>
                                                <div className="col-6 col-sm-3">
                                                    <label htmlFor="floor" className="form-label fw-semibold">Floor</label>
                                                    <input id="floor" className="form-control" value={form.floor} onChange={(event) => updateField('floor', event.target.value)} />
                                                </div>
                                                <div className="col-6 col-sm-3">
                                                    <label htmlFor="apartment" className="form-label fw-semibold">Unit</label>
                                                    <input id="apartment" className="form-control" value={form.apartment} onChange={(event) => updateField('apartment', event.target.value)} />
                                                </div>
                                            </div>
                                            <div className="row g-3 mt-0">
                                                <div className="col-12 col-sm-6">
                                                    <label htmlFor="city" className="form-label fw-semibold">City</label>
                                                    <input id="city" className="form-control" value={form.city} onChange={(event) => updateField('city', event.target.value)} required />
                                                </div>
                                                <div className="col-12 col-sm-6">
                                                    <label htmlFor="district" className="form-label fw-semibold">District</label>
                                                    <input id="district" className="form-control" value={form.district} onChange={(event) => updateField('district', event.target.value)} />
                                                </div>
                                            </div>
                                            <div className="mb-3 mt-3">
                                                <label htmlFor="landmark" className="form-label fw-semibold">Landmark</label>
                                                <input id="landmark" className="form-control" value={form.landmark} onChange={(event) => updateField('landmark', event.target.value)} />
                                            </div>
                                            <div className="form-check">
                                                <input
                                                    id="is_default"
                                                    type="checkbox"
                                                    className="form-check-input"
                                                    checked={form.is_default}
                                                    onChange={(event) => updateField('is_default', event.target.checked)}
                                                />
                                                <label htmlFor="is_default" className="form-check-label">Set as default address</label>
                                            </div>
                                        </div>

                                        <div className="col-lg-6">
                                            <h6 className="fw-semibold mb-1">Pin location</h6>
                                            <p className="text-700 small mb-2">Tap the map to drop a pin. Address fields fill in automatically and can still be edited.</p>
                                            <div className="d-flex gap-2 flex-wrap mb-2">
                                                <Button type="button" variant="outline" size="sm" onClick={geocodeAddress} loading={geocoding} disabled={geocoding}>Find from address</Button>
                                                <Button type="button" variant="outline" size="sm" onClick={useCurrentLocation} loading={geocoding} disabled={geocoding}>Use my location</Button>
                                                {pickedCoords && (
                                                    <>
                                                        <Button type="button" variant="outline" size="sm" onClick={reverseGeocodePicked} loading={reverseGeocoding} disabled={reverseGeocoding}>Fill address from pin</Button>
                                                        <Button type="button" variant="ghost" size="sm" onClick={clearPick}>Clear pin</Button>
                                                    </>
                                                )}
                                            </div>
                                            <AddressMap
                                                addresses={[]}
                                                selectedAddressId={null}
                                                pickedCoordinates={pickedCoords}
                                                onLocationPick={handleLocationPick}
                                                height="320px"
                                                locateOnLoad={!editingId && !pickedCoords}
                                            />
                                        </div>
                                    </div>

                                    <div className="d-flex gap-2 justify-content-end mt-4">
                                        <Button type="button" variant="secondary" onClick={closeForm}>Cancel</Button>
                                        <Button type="submit" variant="primary" loading={saving}>{editingId ? 'Save Changes' : 'Save Address'}</Button>
                                    </div>
                                </form>
                            </CardBody>
                        </Card>
                    </div>
                )}

                {loading ? (
                    <div className="row g-3">
                        {[0, 1].map((i) => (
                            <div className="col-md-6" key={i}>
                                <Card><CardBody className="p-4"><div className="placeholder-glow"><span className="placeholder col-6 mb-2" /><span className="placeholder col-10" /></div></CardBody></Card>
                            </div>
                        ))}
                    </div>
                ) : addresses.length ? (
                    <div className="row g-3">
                        {addresses.map((address) => {
                            const busy = busyId === address.id;
                            const details = [address.building, address.floor && `Floor ${address.floor}`, address.apartment && `Unit ${address.apartment}`].filter(Boolean).join(', ');
                            return (
                                <div className="col-md-6" key={address.id}>
                                    <Card className={`h-100 ${address.is_default ? 'border border-primary' : ''}`}>
                                        <CardBody className="p-3 p-md-4 d-flex flex-column h-100">
                                            <div className="d-flex align-items-start gap-3">
                                                <div className="rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center flex-shrink-0" style={{ width: 44, height: 44 }} aria-hidden="true">📍</div>
                                                <div className="flex-grow-1 min-w-0">
                                                    <h3 className="fs-6 fw-bold text-1000 mb-1">
                                                        {addressTitle(address)}
                                                        {address.is_default && <span className="badge bg-soft-primary text-primary ms-2">Default</span>}
                                                    </h3>
                                                    <p className="text-700 small mb-1">{address.formatted_address}</p>
                                                    {details && <p className="text-700 small mb-1">{details}</p>}
                                                    {address.landmark && <p className="text-700 small mb-0">Near {address.landmark}</p>}
                                                </div>
                                            </div>
                                            <div className="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                                                <Button variant="outline" size="sm" onClick={() => openEditForm(address)} disabled={busy}>Edit</Button>
                                                {!address.is_default && (
                                                    <Button variant="secondary" size="sm" loading={busy} onClick={() => setDefault(address.id)}>Set default</Button>
                                                )}
                                                <Button variant="danger" size="sm" className="ms-auto" onClick={() => setDeleteTarget(address)} disabled={busy}>Remove</Button>
                                            </div>
                                        </CardBody>
                                    </Card>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState
                        title="No addresses saved"
                        description="Add a delivery address to make checkout faster."
                    />
                )}
            </PageScaffold>

            <ConfirmModal
                show={deleteTarget !== null}
                title="Remove this address?"
                message={deleteTarget ? <>“{addressTitle(deleteTarget)}” will be removed from your saved addresses.</> : null}
                confirmLabel="Remove"
                loading={deleteTarget !== null && busyId === deleteTarget.id}
                onConfirm={removeAddress}
                onCancel={() => setDeleteTarget(null)}
            />
        </CustomerLayout>
    );
}

