<?php

namespace Tests\Unit\Services;

use App\Models\Rider;
use App\Models\RiderLocation;
use App\Services\EtaService;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    use RefreshDatabase;

    private EtaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('alabiq.eta_speeds', [
            'bike' => 20,
            'car' => 40,
            'scooter' => 25,
        ]);
        $this->service = new EtaService(new LocationService);
    }

    public function test_ten_km_by_bike_at_20kmh_is_30_minutes(): void
    {
        $this->assertSame(30, $this->service->estimateMinutes(10, Rider::VEHICLE_BIKE));
    }

    public function test_vehicle_speeds_scale_eta(): void
    {
        // Same 20 km: car at 40 km/h = 30 min, bike at 20 km/h = 60 min.
        $this->assertSame(30, $this->service->estimateMinutes(20, Rider::VEHICLE_CAR));
        $this->assertSame(60, $this->service->estimateMinutes(20, Rider::VEHICLE_BIKE));
    }

    public function test_eta_rounds_up_partial_minutes(): void
    {
        // 1 km at 20 km/h = 3 minutes exactly; 1.1 km = 3.3 -> 4.
        $this->assertSame(3, $this->service->estimateMinutes(1, Rider::VEHICLE_BIKE));
        $this->assertSame(4, $this->service->estimateMinutes(1.1, Rider::VEHICLE_BIKE));
    }

    public function test_unknown_vehicle_falls_back_to_bike_speed(): void
    {
        $this->assertSame(60, $this->service->estimateMinutes(20, 'hovercraft'));
    }

    public function test_zero_or_invalid_speed_falls_back_to_20kmh(): void
    {
        Config::set('alabiq.eta_speeds', ['bike' => 0]);

        $this->assertSame(60, (new EtaService)->estimateMinutes(20, Rider::VEHICLE_BIKE));
    }

    public function test_estimate_for_rider_without_location_is_zero(): void
    {
        $rider = Rider::factory()->create();

        $this->assertSame(0, $this->service->estimateForRider($rider, 24.75, 46.70));
    }

    public function test_estimate_for_rider_uses_mapbox_directions_when_available(): void
    {
        Config::set('alabiq.mapbox.token', 'test-token');

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [['distance' => 6000.0, 'duration' => 1200.0]],
            ]),
        ]);

        $rider = Rider::factory()->create();
        RiderLocation::factory()->create([
            'rider_id' => $rider->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        $this->assertSame(20, $this->service->estimateForRider($rider, 24.75, 46.70));
    }

    public function test_estimate_for_rider_falls_back_to_haversine_without_mapbox(): void
    {
        Config::set('alabiq.mapbox.token', null);

        $rider = Rider::factory()->create();
        RiderLocation::factory()->create([
            'rider_id' => $rider->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        // ~5.3 km straight line at 20 km/h => ~16 min.
        $eta = $this->service->estimateForRider($rider, 24.75, 46.70);

        $this->assertGreaterThan(10, $eta);
        $this->assertLessThan(25, $eta);
    }
}
