<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\RiderAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderAvailabilityFactory extends Factory
{
    protected $model = RiderAvailability::class;

    public function definition(): array
    {
        return [
            'rider_id' => Rider::factory(),
            'status' => RiderAvailability::STATUS_OFFLINE,
            'note' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => ['status' => RiderAvailability::STATUS_ONLINE]);
    }

    public function busy(): static
    {
        return $this->state(fn () => ['status' => RiderAvailability::STATUS_BUSY]);
    }
}
