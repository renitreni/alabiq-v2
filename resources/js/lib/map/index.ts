import { MapLibreMapProvider } from './providers/maplibre';
import { MapboxMapProvider } from './providers/mapbox';
import { GoogleMapProvider } from './providers/google';
import type { MapCoordinates, MapLocationResult, MapProvider, MapProviderOptions } from './types';

export type { MapCoordinates, MapLocationResult, MapProvider, MapProviderOptions };
export { MapLibreMapProvider, MapboxMapProvider, GoogleMapProvider };

export type ProviderType = 'maplibre' | 'mapbox' | 'google';

export function createProvider(type: ProviderType, options?: MapProviderOptions): MapProvider {
    switch (type) {
        case 'mapbox':
            return new MapboxMapProvider(options);
        case 'google':
            return new GoogleMapProvider();
        case 'maplibre':
        default:
            return new MapLibreMapProvider();
    }
}
