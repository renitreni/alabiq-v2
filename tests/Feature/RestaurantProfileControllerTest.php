<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RestaurantProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_restaurant_address_and_coordinates(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'address' => 'King Fahd Road',
            'city' => 'Riyadh',
            'district' => 'Al Olaya',
            'postal_code' => '12211',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        $this->actingAs($owner)
            ->get(route('restaurant.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('restaurant/Profile')
                ->where('profile.address', 'King Fahd Road')
                ->where('profile.city', 'Riyadh')
                ->where('profile.district', 'Al Olaya')
                ->where('profile.postal_code', '12211')
                ->where('profile.latitude', 24.7136)
                ->where('profile.longitude', 46.6753));
    }

    public function test_owner_can_update_only_the_main_restaurant_location(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Original Restaurant',
            'is_active' => true,
        ]);
        $otherRestaurant = Restaurant::factory()->create(['address' => 'Other Address']);

        $this->actingAs($owner)
            ->put(route('restaurant.profile.update'), [
                'address' => '123 Kingdom Centre',
                'city' => 'Riyadh',
                'district' => 'Al Olaya',
                'postal_code' => '12211',
                'latitude' => 24.7114,
                'longitude' => 46.6744,
                'name' => 'Changed Restaurant',
                'is_active' => false,
            ])
            ->assertRedirect(route('restaurant.profile'))
            ->assertSessionHas('success', 'Restaurant address and map location updated.');

        $restaurant->refresh();
        $this->assertSame('123 Kingdom Centre', $restaurant->address);
        $this->assertSame('Riyadh', $restaurant->city);
        $this->assertSame('Al Olaya', $restaurant->district);
        $this->assertSame('12211', $restaurant->postal_code);
        $this->assertSame(24.7114, (float) $restaurant->latitude);
        $this->assertSame(46.6744, (float) $restaurant->longitude);
        $this->assertSame('Original Restaurant', $restaurant->name);
        $this->assertTrue($restaurant->is_active);
        $this->assertSame('Other Address', $otherRestaurant->fresh()->address);
    }

    public function test_owner_can_clear_optional_address_fields_and_coordinates(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'district' => 'Al Olaya',
            'postal_code' => '12211',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        $this->actingAs($owner)
            ->put(route('restaurant.profile.update'), [
                'address' => 'King Fahd Road',
                'city' => 'Riyadh',
                'district' => '',
                'postal_code' => '',
                'latitude' => '',
                'longitude' => '',
            ])
            ->assertRedirect(route('restaurant.profile'));

        $restaurant->refresh();
        $this->assertNull($restaurant->district);
        $this->assertNull($restaurant->postal_code);
        $this->assertNull($restaurant->latitude);
        $this->assertNull($restaurant->longitude);
    }

    public function test_profile_update_requires_address_and_city(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('restaurant.profile'))
            ->put(route('restaurant.profile.update'), [])
            ->assertRedirect(route('restaurant.profile'))
            ->assertSessionHasErrors(['address', 'city']);
    }

    public function test_profile_update_rejects_out_of_range_coordinates(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('restaurant.profile.update'), [
                'address' => 'King Fahd Road',
                'city' => 'Riyadh',
                'latitude' => 91,
                'longitude' => 181,
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_profile_update_requires_both_coordinates_when_one_is_present(): void
    {
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('restaurant.profile.update'), [
                'address' => 'King Fahd Road',
                'city' => 'Riyadh',
                'latitude' => 24.7136,
            ])
            ->assertSessionHasErrors('longitude');
    }

    public function test_owner_can_geocode_the_restaurant_address(): void
    {
        config()->set('alabiq.mapbox.token', 'test-token');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mapbox.com/geocoding/v5/mapbox.places/*' => Http::response([
                'features' => [
                    ['geometry' => ['coordinates' => [46.6753, 24.7136]]],
                ],
            ]),
        ]);
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->getJson(route('restaurant.profile.geocode', ['q' => 'King Fahd Road, Riyadh']))
            ->assertOk()
            ->assertJsonPath('data.latitude', 24.7136)
            ->assertJsonPath('data.longitude', 46.6753);

        Http::assertSentCount(1);
    }

    public function test_owner_can_reverse_geocode_the_restaurant_pin(): void
    {
        config()->set('alabiq.mapbox.token', 'test-token');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mapbox.com/geocoding/v5/mapbox.places/*' => Http::response([
                'features' => [
                    [
                        'id' => 'address.123',
                        'place_name' => '123 King Fahd Road, Riyadh',
                        'address' => '123',
                        'text' => 'King Fahd Road',
                        'context' => [
                            ['id' => 'place.1', 'text' => 'Riyadh'],
                            ['id' => 'district.1', 'text' => 'Al Olaya'],
                            ['id' => 'postcode.1', 'text' => '12211'],
                        ],
                    ],
                ],
            ]),
        ]);
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->getJson(route('restaurant.profile.reverse-geocode', ['lat' => 24.7136, 'lng' => 46.6753]))
            ->assertOk()
            ->assertJsonPath('data.street', '123 King Fahd Road')
            ->assertJsonPath('data.city', 'Riyadh')
            ->assertJsonPath('data.district', 'Al Olaya')
            ->assertJsonPath('data.postal_code', '12211');

        Http::assertSentCount(1);
    }

    public function test_location_lookups_return_not_found_when_geocoding_is_unavailable(): void
    {
        config()->set('alabiq.mapbox.token', null);
        $owner = User::factory()->role(User::ROLE_RESTAURANT)->create();
        Restaurant::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->getJson(route('restaurant.profile.geocode', ['q' => 'King Fahd Road']))
            ->assertNotFound()
            ->assertJsonPath('message', 'No coordinates found for the provided address.');

        $this->actingAs($owner)
            ->getJson(route('restaurant.profile.reverse-geocode', ['lat' => 24.7136, 'lng' => 46.6753]))
            ->assertNotFound()
            ->assertJsonPath('message', 'Could not resolve an address for these coordinates.');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('restaurant.profile'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_or_update_restaurant_profile(): void
    {
        $customer = User::factory()->role(User::ROLE_CUSTOMER)->create();

        $this->actingAs($customer)
            ->get(route('restaurant.profile'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->put(route('restaurant.profile.update'), [])
            ->assertForbidden();
    }
}
