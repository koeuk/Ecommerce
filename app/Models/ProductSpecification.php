<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * The spec sheet — descriptive only, never generates a SKU.
 */
class ProductSpecification extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['group', 'key', 'value'];

    protected $fillable = ['product_id', 'group', 'key', 'value', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
