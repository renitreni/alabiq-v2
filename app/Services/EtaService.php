<?php

namespace App\Services;

use App\Models\Rider;

/**
 * Delivery ETA estimation.
 *
 * ETA = distance (km) / vehicle speed (km/h) * 60 minutes, rounded up.
 * Per-vehicle speeds come from config('alabiq.eta_speeds'); unknown
 * vehicles fall back to the bike speed.
 */
class EtaService
{
    public function __construct(private ?LocationService $locationService = null)
    {
        $this->locationService ??= new LocationService;
    }

    /**
     * Estimated minutes for a straight-line distance at a vehicle speed.
     */
    public function estimateMinutes(float $distanceKm, string $vehicleType = Rider::VEHICLE_BIKE): int
    {
        $speeds = config('alabiq.eta_speeds', []);
        $speed = (float) ($speeds[$vehicleType] ?? $speeds[Rider::VEHICLE_BIKE] ?? 20);

        if ($speed <= 0) {
            $speed = 20.0;
        }

        return (int) ceil(($distanceKm / $speed) * 60);
    }

    /**
     * ETA for a rider to reach a destination from their latest location.
     *
     * Falls back to a straight-line haversine estimate when no live location
     * or Mapbox directions are available.
     */
    public function estimateForRider(Rider $rider, float $toLat, float $toLng): int
    {
        $latest = $rider->latestLocation;

        if (! $latest) {
            return 0;
        }

        $directions = $this->locationService->directions(
            (float) $latest->latitude,
            (float) $latest->longitude,
            $toLat,
            $toLng
        );

        if ($directions && $directions['duration_s'] > 0) {
            return (int) ceil($directions['duration_s'] / 60);
        }

        $distanceKm = $this->locationService->haversineKm(
            (float) $latest->latitude,
            (float) $latest->longitude,
            $toLat,
            $toLng
        );

        return $this->estimateMinutes($distanceKm, $rider->vehicle_type);
    }
}
