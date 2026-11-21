<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionService;
    }

    private function makeOrder(float $subtotal, float $deliveryFee): Order
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        return Order::factory()->create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $subtotal + $deliveryFee,
        ]);
    }

    public function test_calculates_commission_with_default_rate(): void
    {
        config(['alabiq.platform_commission_rate' => 0.15]);

        $order = $this->makeOrder(100.00, 10.00);
        $result = $this->service->calculate($order);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(15.00, $result['platform_commission']);
        $this->assertEquals(85.00, $result['restaurant_share']);
        $this->assertEquals(10.00, $result['delivery_fee']);
        $this->assertEquals(95.00, $result['total']);
    }

    public function test_calculates_commission_with_custom_rate(): void
    {
        config(['alabiq.platform_commission_rate' => 0.20]);

        $order = $this->makeOrder(200.00, 15.00);
        $result = $this->service->calculate($order);

        $this->assertEquals(40.00, $result['platform_commission']);
        $this->assertEquals(160.00, $result['restaurant_share']);
        $this->assertEquals(15.00, $result['delivery_fee']);
        $this->assertEquals(175.00, $result['total']);
    }

    public function test_commission_shares_sum_to_subtotal(): void
    {
        config(['alabiq.platform_commission_rate' => 0.15]);

        $order = $this->makeOrder(99.99, 12.50);
        $result = $this->service->calculate($order);

        $this->assertEquals(
            round($result['subtotal'], 2),
            round($result['platform_commission'] + $result['restaurant_share'], 2)
        );
    }

    public function test_zero_subtotal_returns_zero_shares(): void
    {
        $order = $this->makeOrder(0, 0);
        $result = $this->service->calculate($order);

        $this->assertEquals(0, $result['platform_commission']);
        $this->assertEquals(0, $result['restaurant_share']);
        $this->assertEquals(0, $result['total']);
    }

    public function test_decimal_precision_is_rounded_to_two_places(): void
    {
        config(['alabiq.platform_commission_rate' => 0.15]);

        $order = $this->makeOrder(33.33, 5.00);
        $result = $this->service->calculate($order);

        $this->assertEquals(5.00, $result['platform_commission']);
        $this->assertEquals(28.33, $result['restaurant_share']);
    }
}
