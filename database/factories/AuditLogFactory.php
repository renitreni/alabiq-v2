<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(User::ROLE_SUPER_ADMIN),
            'action' => 'settings.updated',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => ['vat_rate' => 0.15],
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
