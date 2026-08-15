<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSpecificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
