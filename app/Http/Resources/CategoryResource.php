<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->image ? Storage::url($this->image) : null,
            'icon' => $this->icon,
            'is_featured' => $this->is_featured,
            'products_count' => $this->whenCounted('products'),
            // `descendants` is the recursive form of `children`, so whichever
            // the caller eager-loaded is emitted under the same key.
            'children' => $this->relationLoaded('descendants')
                ? self::collection($this->descendants)
                : self::collection($this->whenLoaded('children')),
            'parent' => new self($this->whenLoaded('parent')),
        ];
    }
}
