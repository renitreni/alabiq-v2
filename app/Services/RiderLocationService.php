<?php

namespace App\Services;

use App\Events\RiderLocationUpdated;
use App\Models\Rider;
use App\Models\RiderLocation;
use Illuminate\Support\Facades\Cache;

/**
 * Rider GPS location ingestion with movement throttling.
 *
 * Rules:
 *  - Updates closer than the minimum movement distance (metres) are skipped
 *    entirely: no row, no cache write, no broadcast.
 *  - Accepted updates persist a RiderLocation row, refresh the cached latest
 *    location (TTL from config) and broadcast RiderLocationUpdated.
 *  - Passing meta['force'] = true bypasses the throttle (used to log the
 *    rider's position when a new order is placed).
 *  - The throttle reference is the cached location first, then the most
 *    recently recorded DB location.
 */
class RiderLocationService
{
    public function __construct(private ?LocationService $locationService = null)
    {
        $this->locationService ??= new LocationService;
    }

    /**
     * Cache key holding a rider's latest broadcast location.
     */
    public static function cacheKey(int $riderId): string
    {
        return "rider:location:{$riderId}";
    }

    /**
     * Ingest a location update.
     *
     * @return array{persisted: bool, rider_location: RiderLocation|null, moved_m: float}
     */
    public function update(Rider $rider, float $latitude, float $longitude, array $meta = []): array
    {
        [$lastLat, $lastLng] = $this->lastKnownCoordinates($rider);

        $movedM = $lastLat === null
            ? PHP_FLOAT_MAX
            : $this->locationService->haversineKm($lastLat, $lastLng, $latitude, $longitude) * 1000;

        if (! ($meta['force'] ?? false) && $lastLat !== null && $movedM < (float) config('alabiq.rider_location_min_distance_m', 25)) {
            return ['persisted' => false, 'rider_location' => null, 'moved_m' => $movedM];
        }

        $location = RiderLocation::create([
            'rider_id' => $rider->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $meta['speed'] ?? null,
            'heading' => $meta['heading'] ?? null,
            'accuracy' => $meta['accuracy'] ?? null,
            'recorded_at' => $meta['recorded_at'] ?? now(),
        ]);

        Cache::put(
            self::cacheKey($rider->id),
            ['latitude' => $latitude, 'longitude' => $longitude, 'recorded_at' => $location->recorded_at],
            (int) config('alabiq.rider_location_ttl', 300)
        );

        $assignment = $rider->currentAssignment;
        if ($assignment) {
            RiderLocationUpdated::dispatch(
                $assignment->order_id,
                $rider->id,
                $latitude,
                $longitude,
                $location->recorded_at
            );
        }

        return ['persisted' => true, 'rider_location' => $location, 'moved_m' => $movedM];
    }

    /**
     * Latest known coordinates: cached value first, DB fallback.
     *
     * @return array{0: float|null, 1: float}
     */
    private function lastKnownCoordinates(Rider $rider): array
    {
        $cached = Cache::get(self::cacheKey($rider->id));

        if (is_array($cached) && isset($cached['latitude'], $cached['longitude'])) {
            return [(float) $cached['latitude'], (float) $cached['longitude']];
        }

        $latest = $rider->latestLocation;

        if ($latest) {
            return [(float) $latest->latitude, (float) $latest->longitude];
        }

        return [null, 0.0];
    }
}
