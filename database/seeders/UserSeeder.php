<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed users for each platform role.
     */
    public function run(): void
    {
        // Deterministic super admin for local/staging access.
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        // One deployment serves one restaurant: a single owner with the
        // restaurant already linked, so the storefront works out of the box.
        $owner = User::factory()->create([
            'name' => 'Restaurant Owner',
            'email' => 'owner@example.com',
            'role' => User::ROLE_RESTAURANT,
        ]);
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        User::factory()
            ->role(User::ROLE_RIDER)
            ->count(10)
            ->create();

        User::factory()
            ->role(User::ROLE_CUSTOMER)
            ->count(25)
            ->create();
    }
}
