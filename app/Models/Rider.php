<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rider extends Model
{
    use HasFactory;

    public const VEHICLE_BIKE = 'bike';
    public const VEHICLE_CAR = 'car';
    public const VEHICLE_SCOOTER = 'scooter';

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'plate_number',
        'is_online',
        'is_active',
        'total_deliveries',
        'rating',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'is_active' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(RiderLocation::class);
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(RiderLocation::class)->latestOfMany('recorded_at');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(RiderAvailability::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class)
            ->whereIn('status', [DeliveryAssignment::STATUS_ASSIGNED, DeliveryAssignment::STATUS_ACCEPTED])
            ->latestOfMany('offered_at');
    }

    public function scopeAvailableForDispatch($query)
    {
        return $query->where('is_online', true)->where('is_active', true)
            ->whereDoesntHave('assignments', function ($q) {
                $q->whereIn('status', [DeliveryAssignment::STATUS_ASSIGNED, DeliveryAssignment::STATUS_ACCEPTED]);
            });
    }
}
