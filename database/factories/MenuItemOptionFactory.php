<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemOption>
 */
class MenuItemOptionFactory extends Factory
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
            'name' => fake()->randomElement(['Size', 'Add-ons', 'Crust Type', 'Spice Level', 'Sauce']),
            'type' => fake()->randomElement(['select', 'radio', 'checkbox', 'multi_select']),
            'is_required' => fake()->boolean(50),
            'min_selections' => 0,
            'max_selections' => fake()->optional()->numberBetween(1, 5),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
