<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAssignment extends Model
{
    use HasFactory;

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_ASSIGNED, self::STATUS_ACCEPTED];

    protected $fillable = [
        'order_id',
        'rider_id',
        'status',
        'distance_km',
        'delivery_fee',
        'rejected_reason',
        'offered_at',
        'accepted_at',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
