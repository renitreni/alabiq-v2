import { useState, useCallback, useEffect } from 'react';

interface Coordinates {
    lat: number;
    lng: number;
}

interface LocationResult {
    coordinates: Coordinates;
    address: string;
    placeId?: string;
}

interface MapboxConfig {
    accessToken: string;
    style?: string;
}

interface MapboxGl {
    accessToken: string;
    Map: new (options: any) => any;
    NavigationControl: new (options?: any) => any;
    GeolocateControl: new (options?: any) => any;
    Marker: new (options?: any) => any;
}

export function useMapbox(config: MapboxConfig) {
    const [map, setMap] = useState<any>(null);
    const [marker, setMarker] = useState<any>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const initMap = useCallback(
        (container: HTMLElement, options?: any) => {
            if (!window.mapboxgl) {
                setError('Mapbox GL JS not loaded');
                return null;
            }

            const mapboxgl = window.mapboxgl as MapboxGl;
            mapboxgl.accessToken = config.accessToken;

            const newMap = new mapboxgl.Map({
                container,
                style: config.style || 'mapbox://styles/mapbox/streets-v12',
                center: [46.6753, 24.7136],
                zoom: 12,
                ...options,
            });

            newMap.addControl(new mapboxgl.NavigationControl(), 'top-right');
            newMap.addControl(
                new mapboxgl.GeolocateControl({
                    positionOptions: { enableHighAccuracy: true },
                    trackUserLocation: true,
                }),
                'top-right'
            );

            setMap(newMap);
            return newMap;
        },
        [config.accessToken, config.style]
    );

    const geocode = useCallback(
        async (query: string): Promise<LocationResult[]> => {
            setIsLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${config.accessToken}&limit=5&types=place,address,poi`
                );

                if (!response.ok) throw new Error('Geocoding failed');

                const data = await response.json();
                return data.features.map((feature: any) => ({
                    coordinates: {
                        lng: feature.geometry.coordinates[0],
                        lat: feature.geometry.coordinates[1],
                    },
                    address: feature.place_name,
                    placeId: feature.id,
                }));
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Geocoding failed';
                setError(message);
                return [];
            } finally {
                setIsLoading(false);
            }
        },
        [config.accessToken]
    );

    const reverseGeocode = useCallback(
        async (coordinates: Coordinates): Promise<LocationResult | null> => {
            setIsLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `https://api.mapbox.com/geocoding/v5/mapbox.places/${coordinates.lng},${coordinates.lat}.json?access_token=${config.accessToken}&limit=1`
                );

                if (!response.ok) throw new Error('Reverse geocoding failed');

                const data = await response.json();
                if (data.features.length === 0) return null;

                const feature = data.features[0];
                return {
                    coordinates: {
                        lng: feature.geometry.coordinates[0],
                        lat: feature.geometry.coordinates[1],
                    },
                    address: feature.place_name,
                    placeId: feature.id,
                };
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Reverse geocoding failed';
                setError(message);
                return null;
            } finally {
                setIsLoading(false);
            }
        },
        [config.accessToken]
    );

    const getDirections = useCallback(
        async (origin: Coordinates, destination: Coordinates) => {
            setIsLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `https://api.mapbox.com/directions/v5/mapbox/driving/${origin.lng},${origin.lat};${destination.lng},${destination.lat}?access_token=${config.accessToken}&geometries=geojson&overview=full&steps=true`
                );

                if (!response.ok) throw new Error('Directions failed');

                const data = await response.json();
                return data.routes[0];
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Directions failed';
                setError(message);
                return null;
            } finally {
                setIsLoading(false);
            }
        },
        [config.accessToken]
    );

    const addMarker = useCallback(
        (coordinates: Coordinates, options?: any) => {
            if (!map) return null;

            if (marker) marker.remove();

            const mapboxgl = window.mapboxgl as MapboxGl;
            const newMarker = new mapboxgl.Marker({ color: '#E53E3E', ...options })
                .setLngLat([coordinates.lng, coordinates.lat])
                .addTo(map);

            setMarker(newMarker);
            return newMarker;
        },
        [map, marker]
    );

    const centerOn = useCallback(
        (coordinates: Coordinates, zoom = 15) => {
            if (!map) return;
            map.flyTo({ center: [coordinates.lng, coordinates.lat], zoom, essential: true });
        },
        [map]
    );

    const getCurrentLocation = useCallback(
        (): Promise<Coordinates> => {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation not supported'));
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => resolve({ lat: position.coords.latitude, lng: position.coords.longitude }),
                    (err) => reject(err),
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            });
        },
        []
    );

    useEffect(() => {
        return () => {
            if (map) map.remove();
            if (marker) marker.remove();
        };
    }, [map, marker]);

    return {
        map,
        marker,
        isLoading,
        error,
        initMap,
        geocode,
        reverseGeocode,
        getDirections,
        addMarker,
        centerOn,
        getCurrentLocation,
    };
}

declare global {
    interface Window {
        mapboxgl: any;
    }
}
