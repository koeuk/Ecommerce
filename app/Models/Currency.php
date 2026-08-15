<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate',
        'decimal_places', 'is_base', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'decimal_places' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function base(): ?self
    {
        return static::where('is_base', true)->first();
    }

    /** Converts a USD-base amount into this currency. */
    public function convert(float $amountInBase): float
    {
        return round($amountInBase * (float) $this->exchange_rate, $this->decimal_places);
    }

    public function format(float $amountInBase): string
    {
        return $this->symbol.number_format($this->convert($amountInBase), $this->decimal_places);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
