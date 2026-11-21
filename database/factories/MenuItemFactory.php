<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Classic Cheeseburger', 'Double Bacon Burger', 'Chicken Burger', 'Veggie Burger',
            'Margherita Pizza', 'Pepperoni Pizza', 'BBQ Chicken Pizza', 'Veggie Pizza',
            'Caesar Salad', 'Greek Salad', 'Chicken Caesar Salad',
            'Chocolate Cake', 'Ice Cream', 'Cheesecake',
            'Coca Cola', 'Sprite', 'Water', 'Coffee', 'Tea',
        ]);

        return [
            'restaurant_id' => Restaurant::factory(),
            'menu_category_id' => MenuCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 10000)),
            'description' => fake()->paragraph(),
            'image' => null,
            'price' => fake()->randomFloat(2, 10, 100),
            'compare_at_price' => null,
            'calories' => fake()->optional()->numberBetween(200, 1500),
            'preparation_time' => fake()->randomElement(['10 mins', '15 mins', '20 mins', '25 mins', '30 mins']),
            'is_vegetarian' => fake()->boolean(20),
            'is_vegan' => fake()->boolean(10),
            'is_gluten_free' => fake()->boolean(15),
            'is_spicy' => fake()->boolean(25),
            'is_popular' => fake()->boolean(30),
            'is_available' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'metadata' => null,
        ];
    }
}
