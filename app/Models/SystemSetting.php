<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'is_public',
    ];

    protected $casts = [
        'value' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Scalar stored in the JSON column, coerced per the setting type.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'numeric' => (float) $this->value,
            'boolean' => (bool) $this->value,
            default => $this->value,
        };
    }
}
