<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => $this->faker->randomElement(['Home', 'Work', 'Other']),
            'street' => $this->faker->streetAddress(),
            'building' => $this->faker->buildingNumber(),
            'floor' => (string) $this->faker->numberBetween(1, 10),
            'apartment' => (string) $this->faker->numberBetween(1, 50),
            'landmark' => $this->faker->optional()->word(),
            'city' => 'Riyadh',
            'district' => $this->faker->citySuffix(),
            'postal_code' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(24.5, 25.0),
            'longitude' => $this->faker->longitude(46.5, 47.0),
            'place_id' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
