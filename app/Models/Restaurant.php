<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'logo',
        'cover_image',
        'phone',
        'email',
        'website',
        'latitude',
        'longitude',
        'address',
        'city',
        'district',
        'postal_code',
        'commission_rate',
        'delivery_fee',
        'minimum_order',
        'estimated_delivery_time',
        'is_active',
        'is_approved',
        'accepts_orders',
        'metadata',
        'approved_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'commission_rate' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'estimated_delivery_time' => 'integer',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'accepts_orders' => 'boolean',
        'metadata' => 'array',
        'approved_at' => 'datetime',
    ];

    protected $appends = ['coordinates'];

    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
            ];
        }

        return null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(RestaurantBranch::class);
    }

    public function activeBranches(): HasMany
    {
        return $this->hasMany(RestaurantBranch::class)->where('is_active', true)->where('accepts_orders', true);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class, 'restaurant_cuisine');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeAcceptingOrders($query)
    {
        return $query->where('accepts_orders', true);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function isOpen(): bool
    {
        $branch = $this->activeBranches()->first();

        return $branch?->isOpen() ?? false;
    }

    public function getPrimaryBranch(): ?RestaurantBranch
    {
        return $this->activeBranches()->first();
    }
}
