import { MapProvider, MapCoordinates, MapLocationResult, MapProviderOptions } from '../types';

interface MapboxGl {
    accessToken: string;
    Map: new (options: any) => any;
    NavigationControl: new (options?: any) => any;
    GeolocateControl: new (options?: any) => any;
    Marker: new (options?: any) => any;
}

const DEFAULT_CENTER: MapCoordinates = { lat: 24.7136, lng: 46.6753 };

export class MapboxMapProvider implements MapProvider {
    readonly name = 'mapbox';

    private map: any = null;
    private marker: any = null;
    private markers: any[] = [];
    private initialized = false;
    private accessToken: string;
    private onClickHandler: ((coordinates: MapCoordinates) => void) | null = null;
    private clickListener: ((e: any) => void) | null = null;

    constructor(options?: MapProviderOptions) {
        this.accessToken = options?.accessToken ?? '';
    }

    isReady(): boolean {
        return typeof window !== 'undefined' && !!window.mapboxgl && this.initialized;
    }

    init(container: HTMLElement, options?: MapProviderOptions): void {
        const mapboxgl = window.mapboxgl as MapboxGl;
        if (!mapboxgl) throw new Error('Mapbox GL JS not loaded. Include mapbox-gl.js in the layout.');

        this.accessToken = options?.accessToken ?? this.accessToken;
        mapboxgl.accessToken = this.accessToken;

        this.map = new mapboxgl.Map({
            container,
            style: options?.style ?? 'mapbox://styles/mapbox/streets-v12',
            center: [DEFAULT_CENTER.lng, DEFAULT_CENTER.lat],
            zoom: options?.zoom ?? 12,
            ...options,
        });

        this.map.addControl(new mapboxgl.NavigationControl(), 'top-right');
        this.map.addControl(
            new mapboxgl.GeolocateControl({
                positionOptions: { enableHighAccuracy: true },
                trackUserLocation: true,
            }),
            'top-right'
        );

        this.initialized = true;
    }

    addMarker(coordinates: MapCoordinates, options?: any): void {
        if (!this.map) return;
        const mapboxgl = window.mapboxgl as MapboxGl;
        if (this.marker) this.marker.remove();
        const newMarker = new mapboxgl.Marker({ color: '#E53E3E', ...options })
            .setLngLat([coordinates.lng, coordinates.lat])
            .addTo(this.map);
        this.marker = newMarker;
    }

    addMarkers(coordinates: MapCoordinates[], options?: any): void {
        if (!this.map) return;
        const mapboxgl = window.mapboxgl as MapboxGl;
        coordinates.forEach((c) => {
            const m = new mapboxgl.Marker({ color: '#E53E3E', ...options })
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

    onMapClick(handler: (coordinates: MapCoordinates) => void): void {
        if (!this.map) return;
        this.onClickHandler = handler;
        // Replace any previously registered listener so repeated React effect
        // runs don't stack duplicate click handlers on the map.
        if (this.clickListener) this.map.off('click', this.clickListener);
        this.clickListener = (e: any) => handler({ lat: e.lngLat.lat, lng: e.lngLat.lng });
        this.map.on('click', this.clickListener);
    }

    fitBounds(coordinates: MapCoordinates[], padding = 50): void {
        if (!this.map || coordinates.length === 0) return;
        const mapboxgl = window.mapboxgl as MapboxGl;
        const bounds = new mapboxgl.LngLatBounds();
        coordinates.forEach((c) => bounds.extend([c.lng, c.lat]));
        this.map.fitBounds(bounds, { padding });
    }

    resize(): void {
        if (this.map) this.map.resize();
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
        if (!this.accessToken) return [];
        const response = await fetch(
            `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${this.accessToken}&limit=5&types=place,address,poi`
        );
        if (!response.ok) return [];
        const data = await response.json();
        return data.features.map((feature: any) => ({
            coordinates: { lng: feature.geometry.coordinates[0], lat: feature.geometry.coordinates[1] },
            address: feature.place_name,
            placeId: feature.id,
        }));
    }

    async reverseGeocode(coordinates: MapCoordinates): Promise<MapLocationResult | null> {
        if (!this.accessToken) return null;
        const response = await fetch(
            `https://api.mapbox.com/geocoding/v5/mapbox.places/${coordinates.lng},${coordinates.lat}.json?access_token=${this.accessToken}&limit=1`
        );
        if (!response.ok) return null;
        const data = await response.json();
        if (data.features.length === 0) return null;
        const feature = data.features[0];
        return {
            coordinates: { lng: feature.geometry.coordinates[0], lat: feature.geometry.coordinates[1] },
            address: feature.place_name,
            placeId: feature.id,
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
        mapboxgl: any;
    }
}
