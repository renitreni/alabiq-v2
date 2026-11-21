<?php

namespace Database\Factories;

use App\Models\Cuisine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cuisine>
 */
class CuisineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Burgers', 'Pizza', 'Asian', 'Italian', 'Mexican',
            'Indian', 'Chinese', 'Japanese', 'Thai', 'Desserts',
            'Coffee', 'Healthy', 'Seafood', 'BBQ', 'Sandwiches',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => fake()->randomElement(['🍔', '🍕', '🥢', '🍝', '🌮', '🍛', '🥟', '🍣', '🍜', '🍰', '☕', '🥗', '🦐', '🍖', '🥪']),
            'image' => null,
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
