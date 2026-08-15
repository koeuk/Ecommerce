<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'session_id', 'product_id', 'product_variant_id',
        'quantity', 'price_at_add',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_at_add' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /** Current variant price, which may differ from price_at_add. */
    protected function currentPrice(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => (float) ($this->variant?->price ?? $this->price_at_add),
        );
    }

    protected function subtotal(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => round($this->current_price * $this->quantity, 2),
        );
    }

    protected function priceChanged(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => abs($this->current_price - (float) $this->price_at_add) > 0.001,
        );
    }
}
