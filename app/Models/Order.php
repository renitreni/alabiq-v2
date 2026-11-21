<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    // Order status constants
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PLACED = 'placed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_RIDER_ASSIGNED = 'rider_assigned';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_ON_THE_WAY = 'on_the_way';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    // Valid status transitions (from_status => [allowed to_statuses])
    public const VALID_TRANSITIONS = [
        self::STATUS_PENDING_PAYMENT => [self::STATUS_PLACED, self::STATUS_CANCELLED],
        self::STATUS_PLACED => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
        self::STATUS_PREPARING => [self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_READY => [self::STATUS_RIDER_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_RIDER_ASSIGNED => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP => [self::STATUS_ON_THE_WAY, self::STATUS_CANCELLED],
        self::STATUS_ON_THE_WAY => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => [],
    ];

    // Terminal statuses that cannot transition further
    public const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
    ];

    protected $fillable = [
        'user_id',
        'restaurant_id',
        'branch_id',
        'customer_address_id',
        'status',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'snapshot_data',
        'delivery_instructions',
        'estimated_delivery_time',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $attributes = [
        'discount_amount' => 0,
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'snapshot_data' => 'array',
        'estimated_delivery_time' => 'integer',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['is_terminal', 'status_label'];

    public function getIsTerminalAttribute(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_PAYMENT => 'Pending Payment',
            self::STATUS_PLACED => 'Order Placed',
            self::STATUS_ACCEPTED => 'Accepted by Restaurant',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_READY => 'Ready for Pickup',
            self::STATUS_RIDER_ASSIGNED => 'Rider Assigned',
            self::STATUS_PICKED_UP => 'Picked Up',
            self::STATUS_ON_THE_WAY => 'On the Way',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT);
    }

    public function scopePlaced(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLACED);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopePreparing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PREPARING);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopeRiderAssigned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RIDER_ASSIGNED);
    }

    public function scopePickedUp(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PICKED_UP);
    }

    public function scopeOnTheWay(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ON_THE_WAY);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];

        return in_array($toStatus, $allowed);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->attributes['total_amount'] ?? 0);
    }
}
