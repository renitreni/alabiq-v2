<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderFactory extends Factory
{
    protected $model = Rider::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => User::ROLE_RIDER]),
            'vehicle_type' => Rider::VEHICLE_BIKE,
            'plate_number' => strtoupper($this->faker->bothify('???-####')),
            'is_online' => false,
            'is_active' => true,
            'total_deliveries' => 0,
            'rating' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => ['is_online' => true]);
    }

    public function offline(): static
    {
        return $this->state(fn () => ['is_online' => false]);
    }

    public function vehicle(string $vehicleType): static
    {
        return $this->state(fn () => ['vehicle_type' => $vehicleType]);
    }
}
