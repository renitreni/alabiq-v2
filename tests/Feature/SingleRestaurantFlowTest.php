<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleRestaurantFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_single_restaurant_storefront(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get('/')->assertRedirect(route('restaurants.show', ['slug' => $restaurant->slug], false));
    }

    public function test_restaurants_index_redirects_to_the_single_restaurant_storefront(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get('/restaurants')->assertRedirect(route('restaurants.show', ['slug' => $restaurant->slug], false));
    }

    public function test_search_redirects_home(): void
    {
        $this->get('/search')->assertRedirect('/');
    }

    public function test_restaurants_index_redirects_home_when_no_restaurant_exists(): void
    {
        $this->get('/restaurants')->assertRedirect('/');
    }

    public function test_public_registration_rejects_restaurant_role(): void
    {
        $this->post('/register', [
            'name' => 'Self Serve Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'restaurant',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_home_renders_when_no_restaurant_exists(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_home_shows_login_buttons_for_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('marketplace/Home')
                ->where('auth.user', null));
    }

    public function test_home_hides_login_buttons_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('marketplace/Home')
                ->where('auth.user.id', $user->id));
    }

    public function test_restaurant_user_without_restaurant_is_redirected_to_setup(): void
    {
        $user = User::factory()->role(User::ROLE_RESTAURANT)->create();

        $this->actingAs($user)
            ->get('/restaurant/dashboard')
            ->assertRedirect(route('restaurant.setup', absolute: false));
    }

    public function test_setup_page_renders_for_restaurant_user_without_restaurant(): void
    {
        $user = User::factory()->role(User::ROLE_RESTAURANT)->create();

        $this->actingAs($user)->get('/restaurant/setup')->assertOk();
    }

    public function test_setup_page_redirects_once_user_has_restaurant(): void
    {
        $user = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->get('/restaurant/setup')
            ->assertRedirect(route('restaurant.dashboard', absolute: false));
    }

    public function test_setup_creates_the_single_restaurant(): void
    {
        $user = User::factory()->role(User::ROLE_RESTAURANT)->create();

        $this->actingAs($user)
            ->post('/restaurant/setup', [
                'name' => 'Burger Joint',
                'phone' => '+966500000000',
                'address' => 'King Fahd Road',
                'city' => 'Riyadh',
                'delivery_fee' => '10',
                'minimum_order' => '20',
                'estimated_delivery_time' => '25',
            ])
            ->assertRedirect(route('restaurant.dashboard', absolute: false))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('restaurants', [
            'owner_id' => $user->id,
            'name' => 'Burger Joint',
            'slug' => 'burger-joint',
            'is_active' => true,
            'is_approved' => true,
            'accepts_orders' => true,
        ]);
    }

    public function test_setup_is_blocked_once_a_restaurant_exists(): void
    {
        Restaurant::factory()->create();
        $user = User::factory()->role(User::ROLE_RESTAURANT)->create();

        $this->actingAs($user)
            ->post('/restaurant/setup', [
                'name' => 'Second Restaurant',
                'phone' => '+966500000000',
                'address' => 'King Fahd Road',
                'city' => 'Riyadh',
            ])
            ->assertRedirect(route('restaurant.dashboard', absolute: false));

        $this->assertDatabaseMissing('restaurants', ['name' => 'Second Restaurant']);
    }

    public function test_non_restaurant_users_cannot_access_setup(): void
    {
        $user = User::factory()->role(User::ROLE_CUSTOMER)->create();

        $this->actingAs($user)->get('/restaurant/setup')->assertForbidden();
        $this->actingAs($user)->post('/restaurant/setup', [])->assertForbidden();
    }
}
