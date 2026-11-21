<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'value' => 'default',
            'type' => 'string',
            'is_public' => false,
        ];
    }

    public function numeric(string $key, float $value): static
    {
        return $this->state(fn () => [
            'key' => $key,
            'value' => $value,
            'type' => 'numeric',
        ]);
    }

    public function boolean(string $key, bool $value): static
    {
        return $this->state(fn () => [
            'key' => $key,
            'value' => $value,
            'type' => 'boolean',
        ]);
    }
}
