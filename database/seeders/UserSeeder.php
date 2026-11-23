<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed users for each platform role.
     *
     * All accounts are deterministic (keyed by email), so re-running the
     * seeder updates existing records instead of duplicating them.
     */
    public function run(): void
    {
        // Deterministic super admin for local/staging access.
        $this->seedUser('admin@example.com', [
            'name' => 'Super Admin',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        // One deployment serves one restaurant: a single owner with the
        // restaurant already linked, so the storefront works out of the box.
        $owner = $this->seedUser('owner@example.com', [
            'name' => 'Restaurant Owner',
            'role' => User::ROLE_RESTAURANT,
        ]);

        Restaurant::factory()->create(['owner_id' => $owner->id]);

        // Deterministic riders with specific sample emails.
        foreach (range(1, 10) as $number) {
            $this->seedUser("rider{$number}@example.com", [
                'name' => "Rider {$number}",
                'role' => User::ROLE_RIDER,
            ]);
        }

        // Deterministic customers with specific sample emails.
        foreach (range(1, 25) as $number) {
            $this->seedUser("customer{$number}@example.com", [
                'name' => "Customer {$number}",
                'role' => User::ROLE_CUSTOMER,
            ]);
        }
    }

    /**
     * Create or update a user by email with fixture-friendly attributes.
     */
    private function seedUser(string $email, array $attributes): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            array_merge([
                'password' => Hash::make('password'),
            ], $attributes)
        );

        $user->forceFill([
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ])->save();

        return $user;
    }
}
