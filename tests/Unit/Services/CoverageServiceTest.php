<?php

namespace Tests\Unit\Services;

use App\Models\CustomerAddress;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantDeliveryZone;
use App\Models\User;
use App\Services\CoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoverageService $service;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CoverageService;

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => Restaurant::factory(),
            'latitude' => 24.7100,
            'longitude' => 46.6100,
            'delivery_fee' => 12.00,
            'minimum_order' => 30.00,
            'estimated_delivery_time' => 45,
        ]);
    }

    private function makeCircleZone(array $overrides = []): RestaurantDeliveryZone
    {
        return RestaurantDeliveryZone::factory()->create(array_merge([
            'restaurant_branch_id' => $this->branch->id,
            'zone_type' => 'circle',
            'coordinates' => [
                'center' => ['lat' => 24.7100, 'lng' => 46.6100],
                'radius' => 3000, // meters
            ],
            'delivery_fee' => 5.00,
            'minimum_order' => 10.00,
            'estimated_delivery_time' => 25,
            'priority' => 1,
            'is_active' => true,
        ], $overrides));
    }

    public function test_point_inside_circle_zone_gets_zone_terms(): void
    {
        $this->makeCircleZone();

        // ~780m from the center, well inside the 3km radius.
        $result = $this->service->checkCoverage($this->branch->id, 24.7150, 46.6150);

        $this->assertTrue($result['covered']);
        $this->assertTrue($result['in_custom_zone']);
        $this->assertSame(5.0, $result['delivery_fee']);
        $this->assertSame(10.0, $result['minimum_order']);
        $this->assertSame(25, $result['estimated_delivery_time']);
        $this->assertSame($this->branch->id, $result['branch_id']);
    }

    public function test_point_outside_all_zones_falls_back_to_branch_terms(): void
    {
        $this->makeCircleZone();

        $result = $this->service->checkCoverage($this->branch->id, 24.9000, 46.9000);

        $this->assertTrue($result['covered']);
        $this->assertFalse($result['in_custom_zone']);
        $this->assertSame(12.0, $result['delivery_fee']);
        $this->assertSame('Standard Delivery', $result['zone_name']);
    }

    public function test_point_inside_polygon_zone_gets_zone_terms(): void
    {
        RestaurantDeliveryZone::factory()->polygon()->create([
            'restaurant_branch_id' => $this->branch->id,
            'delivery_fee' => 7.00,
        ]);

        $result = $this->service->checkCoverage($this->branch->id, 24.7500, 46.6500);

        $this->assertTrue($result['covered']);
        $this->assertTrue($result['in_custom_zone']);
        $this->assertSame(7.0, $result['delivery_fee']);
    }

    public function test_point_outside_polygon_falls_back(): void
    {
        RestaurantDeliveryZone::factory()->polygon()->create([
            'restaurant_branch_id' => $this->branch->id,
            'delivery_fee' => 7.00,
        ]);

        $result = $this->service->checkCoverage($this->branch->id, 24.8500, 46.6500);

        $this->assertTrue($result['covered']);
        $this->assertFalse($result['in_custom_zone']);
        $this->assertSame(12.0, $result['delivery_fee']);
    }

    public function test_unknown_branch_is_not_covered(): void
    {
        $result = $this->service->checkCoverage(999999, 24.7100, 46.6100);

        $this->assertFalse($result['covered']);
        $this->assertFalse($result['in_custom_zone']);
        $this->assertNull($result['zone_name']);
        $this->assertSame(0.0, $result['delivery_fee']);
    }

    public function test_highest_priority_zone_wins_when_zones_overlap(): void
    {
        $this->makeCircleZone(['name' => 'Low Priority', 'priority' => 1, 'delivery_fee' => 15.00]);
        $this->makeCircleZone(['name' => 'High Priority', 'priority' => 10, 'delivery_fee' => 3.00]);

        $result = $this->service->checkCoverage($this->branch->id, 24.7100, 46.6100);

        $this->assertTrue($result['in_custom_zone']);
        $this->assertSame(3.0, $result['delivery_fee']);
        $this->assertSame('High Priority', $result['zone_name']);
    }

    public function test_inactive_zones_are_ignored(): void
    {
        $this->makeCircleZone(['is_active' => false]);

        $result = $this->service->checkCoverage($this->branch->id, 24.7100, 46.6100);

        $this->assertTrue($result['covered']);
        $this->assertFalse($result['in_custom_zone']);
        $this->assertSame('Standard Delivery', $result['zone_name']);
    }

    public function test_is_covered_shorthand(): void
    {
        $this->makeCircleZone();

        $this->assertTrue($this->service->isCovered($this->branch->id, 24.7100, 46.6100));
        $this->assertFalse($this->service->isCovered(999999, 24.7100, 46.6100));
    }

    public function test_validate_address_without_coordinates_is_valid(): void
    {
        $address = CustomerAddress::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);

        $result = $this->service->validateAddressForBranch($address, $this->branch->id);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['coverage']);
    }

    public function test_validate_null_address_is_valid(): void
    {
        $result = $this->service->validateAddressForBranch(null, $this->branch->id);

        $this->assertTrue($result['valid']);
    }

    public function test_validate_address_with_coordinates_returns_coverage(): void
    {
        $this->makeCircleZone();
        $address = CustomerAddress::factory()->create([
            'latitude' => 24.7100,
            'longitude' => 46.6100,
        ]);

        $result = $this->service->validateAddressForBranch($address, $this->branch->id);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['coverage']['in_custom_zone']);
    }

    public function test_validate_address_against_unknown_branch_fails(): void
    {
        $address = CustomerAddress::factory()->create([
            'latitude' => 24.7100,
            'longitude' => 46.6100,
        ]);

        $result = $this->service->validateAddressForBranch($address, 999999);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function test_address_factory_belongs_to_customer(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->customerAddresses()->where('id', $address->id)->exists());
    }
}
