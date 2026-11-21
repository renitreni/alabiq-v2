<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rider_id' => Rider::factory(),
            'latitude' => $this->faker->latitude(24.5, 25.0), // Riyadh area
            'longitude' => $this->faker->longitude(46.5, 47.0),
            'speed' => $this->faker->randomFloat(2, 0, 60),
            'heading' => $this->faker->randomFloat(2, 0, 360),
            'accuracy' => $this->faker->randomFloat(2, 1, 50),
            'recorded_at' => now(),
        ];
    }
}
