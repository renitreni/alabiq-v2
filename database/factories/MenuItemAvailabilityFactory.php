<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemAvailability;
use App\Models\RestaurantBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemAvailability>
 */
class MenuItemAvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'restaurant_branch_id' => RestaurantBranch::factory(),
            'is_available' => true,
            'price_override' => null,
            'available_from' => null,
            'available_until' => null,
            'days_of_week' => null,
            'available_from_date' => null,
            'available_until_date' => null,
            'stock_quantity' => null,
            'low_stock_threshold' => 5,
            'track_stock' => false,
        ];
    }

    /**
     * Mark the item as unavailable at the branch.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
