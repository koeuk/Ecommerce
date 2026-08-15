<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\GeneratesSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use GeneratesSlug, HasFactory, HasTranslations, SoftDeletes;

    public array $translatable = [
        'title', 'short_description', 'description', 'meta_title', 'meta_description',
    ];

    protected string $slugSource = 'title';

    protected $fillable = [
        'title', 'slug', 'sku', 'short_description', 'description',
        'brand_id', 'category_id',
        'price', 'compare_at_price', 'cost_price',
        'status', 'condition', 'is_featured',
        'warranty_months', 'release_year',
        'weight', 'length', 'width', 'height',
        'stock_quantity', 'views_count', 'rating_avg', 'rating_count',
        'meta_title', 'meta_description',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'warranty_months' => 'integer',
            'release_year' => 'integer',
            'stock_quantity' => 'integer',
            'views_count' => 'integer',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    // Relationships

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    // Accessors

    protected function finalPrice(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->relationLoaded('defaultVariant') && $this->defaultVariant
                ? (float) $this->defaultVariant->price
                : (float) $this->price,
        );
    }

    protected function discountPercent(): CastAttribute
    {
        return CastAttribute::make(get: function () {
            $compare = (float) $this->compare_at_price;

            if ($compare <= 0 || $compare <= (float) $this->price) {
                return 0;
            }

            return (int) round((($compare - (float) $this->price) / $compare) * 100);
        });
    }

    protected function isOnSale(): CastAttribute
    {
        return CastAttribute::make(get: fn () => $this->discount_percent > 0);
    }

    protected function primaryImageUrl(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->primaryImage?->url ?? $this->images->first()?->url,
        );
    }

    protected function inStock(): CastAttribute
    {
        return CastAttribute::make(get: fn () => $this->stock_quantity > 0);
    }

    /** Recalculates the cached stock total from the variant rows. */
    public function syncStockFromVariants(): void
    {
        $this->forceFill([
            'stock_quantity' => $this->variants()->sum('stock_quantity'),
        ])->save();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
