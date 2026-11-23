import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
    createProvider,
    type ProviderType,
    type MapProvider,
    type MapCoordinates,
    type MapLocationResult,
    type MapProviderOptions,
} from '@/lib/map';

interface UseMapOptions {
    providerType?: ProviderType;
    accessToken?: string;
    zoom?: number;
}

interface UseMapReturn {
    provider: MapProvider | null;
    providerType: ProviderType;
    containerRef: React.RefObject<HTMLElement | null>;
    isReady: boolean;
    error: string | null;
    init: (container: HTMLElement) => void;
    addMarker: (coordinates: MapCoordinates, options?: any) => void;
    addMarkers: (coordinates: MapCoordinates[], options?: any) => void;
    clearMarkers: () => void;
    onMapClick: (handler: (coordinates: MapCoordinates) => void) => void;
    resize: () => void;
    centerOn: (coordinates: MapCoordinates, zoom?: number) => void;
    fitBounds: (coordinates: MapCoordinates[], padding?: number | number[]) => void;
    getCurrentLocation: () => Promise<MapCoordinates>;
    geocode: (query: string) => Promise<MapLocationResult[]>;
    reverseGeocode: (coordinates: MapCoordinates) => Promise<MapLocationResult | null>;
    destroy: () => void;
}

export function useMap(options: UseMapOptions = {}): UseMapReturn {
    const { app } = usePage().props as unknown as {
        app?: { mapbox_token?: string; map_provider?: string };
    };

    const [provider, setProvider] = useState<MapProvider | null>(null);
    const [error, setError] = useState<string | null>(null);
    // Readiness must be state: init() runs in an effect, and without a state
    // change the derived provider.isReady() never reaches the render pass,
    // so effects gated on isReady (e.g. map click -> pin) never run.
    const [isReady, setIsReady] = useState(false);
    const containerRef = useRef<HTMLElement | null>(null);

    const resolvedType = useCallback((): ProviderType => {
        if (options.providerType) return options.providerType === 'free' ? 'maplibre' : options.providerType;
        const configured = app?.map_provider;
        return configured === 'mapbox' || configured === 'google'
            ? (configured as ProviderType)
            : 'maplibre';
    }, [app, options.providerType]);

    const token = options.accessToken ?? app?.mapbox_token ?? '';

    useEffect(() => {
        const type = resolvedType();
        let instance: MapProvider;
        try {
            instance = createProvider(type, { accessToken: token } as MapProviderOptions);
        } catch {
            instance = createProvider('free');
        }
        setProvider(instance);
        setIsReady(false);

        return () => {
            instance.destroy();
            containerRef.current = null;
        };
    }, [resolvedType, token]);

    const init = useCallback(
        (container: HTMLElement) => {
            containerRef.current = container;
            if (!provider) throw new Error('Map provider not ready');
            try {
                provider.init(container, {
                    accessToken: token,
                    zoom: options.zoom,
                } as MapProviderOptions);
                setError(null);
                setIsReady(true);
            } catch (e: any) {
                setError(e?.message ?? 'Failed to initialize map');
                setIsReady(false);
            }
        },
        [provider, token, options.zoom]
    );

    const addMarker = useCallback(
        (coordinates: MapCoordinates, opts?: any) => {
            provider?.addMarker(coordinates, opts);
        },
        [provider]
    );

    const addMarkers = useCallback(
        (coordinates: MapCoordinates[], opts?: any) => {
            if (provider && typeof (provider as any).addMarkers === 'function') {
                (provider as any).addMarkers(coordinates, opts);
            } else {
                coordinates.forEach((c) => provider?.addMarker(c, opts));
            }
        },
        [provider]
    );

    const clearMarkers = useCallback(() => {
        if (provider && typeof (provider as any).clearMarkers === 'function') {
            (provider as any).clearMarkers();
        }
    }, [provider]);

    const onMapClick = useCallback(
        (handler: (coordinates: MapCoordinates) => void) => {
            provider?.onMapClick(handler);
        },
        [provider]
    );

    const centerOn = useCallback(
        (coordinates: MapCoordinates, zoom?: number) => {
            provider?.centerOn(coordinates, zoom);
        },
        [provider]
    );

    const resize = useCallback(() => {
        provider?.resize();
    }, [provider]);

    const fitBounds = useCallback(
        (coordinates: MapCoordinates[], padding?: number | number[]) => {
            if (provider && typeof (provider as any).fitBounds === 'function') {
                (provider as any).fitBounds(coordinates, padding);
            }
        },
        [provider]
    );

    const getCurrentLocation = useCallback(() => provider?.getCurrentLocation() ?? Promise.reject(new Error('Map not ready')), [provider]);
    const geocode = useCallback((query: string) => provider?.geocode(query) ?? Promise.resolve([]), [provider]);
    const reverseGeocode = useCallback(
        (coordinates: MapCoordinates) => provider?.reverseGeocode(coordinates) ?? Promise.resolve(null),
        [provider]
    );

    const destroy = useCallback(() => {
        provider?.destroy();
        setProvider(null);
        setIsReady(false);
    }, [provider]);

    return {
        provider,
        providerType: resolvedType(),
        containerRef,
        isReady,
        error,
        init,
        addMarker,
        addMarkers,
        clearMarkers,
        onMapClick,
        centerOn,
        fitBounds,
        resize,
        getCurrentLocation,
        geocode,
        reverseGeocode,
        destroy,
    };
}
