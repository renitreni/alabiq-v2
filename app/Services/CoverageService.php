<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\RestaurantBranch;

/**
 * Delivery coverage checking against branch delivery zones.
 *
 * Rules:
 *  - A point inside an active delivery zone (circle or polygon) gets that
 *    zone's fee / minimum order / ETA, highest priority zone first.
 *  - A point outside all zones falls back to the branch's standard
 *    delivery terms (zone_name = "Standard Delivery"), unless the branch
 *    sets delivery_radius_km and the point is farther than that radius.
 *  - An unknown branch is not covered.
 */
class CoverageService
{
    /**
     * Check whether a coordinate is covered by a branch and on what terms.
     *
     * @return array{covered: bool, in_custom_zone: bool, delivery_fee: float, minimum_order: float, estimated_delivery_time: int, zone_name: string|null, branch_id: int}
     */
    public function checkCoverage(int $branchId, float $latitude, float $longitude): array
    {
        $branch = RestaurantBranch::query()
            ->with(['deliveryZones' => fn ($q) => $q->active()])
            ->find($branchId);

        if (! $branch) {
            return [
                'covered' => false,
                'in_custom_zone' => false,
                'delivery_fee' => 0.0,
                'minimum_order' => 0.0,
                'estimated_delivery_time' => 0,
                'zone_name' => null,
                'branch_id' => $branchId,
            ];
        }

        foreach ($branch->deliveryZones as $zone) {
            if ($zone->containsPoint($latitude, $longitude)) {
                return [
                    'covered' => true,
                    'in_custom_zone' => true,
                    'delivery_fee' => (float) $zone->delivery_fee,
                    'minimum_order' => (float) $zone->minimum_order,
                    'estimated_delivery_time' => (int) $zone->estimated_delivery_time,
                    'zone_name' => $zone->name,
                    'branch_id' => $branch->id,
                ];
            }
        }

        if ($branch->delivery_radius_km !== null && ! $this->isWithinRadius($branch, $latitude, $longitude)) {
            return [
                'covered' => false,
                'in_custom_zone' => false,
                'delivery_fee' => 0.0,
                'minimum_order' => 0.0,
                'estimated_delivery_time' => 0,
                'zone_name' => null,
                'branch_id' => $branch->id,
            ];
        }

        return [
            'covered' => true,
            'in_custom_zone' => false,
            'delivery_fee' => (float) $branch->delivery_fee,
            'minimum_order' => (float) $branch->minimum_order,
            'estimated_delivery_time' => (int) $branch->estimated_delivery_time,
            'zone_name' => 'Standard Delivery',
            'branch_id' => $branch->id,
        ];
    }

    /**
     * Whether a point is within the branch's delivery radius. A branch
     * without coordinates cannot be measured, so it is treated as outside.
     */
    private function isWithinRadius(RestaurantBranch $branch, float $latitude, float $longitude): bool
    {
        if ($branch->latitude === null || $branch->longitude === null) {
            return false;
        }

        $distanceKm = app(LocationService::class)->haversineKm(
            (float) $branch->latitude,
            (float) $branch->longitude,
            $latitude,
            $longitude
        );

        return $distanceKm <= (float) $branch->delivery_radius_km;
    }

    /**
     * Boolean shorthand for checkCoverage().
     */
    public function isCovered(int $branchId, float $latitude, float $longitude): bool
    {
        return $this->checkCoverage($branchId, $latitude, $longitude)['covered'];
    }

    /**
     * Validate a customer address against a branch before checkout.
     *
     * Addresses without coordinates (manual text entry, geocoding pending)
     * skip the coverage check but remain valid.
     *
     * @return array{valid: bool, coverage: array|null, error: string|null}
     */
    public function validateAddressForBranch(?CustomerAddress $address, int $branchId): array
    {
        if (! $address) {
            return ['valid' => true, 'coverage' => null, 'error' => null];
        }

        if ($address->latitude === null || $address->longitude === null) {
            $hasRadius = RestaurantBranch::query()->whereKey($branchId)->whereNotNull('delivery_radius_km')->exists();

            return $hasRadius
                ? ['valid' => false, 'coverage' => null, 'error' => 'Pin your delivery address on the map so we can confirm it is within the delivery area']
                : ['valid' => true, 'coverage' => null, 'error' => null];
        }

        $coverage = $this->checkCoverage($branchId, (float) $address->latitude, (float) $address->longitude);

        if (! $coverage['covered']) {
            return ['valid' => false, 'coverage' => $coverage, 'error' => 'Your delivery address is outside this branch\'s delivery area'];
        }

        return ['valid' => true, 'coverage' => $coverage, 'error' => null];
    }
}
