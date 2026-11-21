<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory;

    // Account constants
    public const ACCOUNT_PLATFORM_COMMISSION = 'platform_commission';

    public const ACCOUNT_RESTAURANT_REVENUE = 'restaurant_revenue';

    public const ACCOUNT_DELIVERY_FEE = 'delivery_fee';

    public const ACCOUNT_REFUND = 'refund';

    // Type constants
    public const TYPE_DEBIT = 'debit';

    public const TYPE_CREDIT = 'credit';

    protected $fillable = [
        'order_id',
        'payment_transaction_id',
        'account',
        'type',
        'amount',
        'balance_after',
        'reference',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    protected $appends = ['signed_amount'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function getSignedAmountAttribute(): float
    {
        $amount = (float) $this->amount;

        return $this->type === self::TYPE_DEBIT ? -$amount : $amount;
    }

    public function isDebit(): bool
    {
        return $this->type === self::TYPE_DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }
}
