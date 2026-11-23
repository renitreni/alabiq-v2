import { MapProvider, MapCoordinates, MapLocationResult, MapProviderOptions } from '../types';

/**
 * Google Maps provider scaffold.
 *
 * To enable: load the Google Maps JavaScript API script in the layout
 * (https://maps.googleapis.com/maps/api/js?key=...) and wire the global
 * `window.google` object into the methods below.
 */
export class GoogleMapProvider implements MapProvider {
    readonly name = 'google';

    private map: any = null;
    private marker: any = null;
    private initialized = false;
    private onClickHandler: ((coordinates: MapCoordinates) => void) | null = null;

    isReady(): boolean {
        return typeof window !== 'undefined' && !!window.google && !!window.google.maps && this.initialized;
    }

    init(container: HTMLElement, options?: MapProviderOptions): void {
        const google = window.google as any;
        if (!google?.maps) throw new Error('Google Maps JavaScript API not loaded.');

        this.map = new google.maps.Map(container, {
            center: { lat: 24.7136, lng: 46.6753 },
            zoom: options?.zoom ?? 13,
            ...options,
        });
        this.initialized = true;
    }

    addMarker(coordinates: MapCoordinates, options?: any): void {
        if (!this.map) return;
        const google = window.google as any;
        if (this.marker) this.marker.setMap(null);
        this.marker = new google.maps.Marker({
            position: { lat: coordinates.lat, lng: coordinates.lng },
            map: this.map,
            ...options,
        });
    }

    centerOn(coordinates: MapCoordinates, zoom = 15): void {
        if (!this.map) return;
        this.map.setCenter({ lat: coordinates.lat, lng: coordinates.lng });
        this.map.setZoom(zoom);
    }

    onMapClick(handler: (coordinates: MapCoordinates) => void): void {
        if (!this.map) return;
        this.onClickHandler = handler;
        this.map.addListener('click', (e: any) => handler({ lat: e.latLng.lat(), lng: e.latLng.lng() }));
    }

    resize(): void {
        if (this.map) {
            const google = window.google as any;
            google.maps.event.trigger(this.map, 'resize');
        }
    }

    async getCurrentLocation(): Promise<MapCoordinates> {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) =>
                    resolve({ lat: position.coords.latitude, lng: position.coords.longitude }),
                (err) => reject(err),
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
            );
        });
    }

    async geocode(_query: string): Promise<MapLocationResult[]> {
        throw new Error('Google geocoding not yet implemented. Wire the Geocoder API.');
    }

    async reverseGeocode(_coordinates: MapCoordinates): Promise<MapLocationResult | null> {
        throw new Error('Google reverse geocoding not yet implemented. Wire the Geocoder API.');
    }

    destroy(): void {
        if (this.marker) this.marker.setMap(null);
        this.marker = null;
        this.map = null;
        this.initialized = false;
    }
}
