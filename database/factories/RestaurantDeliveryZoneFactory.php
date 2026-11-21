<?php

namespace Database\Factories;

use App\Models\RestaurantBranch;
use App\Models\RestaurantDeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantDeliveryZone>
 */
class RestaurantDeliveryZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_branch_id' => RestaurantBranch::factory(),
            'name' => fake()->randomElement(['Zone A', 'Zone B', 'Central', 'Suburbs']),
            'zone_type' => 'circle',
            'coordinates' => [
                'center' => [
                    'lat' => fake()->latitude(24.0, 25.0),
                    'lng' => fake()->longitude(46.0, 47.0),
                ],
                'radius' => fake()->numberBetween(1000, 10000),
            ],
            'delivery_fee' => fake()->randomFloat(2, 0, 25),
            'minimum_order' => fake()->randomFloat(2, 0, 50),
            'estimated_delivery_time' => fake()->numberBetween(15, 60),
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    /**
     * A polygon zone around a fixed Riyadh-centered area for deterministic tests.
     *
     * Note: containsPoint() expects [lat, lng] pairs (not GeoJSON order).
     */
    public function polygon(): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_type' => 'polygon',
            'coordinates' => [
                'coordinates' => [[
                    [24.7000, 46.6000],
                    [24.7000, 46.7000],
                    [24.8000, 46.7000],
                    [24.8000, 46.6000],
                    [24.7000, 46.6000],
                ]],
            ],
        ]);
    }
}
