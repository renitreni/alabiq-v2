<?php

namespace Tests\Unit\Services;

use App\Services\LocationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistanceCalculationTest extends TestCase
{
    private LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationService;
    }

    public function test_same_point_is_zero_kilometres(): void
    {
        $this->assertSame(0.0, $this->service->haversineKm(24.7136, 46.6753, 24.7136, 46.6753));
    }

    public function test_known_city_distance(): void
    {
        // Riyadh -> Jeddah is roughly 950 km by great circle.
        $km = $this->service->haversineKm(24.7136, 46.6753, 21.4858, 39.1925);

        $this->assertGreaterThan(800.0, $km);
        $this->assertLessThan(1000.0, $km);
    }

    public function test_distance_is_symmetric(): void
    {
        $ab = $this->service->haversineKm(24.7136, 46.6753, 21.4858, 39.1925);
        $ba = $this->service->haversineKm(21.4858, 39.1925, 24.7136, 46.6753);

        $this->assertSame($ab, $ba);
    }

    public function test_one_degree_longitude_at_equator_is_about_111_km(): void
    {
        $km = $this->service->haversineKm(0.0, 0.0, 0.0, 1.0);

        $this->assertGreaterThan(110.0, $km);
        $this->assertLessThan(112.0, $km);
    }

    public function test_geocode_returns_coordinates_from_mapbox(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'features' => [
                    ['geometry' => ['coordinates' => [46.6753, 24.7136]]],
                ],
            ]),
        ]);

        $result = $this->service->geocode('Riyadh');

        $this->assertSame([46.6753, 24.7136], $result);
    }

    public function test_geocode_returns_null_without_token(): void
    {
        Config::set('alabiq.mapbox.token', null);

        $this->assertNull($this->service->geocode('Riyadh'));
    }

    public function test_geocode_returns_null_on_http_error(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake(['api.mapbox.com/*' => Http::response('Server Error', 500)]);

        $this->assertNull($this->service->geocode('Riyadh'));
    }

    public function test_geocode_returns_null_on_empty_features(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake(['api.mapbox.com/*' => Http::response(['features' => []])]);

        $this->assertNull($this->service->geocode('unknown place xyz'));
    }

    public function test_reverse_geocode_returns_place_name(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'features' => [['place_name' => 'King Fahd Road, Riyadh']],
            ]),
        ]);

        $this->assertSame('King Fahd Road, Riyadh', $this->service->reverseGeocode(24.7136, 46.6753));
    }

    public function test_directions_returns_distance_duration_and_geometry(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [
                    [
                        'distance' => 12500.5,
                        'duration' => 900.0,
                        'geometry' => ['coordinates' => [[46.67, 24.71], [46.68, 24.72]]],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->directions(24.7136, 46.6753, 24.75, 46.70);

        $this->assertSame(12500.5, $result['distance_m']);
        $this->assertSame(900.0, $result['duration_s']);
        $this->assertCount(2, $result['geometry']);
    }

    public function test_directions_returns_null_on_failure(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake(['api.mapbox.com/*' => Http::response('Error', 503)]);

        $this->assertNull($this->service->directions(24.7136, 46.6753, 24.75, 46.70));
    }
}
