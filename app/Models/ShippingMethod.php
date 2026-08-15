<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ShippingMethod extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'shipping_zone_id', 'name', 'description', 'rate_type',
        'base_rate', 'per_kg_rate', 'free_above_total',
        'min_days', 'max_days', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_rate' => 'decimal:2',
            'per_kg_rate' => 'decimal:2',
            'free_above_total' => 'decimal:2',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Shipping cost for a cart, honouring the free-shipping threshold. */
    public function calculate(float $subtotal, float $weightKg = 0): float
    {
        if ($this->rate_type === 'free') {
            return 0.0;
        }

        if ($this->free_above_total !== null && $subtotal >= (float) $this->free_above_total) {
            return 0.0;
        }

        $cost = (float) $this->base_rate;

        if ($this->rate_type === 'weight' && $this->per_kg_rate !== null) {
            $cost += (float) $this->per_kg_rate * $weightKg;
        }

        return round($cost, 2);
    }
}
