<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'provinces', 'is_default', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'provinces' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Resolves the zone covering a province, falling back to the default. */
    public static function forProvince(?string $province): ?self
    {
        $zones = static::active()->orderBy('sort_order')->get();

        return $zones->first(fn (self $zone) => collect($zone->provinces ?? [])
            ->contains(fn ($p) => strcasecmp((string) $p, (string) $province) === 0))
            ?? $zones->firstWhere('is_default', true);
    }
}
