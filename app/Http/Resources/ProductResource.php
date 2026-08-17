<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,

            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price ? (float) $this->compare_at_price : null,
            'discount_percent' => $this->discount_percent,
            'is_on_sale' => $this->is_on_sale,

            'in_stock' => $this->in_stock,
            'stock_quantity' => $this->stock_quantity,

            'condition' => $this->condition,
            'warranty_months' => $this->warranty_months,
            'release_year' => $this->release_year,
            'is_featured' => $this->is_featured,

            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,

            'primary_image_url' => $this->primary_image_url,
            'primary_thumbnail_url' => $this->primary_thumbnail_url,

            // SEO inputs — the frontend renders the tags, this supplies them.
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            // Only present when explicitly ?include='d
            'description' => $this->when($request->routeIs('*.show'), $this->description),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'specifications' => ProductSpecificationResource::collection($this->whenLoaded('specifications')),
        ];
    }
}
