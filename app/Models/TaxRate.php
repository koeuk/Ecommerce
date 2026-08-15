<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'rate', 'is_inclusive', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_inclusive' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return static::active()->where('is_default', true)->first();
    }

    /** Tax due on an amount. Inclusive rates extract rather than add. */
    public function taxFor(float $amount): float
    {
        $rate = (float) $this->rate / 100;

        return round(
            $this->is_inclusive ? $amount - ($amount / (1 + $rate)) : $amount * $rate,
            2
        );
    }
}
