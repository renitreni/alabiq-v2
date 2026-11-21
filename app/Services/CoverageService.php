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
 *    delivery terms (zone_name = "Standard Delivery").
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
        if (! $address || $address->latitude === null || $address->longitude === null) {
            return ['valid' => true, 'coverage' => null, 'error' => null];
        }

        $coverage = $this->checkCoverage($branchId, (float) $address->latitude, (float) $address->longitude);

        if (! $coverage['covered']) {
            return ['valid' => false, 'coverage' => $coverage, 'error' => 'Selected branch is not available'];
        }

        return ['valid' => true, 'coverage' => $coverage, 'error' => null];
    }
}
