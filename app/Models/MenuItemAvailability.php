<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemAvailability extends Model
{
    protected $fillable = [
        'menu_item_id',
        'restaurant_branch_id',
        'is_available',
        'price_override',
        'available_from',
        'available_until',
        'days_of_week',
        'available_from_date',
        'available_until_date',
        'stock_quantity',
        'low_stock_threshold',
        'track_stock',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price_override' => 'decimal:2',
        'available_from' => 'datetime:H:i',
        'available_until' => 'datetime:H:i',
        'days_of_week' => 'array',
        'available_from_date' => 'date',
        'available_until_date' => 'date',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_stock' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'restaurant_branch_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('restaurant_branch_id', $branchId);
    }

    public function isCurrentlyAvailable(): bool
    {
        if (! $this->is_available) {
            return false;
        }

        $now = now();
        $today = $now->dayOfWeek; // 1=Mon, 7=Sun (Carbon uses 0=Sun, 6=Sat)

        // Check date range
        if ($this->available_from_date && $now->lt($this->available_from_date)) {
            return false;
        }
        if ($this->available_until_date && $now->gt($this->available_until_date)) {
            return false;
        }

        // Check days of week
        if ($this->days_of_week && ! in_array($today, $this->days_of_week)) {
            return false;
        }

        // Check time range
        if ($this->available_from && $this->available_until) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $this->available_from || $currentTime > $this->available_until) {
                return false;
            }
        }

        // Check stock
        if ($this->track_stock && $this->stock_quantity !== null && $this->stock_quantity <= 0) {
            return false;
        }

        return true;
    }

    public function isLowStock(): bool
    {
        if (! $this->track_stock || $this->stock_quantity === null) {
            return false;
        }

        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function decrementStock(int $quantity = 1): void
    {
        if ($this->track_stock && $this->stock_quantity !== null) {
            $this->decrement('stock_quantity', $quantity);
        }
    }
}
