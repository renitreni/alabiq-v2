<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOptionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_option_id',
        'name',
        'price_adjustment',
        'is_default',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(MenuItemOption::class, 'menu_item_option_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
