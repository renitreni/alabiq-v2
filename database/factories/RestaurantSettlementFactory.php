<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantSettlementFactory extends Factory
{
    protected $model = RestaurantSettlement::class;

    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 200, 5000);
        $commission = round($gross * 0.15, 2);

        return [
            'restaurant_id' => Restaurant::factory(),
            'period_start' => now()->subWeek()->startOfWeek()->toDateString(),
            'period_end' => now()->subWeek()->endOfWeek()->toDateString(),
            'orders_count' => $this->faker->numberBetween(5, 120),
            'gross_amount' => $gross,
            'commission_amount' => $commission,
            'net_amount' => round($gross - $commission, 2),
            'status' => RestaurantSettlement::STATUS_PENDING,
            'paid_at' => null,
            'reference' => 'STL-'.$this->faker->unique()->numberBetween(100000, 999999),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => RestaurantSettlement::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
