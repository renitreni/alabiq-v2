<?php

namespace Database\Factories;

use App\Models\PlatformCommission;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformCommissionFactory extends Factory
{
    protected $model = PlatformCommission::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'rate' => 15.00,
            'is_active' => true,
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['restaurant_id' => null]);
    }
}
