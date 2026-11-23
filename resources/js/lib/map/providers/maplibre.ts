import { MapProvider, MapCoordinates, MapLocationResult, MapProviderOptions } from '../types';

interface MaplibreGl {
    accessToken?: string;
    Map: new (options: any) => any;
    NavigationControl: new (options?: any) => any;
    GeolocateControl: new (options?: any) => any;
    Marker: new (options?: any) => any;
}

const DEFAULT_CENTER: MapCoordinates = { lat: 24.7136, lng: 46.6753 };
// Free vector style — no access token required (MapLibre community demo tiles).
const FREE_STYLE = 'https://demotiles.maplibre.org/style.json';

export class MapLibreMapProvider implements MapProvider {
    readonly name = 'maplibre';

    private map: any = null;
    private marker: any = null;
    private markers: any[] = [];
    private initialized = false;
    private onClickHandler: ((coordinates: MapCoordinates) => void) | null = null;
    private clickListener: ((e: any) => void) | null = null;

    isReady(): boolean {
        return typeof window !== 'undefined' && !!window.maplibregl && this.initialized;
    }

    init(container: HTMLElement, options?: MapProviderOptions): void {
        const maplibregl = window.maplibregl as MaplibreGl;
        if (!maplibregl) {
            throw new Error('MapLibre GL JS not loaded. Include maplibre-gl.js in the layout.');
        }

        if (options?.accessToken) {
            maplibregl.accessToken = options.accessToken;
        }

        this.map = new maplibregl.Map({
            container,
            style: options?.style ?? FREE_STYLE,
            center: [DEFAULT_CENTER.lng, DEFAULT_CENTER.lat],
            zoom: options?.zoom ?? 12,
            ...options,
        });

        this.map.addControl(new maplibregl.NavigationControl(), 'top-right');
        this.map.addControl(
            new maplibregl.GeolocateControl({
                positionOptions: { enableHighAccuracy: true },
                trackUserLocation: true,
            }),
            'top-right'
        );

        this.initialized = true;

        // MapLibre needs a resize pass once the container is visible in the DOM.
        this.map.resize();
    }

    resize(): void {
        if (this.map) this.map.resize();
    }

    addMarker(coordinates: MapCoordinates, options?: any): void {
        if (!this.map) return;
        const maplibregl = window.maplibregl as MaplibreGl;
        if (this.marker) this.marker.remove();
        const newMarker = new maplibregl.Marker({ color: '#E53E3E', ...options })
            .setLngLat([coordinates.lng, coordinates.lat])
            .addTo(this.map);
        this.marker = newMarker;
    }

    addMarkers(coordinates: MapCoordinates[], options?: any): void {
        if (!this.map) return;
        const maplibregl = window.maplibregl as MaplibreGl;
        coordinates.forEach((c) => {
            const m = new maplibregl.Marker({ color: '#E53E3E', ...options })
                .setLngLat([c.lng, c.lat])
                .addTo(this.map);
            this.markers.push(m);
        });
    }

    clearMarkers(): void {
        if (this.marker) { this.marker.remove(); this.marker = null; }
        this.markers.forEach((m) => m.remove());
        this.markers = [];
    }

    centerOn(coordinates: MapCoordinates, zoom = 15): void {
        if (!this.map) return;
        this.map.flyTo({ center: [coordinates.lng, coordinates.lat], zoom, essential: true });
    }

    fitBounds(coordinates: MapCoordinates[], padding = 50): void {
        if (!this.map || coordinates.length === 0) return;
        const maplibregl = window.maplibregl as MaplibreGl;
        const bounds = new maplibregl.LngLatBounds();
        coordinates.forEach((c) => bounds.extend([c.lng, c.lat]));
        this.map.fitBounds(bounds, { padding });
    }

    onMapClick(handler: (coordinates: MapCoordinates) => void): void {
        if (!this.map) return;
        this.onClickHandler = handler;
        // Replace any previously registered listener so repeated React effect
        // runs don't stack duplicate click handlers on the map.
        if (this.clickListener) this.map.off('click', this.clickListener);
        this.clickListener = (e: any) => handler({ lat: e.lngLat.lat, lng: e.lngLat.lng });
        this.map.on('click', this.clickListener);
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

    async geocode(query: string): Promise<MapLocationResult[]> {
        // Free geocoding via Nominatim (OpenStreetMap) — no API key required.
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`,
            { headers: { 'Accept-Language': 'en', 'User-Agent': 'Alabiq/1.0' } },
        );
        if (!response.ok) return [];
        const data = await response.json();
        return data.map((item: any) => ({
            coordinates: { lat: parseFloat(item.lat), lng: parseFloat(item.lon) },
            address: item.display_name,
            placeId: item.place_id,
        }));
    }

    async reverseGeocode(coordinates: MapCoordinates): Promise<MapLocationResult | null> {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${coordinates.lat}&lon=${coordinates.lng}`,
            { headers: { 'Accept-Language': 'en', 'User-Agent': 'Alabiq/1.0' } },
        );
        if (!response.ok) return null;
        const data = await response.json();
        if (!data) return null;
        return {
            coordinates: { lat: parseFloat(data.lat), lng: parseFloat(data.lon) },
            address: data.display_name,
            placeId: data.place_id,
        };
    }

    destroy(): void {
        this.clearMarkers();
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
        this.clickListener = null;
        this.onClickHandler = null;
        this.initialized = false;
    }
}

declare global {
    interface Window {
        maplibregl: any;
    }
}
