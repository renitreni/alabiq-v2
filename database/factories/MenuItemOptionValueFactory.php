<?php

namespace Database\Factories;

use App\Models\MenuItemOption;
use App\Models\MenuItemOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemOptionValueFactory extends Factory
{
    protected $model = MenuItemOptionValue::class;

    public function definition(): array
    {
        return [
            'menu_item_option_id' => MenuItemOption::factory(),
            'name' => $this->faker->randomElement(['Small', 'Medium', 'Large', 'Extra Large', 'Regular', 'Family Size']),
            'price_adjustment' => $this->faker->randomFloat(2, 0, 10),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
