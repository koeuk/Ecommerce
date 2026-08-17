<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'user_id', 'session_id',
        'customer_name', 'customer_email', 'customer_phone',
        'subtotal', 'discount_total', 'tax_total', 'shipping_fee', 'grand_total',
        'currency', 'exchange_rate',
        'status', 'payment_status', 'fulfillment_status',
        'coupon_id', 'shipping_method_id',
        'shipping_address', 'billing_address',
        'customer_note', 'admin_note',
        'placed_at', 'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'stock_released_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_released_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::Paid);
    }

    public function scopeStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    protected function isGuestOrder(): CastAttribute
    {
        return CastAttribute::make(get: fn () => $this->user_id === null);
    }

    protected function itemCount(): CastAttribute
    {
        return CastAttribute::make(get: fn () => (int) $this->items->sum('quantity'));
    }

    /** Grand total converted into the order's currency at the snapshot rate. */
    protected function grandTotalInCurrency(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => round((float) $this->grand_total * (float) $this->exchange_rate, 2),
        );
    }

    public function canTransitionTo(OrderStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }
}
