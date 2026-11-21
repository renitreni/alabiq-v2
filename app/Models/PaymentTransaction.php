<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    // Gateway constants
    public const GATEWAY_CASH = 'cash_on_delivery';

    public const GATEWAY_TAP = 'tap_payments';

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'gateway',
        'gateway_transaction_id',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'refunded_amount',
        'response',
        'failure_reason',
    ];

    protected $attributes = [
        'currency' => 'SAR',
        'status' => self::STATUS_PENDING,
        'refunded_amount' => 0,
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'response' => 'array',
    ];

    protected $appends = ['is_successful', 'is_refundable'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function getIsRefundableAttribute(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED
            && (float) $this->refunded_amount < (float) $this->amount;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function isRefundable(): bool
    {
        return $this->isSuccessful() && (float) $this->refunded_amount < (float) $this->amount;
    }

    public function getRemainingRefundableAmount(): float
    {
        return round((float) $this->amount - (float) $this->refunded_amount, 2);
    }
}
