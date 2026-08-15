<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'label' => $this->label,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price ? (float) $this->compare_at_price : null,
            'stock_quantity' => $this->stock_quantity,
            'is_in_stock' => $this->is_in_stock,
            'is_low_stock' => $this->is_low_stock,
            'is_default' => $this->is_default,
            'options' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues
                ->map(fn ($value) => [
                    'attribute_id' => $value->pivot->attribute_id,
                    'value_id' => $value->id,
                    'label' => $value->label,
                    'colour_hex' => $value->colour_hex,
                ])),
        ];
    }
}
