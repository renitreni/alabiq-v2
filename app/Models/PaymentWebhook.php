<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'gateway_event_id',
        'signature',
        'signature_valid',
        'status',
        'processed',
        'payload',
        'error',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed' => 'boolean',
        'payload' => 'array',
    ];
}
