<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private readonly ImageService $images) {}

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($this->productAttributes($data));

            $this->syncVariants($product, $data['variants'] ?? []);
            $this->syncSpecifications($product, $data['specifications'] ?? []);
            $this->syncTags($product, $data['tags'] ?? []);

            $product->syncStockFromVariants();

            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($this->productAttributes($data, $product));

            $this->syncVariants($product, $data['variants'] ?? []);
            $this->syncSpecifications($product, $data['specifications'] ?? []);
            $this->syncTags($product, $data['tags'] ?? []);

            $product->syncStockFromVariants();

            return $product->refresh();
        });
    }

    private function productAttributes(array $data, ?Product $product = null): array
    {
        $attributes = collect($data)->only([
            'title', 'slug', 'sku', 'short_description', 'description',
            'brand_id', 'category_id',
            'price', 'compare_at_price', 'cost_price',
            'status', 'condition', 'is_featured',
            'warranty_months', 'release_year',
            'weight', 'length', 'width', 'height',
            'meta_title', 'meta_description',
        ])->all();

        // Let the model generate the slug when the field is left blank.
        if (blank($attributes['slug'] ?? null)) {
            unset($attributes['slug']);
        }

        $attributes[$product ? 'updated_by' : 'created_by'] = auth()->id();

        return $attributes;
    }

    /**
     * Reconciles the submitted variant rows against what is stored.
     *
     * Variants absent from the payload are deleted; the first row is forced
     * to be the default so a product always has one.
     */
    private function syncVariants(Product $product, array $variants): void
    {
        if ($variants === []) {
            // A product must remain sellable, so fall back to a single variant.
            if ($product->variants()->count() === 0) {
                $this->createDefaultVariant($product);
            }

            return;
        }

        $keptIds = [];

        foreach (array_values($variants) as $index => $row) {
            $attributes = [
                'product_id' => $product->id,
                'sku' => $row['sku'],
                'label' => $row['label'] ?? null,
                'price' => $row['price'],
                'compare_at_price' => $row['compare_at_price'] ?? null,
                'cost_price' => $row['cost_price'] ?? null,
                'stock_quantity' => $row['stock_quantity'] ?? 0,
                'low_stock_threshold' => $row['low_stock_threshold'] ?? 5,
                'allow_backorder' => (bool) ($row['allow_backorder'] ?? false),
                'weight' => $row['weight'] ?? $product->weight,
                'is_default' => $index === 0,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $variant = isset($row['id'])
                ? tap(ProductVariant::findOrFail($row['id']))->update($attributes)
                : ProductVariant::create($attributes);

            $keptIds[] = $variant->id;

            $pivot = [];

            foreach ($row['attribute_value_ids'] ?? [] as $attributeId => $valueId) {
                if ($valueId) {
                    $pivot[$valueId] = ['attribute_id' => $attributeId];
                }
            }

            $variant->attributeValues()->sync($pivot);
        }

        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }

    private function createDefaultVariant(Product $product): void
    {
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-DEFAULT',
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'cost_price' => $product->cost_price,
            'stock_quantity' => 0,
            'weight' => $product->weight,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function syncSpecifications(Product $product, array $specifications): void
    {
        $product->specifications()->delete();

        foreach (array_values($specifications) as $index => $spec) {
            if (blank($spec['key']['en'] ?? null)) {
                continue;
            }

            ProductSpecification::create([
                'product_id' => $product->id,
                'group' => $spec['group'] ?? null,
                'key' => $spec['key'],
                'value' => $spec['value'] ?? [],
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function syncTags(Product $product, array $tagIds): void
    {
        $product->tags()->sync($tagIds);
    }

    /**
     * @param  array<UploadedFile>  $files
     */
    public function addImages(Product $product, array $files): void
    {
        $nextOrder = (int) $product->images()->max('sort_order');
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach ($files as $file) {
            // Converted to WebP and thumbnailed rather than stored as-is.
            $stored = $this->images->store($file, "products/{$product->id}");

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $stored['path'],
                'thumbnail_path' => $stored['thumbnail_path'],
                'alt_text' => $product->getTranslation('title', 'en'),
                'is_primary' => ! $hasPrimary,
                'sort_order' => ++$nextOrder,
            ]);

            $hasPrimary = true;
        }
    }

    public function deleteImage(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $wasPrimary = $image->is_primary;
            $productId = $image->product_id;

            $this->images->delete($image->path, $image->thumbnail_path);

            $image->delete();

            // Promote another image so the product keeps a primary.
            if ($wasPrimary) {
                ProductImage::where('product_id', $productId)
                    ->orderBy('sort_order')
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });
    }

    /** @param  array<int>  $orderedIds */
    public function reorderImages(Product $product, array $orderedIds): void
    {
        DB::transaction(function () use ($product, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                $product->images()->where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function setPrimaryImage(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image) {
            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });
    }

    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            // search_text is a generated column — MySQL rejects any attempt
            // to write it, so it must not be carried into the copy.
            $copy = $product->replicate([
                'slug', 'sku', 'views_count', 'rating_avg', 'rating_count', 'search_text',
            ]);

            $copy->title = collect($product->getTranslations('title'))
                ->map(fn ($value) => $value.' (copy)')
                ->all();
            $copy->slug = null;
            $copy->sku = $product->sku.'-COPY-'.strtoupper(substr(uniqid(), -4));
            $copy->status = ProductStatus::Draft;
            $copy->views_count = 0;
            $copy->rating_avg = 0;
            $copy->rating_count = 0;
            $copy->created_by = auth()->id();
            $copy->save();

            foreach ($product->variants as $index => $variant) {
                $newVariant = $variant->replicate(['sku']);
                $newVariant->product_id = $copy->id;
                $newVariant->sku = $copy->sku.'-V'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $newVariant->save();

                $pivot = [];

                foreach ($variant->attributeValues as $value) {
                    $pivot[$value->id] = ['attribute_id' => $value->pivot->attribute_id];
                }

                $newVariant->attributeValues()->sync($pivot);
            }

            foreach ($product->specifications as $spec) {
                $newSpec = $spec->replicate();
                $newSpec->product_id = $copy->id;
                $newSpec->save();
            }

            foreach ($product->images as $image) {
                $newImage = $image->replicate();
                $newImage->product_id = $copy->id;
                $newImage->product_variant_id = null;
                $newImage->save();
            }

            $copy->tags()->sync($product->tags->pluck('id'));
            $copy->syncStockFromVariants();

            return $copy;
        });
    }
}
