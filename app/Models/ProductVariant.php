<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The real sellable unit — owns price and stock. Cart and order lines always
 * reference a variant, so every product has at least a default one.
 */
class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'label',
        'price', 'compare_at_price', 'cost_price',
        'stock_quantity', 'low_stock_threshold', 'allow_backorder',
        'weight', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'allow_backorder' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attribute_values'
        )->withPivot('attribute_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('stock_quantity', '>', 0)->orWhere('allow_backorder', true);
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    protected function isLowStock(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->stock_quantity <= $this->low_stock_threshold,
        );
    }

    protected function isInStock(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->stock_quantity > 0 || $this->allow_backorder,
        );
    }

    public function canFulfil(int $quantity): bool
    {
        return $this->allow_backorder || $this->stock_quantity >= $quantity;
    }
}
