<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'street',
        'building',
        'floor',
        'apartment',
        'landmark',
        'city',
        'district',
        'postal_code',
        'latitude',
        'longitude',
        'place_id',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = ['coordinates', 'formatted_address'];

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

    public function getFormattedAddressAttribute(): string
    {
        $parts = [
            $this->street,
            $this->building,
            $this->floor,
            $this->apartment,
            $this->landmark,
            $this->district,
            $this->city,
            $this->postal_code,
        ];

        return implode(', ', array_filter($parts));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    protected static function booted(): void
    {
        static::saving(function (self $address) {
            if ($address->is_default) {
                // Unset other default addresses for this user
                static::where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
