<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
            'description' => fake()->paragraph(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'city' => fake()->city(),
            'district' => fake()->streetName(),
            'address' => fake()->address(),
            'commission_rate' => fake()->randomFloat(2, 10, 25),
            'delivery_fee' => fake()->randomFloat(2, 0, 20),
            'minimum_order' => fake()->randomFloat(2, 0, 50),
            'estimated_delivery_time' => fake()->numberBetween(15, 60),
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ];
    }
}
