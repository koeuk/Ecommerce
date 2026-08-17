<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'type', 'value', 'min_order_total', 'max_discount', 'applies_to',
        'usage_limit', 'per_user_limit', 'used_count',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_total' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CouponTarget::class);
    }

    public function scopeUsable(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    protected function isExpired(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->expires_at !== null && $this->expires_at->isPast(),
        );
    }

    protected function isExhausted(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->usage_limit !== null && $this->used_count >= $this->usage_limit,
        );
    }

    /** A coupon dated to start later must not be redeemable yet. */
    protected function hasNotStarted(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->starts_at !== null && $this->starts_at->isFuture(),
        );
    }

    /** How many times this customer has already redeemed it. */
    public function redemptionsBy(?int $userId): int
    {
        if ($userId === null) {
            return 0;   // guests are not tracked across orders
        }

        return $this->usages()->where('user_id', $userId)->count();
    }

    public function exceededLimitFor(?int $userId): bool
    {
        return $this->per_user_limit !== null
            && $this->redemptionsBy($userId) >= $this->per_user_limit;
    }

    /**
     * The single authority on whether this coupon may be redeemed right now.
     *
     * Returns null when it may, or a customer-facing reason when it may not.
     * Both the quote and the checkout transaction call this, so the two can
     * never drift apart.
     */
    public function redemptionError(?int $userId = null): ?string
    {
        return match (true) {
            ! $this->is_active => __('That coupon is no longer available.'),
            $this->has_not_started => __('That coupon is not active yet.'),
            $this->is_expired => __('That coupon has expired.'),
            $this->is_exhausted => __('That coupon has been fully redeemed.'),
            $this->exceededLimitFor($userId) => __('You have already used that coupon.'),
            default => null,
        };
    }

    /** Discount for a given subtotal, respecting the max_discount cap. */
    public function discountFor(float $subtotal): float
    {
        if ($this->min_order_total !== null && $subtotal < (float) $this->min_order_total) {
            return 0.0;
        }

        $discount = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
