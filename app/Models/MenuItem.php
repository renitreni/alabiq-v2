<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'menu_category_id',
        'name',
        'slug',
        'description',
        'image',
        'price',
        'compare_at_price',
        'calories',
        'preparation_time',
        'is_vegetarian',
        'is_vegan',
        'is_gluten_free',
        'is_spicy',
        'is_popular',
        'is_available',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'calories' => 'integer',
        'is_vegetarian' => 'boolean',
        'is_vegan' => 'boolean',
        'is_gluten_free' => 'boolean',
        'is_spicy' => 'boolean',
        'is_popular' => 'boolean',
        'is_available' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = ['discount_percentage', 'has_discount'];

    public function getDiscountPercentageAttribute(): ?int
    {
        if ($this->compare_at_price && $this->compare_at_price > $this->price) {
            return round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
        }

        return null;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_percentage !== null;
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuItemOption::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(MenuItemAvailability::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true)->where('is_available', true);
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('menu_category_id', $categoryId);
    }

    public function isAvailableAtBranch(int $branchId): bool
    {
        $availability = $this->availabilities()
            ->where('restaurant_branch_id', $branchId)
            ->first();

        return $availability?->isCurrentlyAvailable() ?? $this->is_available;
    }

    public function getPriceAtBranch(int $branchId): float
    {
        $availability = $this->availabilities()
            ->where('restaurant_branch_id', $branchId)
            ->first();

        if ($availability && $availability->price_override !== null) {
            return (float) $availability->price_override;
        }

        return (float) $this->price;
    }
}
