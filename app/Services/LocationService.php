<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Location primitives and Mapbox gateway calls.
 *
 * Gateway methods never throw: any Mapbox error (network, HTTP status,
 * malformed payload) results in a null return so callers degrade gracefully.
 */
class LocationService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Great-circle distance in kilometres (haversine formula).
     */
    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(sqrt($a));
    }

    /**
     * Geocode a free-text address via Mapbox. Returns [lng, lat] or null.
     *
     * @return array{0: float, 1: float}|null
     */
    public function geocode(string $address): ?array
    {
        $token = config('alabiq.mapbox.token');

        if (! $token || trim($address) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/'.urlencode($address).'.json',
                [
                    'access_token' => $token,
                    'limit' => 1,
                ]
            );

            if (! $response->ok()) {
                return null;
            }

            $coordinates = $response->json('features.0.geometry.coordinates');

            if (! is_array($coordinates) || count($coordinates) < 2) {
                return null;
            }

            return [(float) $coordinates[0], (float) $coordinates[1]];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reverse geocode coordinates into a display address, or null on failure.
     */
    public function reverseGeocode(float $lat, float $lng): ?string
    {
        $token = config('alabiq.mapbox.token');

        if (! $token) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get(
                "https://api.mapbox.com/geocoding/v5/mapbox.places/{$lng},{$lat}.json",
                ['access_token' => $token, 'limit' => 1]
            );

            if (! $response->ok()) {
                return null;
            }

            return $response->json('features.0.place_name');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Driving directions between two points.
     *
     * @return array{distance_m: float, duration_s: float, geometry: array}|null
     */
    public function directions(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $token = config('alabiq.mapbox.token');

        if (! $token) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get(
                "https://api.mapbox.com/directions/v5/mapbox/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}",
                [
                    'access_token' => $token,
                    'geometries' => 'geojson',
                    'overview' => 'full',
                ]
            );

            if (! $response->ok()) {
                return null;
            }

            $route = $response->json('routes.0');

            if (! is_array($route) || ! isset($route['distance'], $route['duration'])) {
                return null;
            }

            return [
                'distance_m' => (float) $route['distance'],
                'duration_s' => (float) $route['duration'],
                'geometry' => $route['geometry']['coordinates'] ?? [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
