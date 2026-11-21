<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_by_type',
        'note',
    ];

    protected $casts = [
        'changed_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getChangerAttribute(): ?User
    {
        if (! $this->changed_by) {
            return null;
        }

        return User::find($this->changed_by);
    }
}
