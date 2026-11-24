import { useEffect, useRef, useState } from 'react';
import { useMap } from '@/hooks/useMap';
import type { MapCoordinates } from '@/lib/map';

interface MappableAddress {
    id: number;
    label?: string | null;
    formatted_address?: string | null;
    latitude?: number | null;
    longitude?: number | null;
}

interface AddressMapProps {
    addresses: MappableAddress[];
    selectedAddressId?: number | null;
    pickedCoordinates?: MapCoordinates | null;
    onLocationPick?: (coordinates: MapCoordinates) => void;
    className?: string;
    height?: string;
    width?: string;
    zoom?: number;
    locateOnLoad?: boolean;
}

export default function AddressMap({
    addresses,
    selectedAddressId,
    pickedCoordinates,
    onLocationPick,
    className = '',
    height = '400px',
    width = '100%',
    zoom = 13,
    locateOnLoad = true,
}: AddressMapProps) {
    const { init, isReady, error, addMarkers, clearMarkers, centerOn, addMarker, onMapClick, getCurrentLocation, resize } = useMap({ zoom });
    const containerRef = useRef<HTMLDivElement>(null);
    const initialized = useRef(false);
    const [pinnedCoords, setPinnedCoords] = useState<MapCoordinates | null>(null);
    const [located, setLocated] = useState(false);
    const [attempts, setAttempts] = useState(0);
    const pickCallback = useRef(onLocationPick);
    pickCallback.current = onLocationPick;
    const isPickControlled = pickedCoordinates !== undefined;
    const activePinnedCoords = isPickControlled ? pickedCoordinates : pinnedCoords;
    const activePinLat = activePinnedCoords?.lat ?? null;
    const activePinLng = activePinnedCoords?.lng ?? null;

    // Initialize the map once the container is mounted.
    useEffect(() => {
        if (!containerRef.current || initialized.current) return;

        const container = containerRef.current;
        // Ensure explicit dimensions so MapLibre can size the canvas.
        container.style.width = width;
        container.style.height = height;
        container.style.position = 'relative';

        try {
            init(container);
            initialized.current = true;
            resize();
        } catch (err) {
            // Retry a few times in case the map library script is still loading.
            if (attempts < 3) {
                setAttempts((a) => a + 1);
                setTimeout(() => {
                    initialized.current = false;
                }, 500);
            } else {
                console.error('Map init failed', err);
            }
        }
    }, [init, resize, width, height, attempts]);

    // Auto-center on the user's current location when the map loads.
    useEffect(() => {
        if (!isReady || !locateOnLoad || located) return;

        getCurrentLocation()
            .then((coords) => {
                if (isPickControlled) {
                    pickCallback.current?.(coords);
                } else {
                    setPinnedCoords(coords);
                }
                centerOn(coords, 16);
                addMarker(coords);
                setLocated(true);
            })
            .catch(() => {
                // Geolocation denied or unavailable — map still opens at default.
                setLocated(true);
            });
    }, [isReady, locateOnLoad, getCurrentLocation, centerOn, addMarker, isPickControlled]);

    // Render saved-address markers and re-apply the user's pin.
    useEffect(() => {
        if (!isReady || !containerRef.current) return;
        clearMarkers();

        const withCoords = addresses.filter((a) => a.latitude != null && a.longitude != null);
        if (withCoords.length) {
            addMarkers(
                withCoords.map((a) => ({ lat: a.latitude!, lng: a.longitude! }))
            );
        }

        if (activePinLat !== null && activePinLng !== null) {
            addMarker({ lat: activePinLat, lng: activePinLng });
        }
        resize();
    }, [addresses, isReady, clearMarkers, addMarkers, activePinLat, activePinLng, addMarker, resize]);

    // Center when coordinates come from geocoding or the browser location.
    useEffect(() => {
        if (!isReady || activePinLat === null || activePinLng === null) return;
        centerOn({ lat: activePinLat, lng: activePinLng }, 16);
    }, [isReady, activePinLat, activePinLng, centerOn]);

    // Center on the selected/default address when no manual pin is active.
    useEffect(() => {
        if (!isReady || selectedAddressId == null || activePinLat !== null || activePinLng !== null) return;
        const address = addresses.find((a) => a.id === selectedAddressId);
        if (address?.latitude != null && address?.longitude != null) {
            centerOn({ lat: address.latitude, lng: address.longitude });
        }
    }, [selectedAddressId, addresses, isReady, centerOn, activePinLat, activePinLng]);

    // Clicking the map pins a new location and notifies the parent.
    useEffect(() => {
        if (!isReady) return;
        onMapClick((coordinates: MapCoordinates) => {
            if (!isPickControlled) {
                setPinnedCoords(coordinates);
            }
            addMarker(coordinates);
            centerOn(coordinates, 16);
            pickCallback.current?.(coordinates);
        });
    }, [isReady, onMapClick, addMarker, centerOn, isPickControlled]);

    if (error) {
        return (
            <div className={`border border-danger rounded p-3 bg-light text-700 ${className}`} style={{ width, height }}>
                Map unavailable: {error}
            </div>
        );
    }

    return (
        <div className={`border rounded overflow-hidden position-relative ${className}`} style={{ width, height }} ref={containerRef}>
            {!isReady && attempts < 3 && (
                <div className="w-full h-full d-flex align-items-center justify-content-center text-700">
                    Loading map…
                </div>
            )}
            {attempts >= 3 && !isReady && (
                <div className="w-full h-full d-flex align-items-center justify-content-center text-700 bg-light">
                    Map failed to load. Please refresh the page.
                </div>
            )}
            {activePinnedCoords && (
                <div className="absolute top-2 start-2 z-3 bg-white border border-300 px-2 py-1 rounded shadow-sm text-700 small">
                    {activePinnedCoords.lat.toFixed(5)}, {activePinnedCoords.lng.toFixed(5)}
                </div>
            )}
        </div>
    );
}
