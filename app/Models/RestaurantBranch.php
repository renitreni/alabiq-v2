<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantBranch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'name',
        'slug',
        'address',
        'phone',
        'latitude',
        'longitude',
        'opens_at',
        'closes_at',
        'delivery_fee',
        'minimum_order',
        'delivery_radius_km',
        'estimated_delivery_time',
        'is_active',
        'accepts_orders',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'delivery_fee' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'delivery_radius_km' => 'decimal:2',
        'opens_at' => 'datetime:H:i',
        'closes_at' => 'datetime:H:i',
        'is_active' => 'boolean',
        'accepts_orders' => 'boolean',
        'estimated_delivery_time' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = ['coordinates'];

    public function getCoordinatesAttribute(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(RestaurantDeliveryZone::class);
    }

    public function menuItemAvailabilities(): HasMany
    {
        return $this->hasMany(MenuItemAvailability::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAcceptingOrders($query)
    {
        return $query->where('accepts_orders', true);
    }

    public function isOpen(): bool
    {
        $now = now()->format('H:i');
        $opensAt = $this->opens_at?->format('H:i') ?? '00:00';
        $closesAt = $this->closes_at?->format('H:i') ?? '23:59';

        return $opensAt <= $now && $closesAt >= $now;
    }
}
