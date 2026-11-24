import { FormEvent, useMemo, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import AddressMap from '@/components/map/AddressMap';
import { RestaurantLayout } from '@/components/layout/RestaurantLayout';
import Button from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { PageScaffold } from '@/components/ui/PageScaffold';
import type { MapCoordinates } from '@/lib/map';

interface AuthUser {
    id: number | string;
    name: string;
    email: string;
    restaurant?: { id: number | string; name: string; slug: string; logo?: string | null } | null;
}

interface RestaurantProfile {
    id: number | string;
    address: string | null;
    city: string | null;
    district: string | null;
    postal_code: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface ProfileForm {
    address: string;
    city: string;
    district: string;
    postal_code: string;
    latitude: string;
    longitude: string;
}

interface ReverseGeocodeResult {
    address?: string;
    street?: string | null;
    city?: string | null;
    district?: string | null;
    postal_code?: string | null;
}

const errorMessage = (error: unknown, fallback: string) => {
    if (axios.isAxiosError(error)) {
        return (error.response?.data as { message?: string } | undefined)?.message ?? fallback;
    }

    return fallback;
};

export default function Profile({ profile }: { profile: RestaurantProfile }) {
    const { auth, flash } = usePage().props as unknown as {
        auth: { user?: AuthUser | null };
        flash?: { success?: string | null };
    };
    const user = auth?.user;
    const restaurant = user?.restaurant;
    const { data, setData, put, processing, errors } = useForm<ProfileForm>({
        address: profile.address ?? '',
        city: profile.city ?? '',
        district: profile.district ?? '',
        postal_code: profile.postal_code ?? '',
        latitude: profile.latitude?.toString() ?? '',
        longitude: profile.longitude?.toString() ?? '',
    });
    const [geocoding, setGeocoding] = useState(false);
    const [reverseGeocoding, setReverseGeocoding] = useState(false);
    const [locationError, setLocationError] = useState<string | null>(null);
    const [locationNotice, setLocationNotice] = useState<string | null>(null);

    const pickedCoordinates = useMemo<MapCoordinates | null>(() => {
        if (data.latitude === '' || data.longitude === '') return null;

        return { lat: Number(data.latitude), lng: Number(data.longitude) };
    }, [data.latitude, data.longitude]);

    const setCoordinates = (coordinates: MapCoordinates | null) => {
        setData('latitude', coordinates?.lat.toFixed(8) ?? '');
        setData('longitude', coordinates?.lng.toFixed(8) ?? '');
    };

    const saveProfile = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setLocationError(null);
        setLocationNotice(null);
        put('/restaurant/profile', { preserveScroll: true });
    };

    const applyReverseGeocode = async (coordinates: MapCoordinates) => {
        setReverseGeocoding(true);
        setLocationError(null);

        try {
            const response = await axios.get('/restaurant/profile/reverse-geocode', {
                params: { lat: coordinates.lat, lng: coordinates.lng },
            });
            const result = (response.data?.data ?? {}) as ReverseGeocodeResult;

            if (result.address) {
                if (result.street) setData('address', result.street);
                if (result.city) setData('city', result.city);
                if (result.district) setData('district', result.district);
                if (result.postal_code) setData('postal_code', result.postal_code);
                setLocationNotice('Address details filled from the pin. You can still edit them.');
            } else {
                setLocationNotice('Location pinned. Enter the address details manually.');
            }
        } catch (error: unknown) {
            setLocationError(errorMessage(error, 'Could not resolve the address. You can still enter it manually.'));
        } finally {
            setReverseGeocoding(false);
        }
    };

    const handleLocationPick = async (coordinates: MapCoordinates) => {
        setCoordinates(coordinates);
        await applyReverseGeocode(coordinates);
    };

    const geocodeAddress = async () => {
        if (!data.address.trim()) {
            setLocationError('Enter a street address before geocoding.');
            return;
        }

        setGeocoding(true);
        setLocationError(null);
        setLocationNotice(null);

        try {
            const query = [data.address, data.district, data.city, data.postal_code].filter(Boolean).join(', ');
            const response = await axios.get('/restaurant/profile/geocode', { params: { q: query } });
            const result = response.data?.data;

            setCoordinates({ lat: Number(result.latitude), lng: Number(result.longitude) });
            setLocationNotice('Location pinned on the map. Save the profile to keep it.');
        } catch (error: unknown) {
            setLocationError(errorMessage(error, 'Could not geocode the address.'));
        } finally {
            setGeocoding(false);
        }
    };

    const useCurrentLocation = async () => {
        if (!navigator.geolocation) {
            setLocationError('Geolocation is not supported by this browser.');
            return;
        }

        setGeocoding(true);
        setLocationError(null);
        setLocationNotice(null);

        try {
            const position = await new Promise<GeolocationPosition>((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                });
            });
            await handleLocationPick({ lat: position.coords.latitude, lng: position.coords.longitude });
        } catch (error: unknown) {
            setLocationError(errorMessage(error, 'Could not access your current location.'));
        } finally {
            setGeocoding(false);
        }
    };

    const clearLocation = () => {
        setCoordinates(null);
        setLocationError(null);
        setLocationNotice('Map pin cleared. Save the profile to remove the stored coordinates.');
    };

    return (
        <RestaurantLayout
            title="Restaurant Profile - Alabiq"
            restaurant={restaurant ? { name: restaurant.name, logo: restaurant.logo ?? undefined } : { name: user?.name ?? 'Restaurant' }}
        >
            <PageScaffold
                eyebrow="Profile"
                title="Restaurant Profile"
                description="Review the restaurant account and manage its main address and map location."
            >
                <div className="row g-4">
                    <div className="col-lg-7">
                        <Card>
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Restaurant Details</h2>
                                <dl className="row mb-0">
                                    <dt className="col-sm-3 text-700">Restaurant</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{restaurant?.name ?? 'Not linked yet'}</dd>
                                    <dt className="col-sm-3 text-700">Owner</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{user?.name ?? 'Not available'}</dd>
                                    <dt className="col-sm-3 text-700">Email</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{user?.email ?? 'Not available'}</dd>
                                    <dt className="col-sm-3 text-700">Slug</dt>
                                    <dd className="col-sm-9 fw-semibold text-1000">{restaurant?.slug ?? 'Not available'}</dd>
                                </dl>

                                <hr className="my-4" />

                                {(locationError || flash?.success) && (
                                    <div className={`alert ${locationError ? 'alert-danger' : 'alert-success'}`} role="alert">
                                        {locationError ?? flash?.success}
                                    </div>
                                )}
                                {locationNotice && <div className="alert alert-success" role="status">{locationNotice}</div>}

                                <form onSubmit={saveProfile} noValidate>
                                    <div className="mb-3">
                                        <label htmlFor="profile-address" className="form-label fw-semibold">Street address</label>
                                        <input
                                            id="profile-address"
                                            className={`form-control ${errors.address ? 'is-invalid' : ''}`}
                                            value={data.address}
                                            onChange={(event) => setData('address', event.target.value)}
                                            autoComplete="street-address"
                                            required
                                        />
                                        {errors.address && <div className="invalid-feedback">{errors.address}</div>}
                                    </div>
                                    <div className="row g-3">
                                        <div className="col-md-6">
                                            <label htmlFor="profile-city" className="form-label fw-semibold">City</label>
                                            <input
                                                id="profile-city"
                                                className={`form-control ${errors.city ? 'is-invalid' : ''}`}
                                                value={data.city}
                                                onChange={(event) => setData('city', event.target.value)}
                                                autoComplete="address-level2"
                                                required
                                            />
                                            {errors.city && <div className="invalid-feedback">{errors.city}</div>}
                                        </div>
                                        <div className="col-md-6">
                                            <label htmlFor="profile-district" className="form-label fw-semibold">District</label>
                                            <input
                                                id="profile-district"
                                                className={`form-control ${errors.district ? 'is-invalid' : ''}`}
                                                value={data.district}
                                                onChange={(event) => setData('district', event.target.value)}
                                            />
                                            {errors.district && <div className="invalid-feedback">{errors.district}</div>}
                                        </div>
                                        <div className="col-md-6">
                                            <label htmlFor="profile-postal-code" className="form-label fw-semibold">Postal code</label>
                                            <input
                                                id="profile-postal-code"
                                                className={`form-control ${errors.postal_code ? 'is-invalid' : ''}`}
                                                value={data.postal_code}
                                                onChange={(event) => setData('postal_code', event.target.value)}
                                                autoComplete="postal-code"
                                            />
                                            {errors.postal_code && <div className="invalid-feedback">{errors.postal_code}</div>}
                                        </div>
                                    </div>

                                    <Card className="mt-4" variant="outlined">
                                        <CardBody className="p-0">
                                            <div className="p-3 border-bottom">
                                                <h3 className="fs-6 fw-semibold mb-1">Map location</h3>
                                                <small className="text-700">Click the map to drop a pin and fill the address automatically.</small>
                                            </div>
                                            <div className="d-flex gap-2 flex-wrap p-3 pb-0">
                                                <Button type="button" variant="outline" size="sm" onClick={geocodeAddress} loading={geocoding} disabled={reverseGeocoding}>
                                                    Find address on map
                                                </Button>
                                                <Button type="button" variant="outline" size="sm" onClick={useCurrentLocation} loading={geocoding} disabled={reverseGeocoding}>
                                                    Use my location
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => pickedCoordinates && applyReverseGeocode(pickedCoordinates)}
                                                    loading={reverseGeocoding}
                                                    disabled={!pickedCoordinates || geocoding}
                                                >
                                                    Fill address from map
                                                </Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={clearLocation} disabled={!pickedCoordinates || geocoding || reverseGeocoding}>
                                                    Clear pin
                                                </Button>
                                            </div>
                                            {(errors.latitude || errors.longitude) && (
                                                <div className="text-danger small px-3 pt-2">{errors.latitude ?? errors.longitude}</div>
                                            )}
                                            <AddressMap
                                                addresses={[]}
                                                selectedAddressId={null}
                                                pickedCoordinates={pickedCoordinates}
                                                onLocationPick={handleLocationPick}
                                                height="340px"
                                                locateOnLoad={false}
                                            />
                                        </CardBody>
                                    </Card>

                                    <Button type="submit" variant="primary" className="w-100 mt-4" loading={processing}>
                                        Save Address & Location
                                    </Button>
                                </form>
                            </CardBody>
                        </Card>
                    </div>
                    <div className="col-lg-5">
                        <Card variant="outlined" className="h-100">
                            <CardBody className="p-4">
                                <h2 className="fs-5 fw-bold text-1000 mb-3">Manage</h2>
                                <div className="list-group list-group-flush">
                                    <Link href="/restaurant/orders" className="list-group-item list-group-item-action px-0">Restaurant orders</Link>
                                    <Link href="/restaurant/menu" className="list-group-item list-group-item-action px-0">Menu catalog</Link>
                                    <Link href="/restaurant/settings" className="list-group-item list-group-item-action px-0">Restaurant settings</Link>
                                </div>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </PageScaffold>
        </RestaurantLayout>
    );
}
