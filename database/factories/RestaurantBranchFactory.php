<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RestaurantBranch>
 */
class RestaurantBranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Main Branch', 'Downtown Branch', 'Mall Branch', 'Airport Branch',
            'North Branch', 'South Branch', 'East Branch', 'West Branch',
        ]);

        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 1000)),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'latitude' => fake()->latitude(24.0, 25.0),
            'longitude' => fake()->longitude(45.0, 47.0),
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:00',
            'delivery_fee' => fake()->randomFloat(2, 0, 20),
            'minimum_order' => fake()->randomFloat(2, 0, 50),
            'estimated_delivery_time' => fake()->numberBetween(15, 60),
            'is_active' => true,
            'accepts_orders' => true,
            'metadata' => null,
        ];
    }
}
