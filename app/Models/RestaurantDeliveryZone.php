<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantDeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_branch_id',
        'name',
        'zone_type',
        'coordinates',
        'delivery_fee',
        'minimum_order',
        'estimated_delivery_time',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'delivery_fee' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'estimated_delivery_time' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderByDesc('priority');
    }

    public function containsPoint(float $lat, float $lng): bool
    {
        if ($this->zone_type === 'circle') {
            $center = $this->coordinates['center'] ?? [];
            $radius = $this->coordinates['radius'] ?? 0;
            if (empty($center)) {
                return false;
            }

            $distance = $this->calculateDistance(
                $lat, $lng,
                $center['lat'], $center['lng']
            );

            return $distance <= $radius;
        }

        // Polygon check (ray casting algorithm)
        $polygon = $this->coordinates['coordinates'] ?? [];
        if (empty($polygon)) {
            return false;
        }

        return $this->pointInPolygon($lat, $lng, $polygon[0]);
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0] ?? 0;
            $yi = $polygon[$i][1] ?? 0;
            $xj = $polygon[$j][0] ?? 0;
            $yj = $polygon[$j][1] ?? 0;

            $intersect = (($yi > $lng) !== ($yj > $lng)) &&
                         ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }
}
