<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\FlushesStorefrontCache;
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
    use FlushesStorefrontCache, GeneratesSlug, HasFactory, HasTranslations, SoftDeletes;

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

    /**
     * FULLTEXT over the generated `search_text` column (title in every locale
     * plus the SKU). Falls back to LIKE for terms MySQL will not tokenise —
     * anything under its minimum token length — and on drivers without the
     * index, which keeps the test suite portable.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        if ($query->getConnection()->getDriverName() !== 'mysql') {
            return $this->likeSearch($query, $term);
        }

        // Boolean-mode operators would otherwise change what was asked for:
        // the hyphen in "LAP-001" reads as "exclude 001".
        $tokens = collect(preg_split('/\s+/', (string) preg_replace('/[+\-><()~*"@]+/', ' ', $term)))
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->values();

        if ($tokens->isEmpty()) {
            return $this->likeSearch($query, $term);
        }

        // Each token required, with a trailing wildcard so prefixes match.
        $boolean = $tokens->map(fn (string $token) => '+'.$token.'*')->implode(' ');

        return $query->whereFullText('search_text', $boolean, ['mode' => 'boolean']);
    }

    /**
     * A JSON column compares under a binary collation, so a plain
     * `title LIKE '%as%'` would never match "Asus". Casting to CHAR brings
     * the column back under the case-insensitive collation.
     */
    private function likeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->whereRaw('CAST(title AS CHAR) LIKE ?', ["%{$term}%"])
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

    protected function primaryThumbnailUrl(): CastAttribute
    {
        return CastAttribute::make(get: fn () => $this->primaryImage?->thumbnail_url);
    }

    /**
     * The fallback only reads `images` when it is already eager-loaded.
     * Touching the relation unconditionally lazy-loads it once per row, which
     * turned a 24-product listing into 24 extra queries.
     */
    protected function primaryImageUrl(): CastAttribute
    {
        return CastAttribute::make(
            get: fn () => $this->primaryImage?->url
                ?? ($this->relationLoaded('images') ? $this->images->first()?->url : null),
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
