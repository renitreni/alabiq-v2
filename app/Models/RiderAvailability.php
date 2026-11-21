<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderAvailability extends Model
{
    use HasFactory;

    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_BUSY = 'busy';

    public const STATUSES = [self::STATUS_ONLINE, self::STATUS_OFFLINE, self::STATUS_BUSY];

    protected $fillable = [
        'rider_id',
        'status',
        'note',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
