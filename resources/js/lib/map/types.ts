export interface MapCoordinates {
    lat: number;
    lng: number;
}

export interface MapLocationResult {
    coordinates: MapCoordinates;
    address: string;
    placeId?: string;
}

export interface MapProviderOptions {
    accessToken?: string;
    [key: string]: unknown;
}

export interface MapProvider {
    readonly name: string;
    isReady(): boolean;
    init(container: HTMLElement, options?: MapProviderOptions): void;
    addMarker(coordinates: MapCoordinates, options?: any): void;
    centerOn(coordinates: MapCoordinates, zoom?: number): void;
    onMapClick(handler: (coordinates: MapCoordinates) => void): void;
    resize(): void;
    getCurrentLocation(): Promise<MapCoordinates>;
    geocode(query: string): Promise<MapLocationResult[]>;
    reverseGeocode(coordinates: MapCoordinates): Promise<MapLocationResult | null>;
    destroy(): void;
}
