<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'name',
        'type',
        'is_required',
        'min_selections',
        'max_selections',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(MenuItemOptionValue::class, 'menu_item_option_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeForMenuItem($query, int $menuItemId)
    {
        return $query->where('menu_item_id', $menuItemId);
    }
}
