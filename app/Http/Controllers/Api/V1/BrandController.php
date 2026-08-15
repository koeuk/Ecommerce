<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BrandController extends Controller
{
    /** GET /api/v1/brands */
    public function index(): AnonymousResourceCollection
    {
        $brands = QueryBuilder::for(Brand::active())
            ->withCount('products')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('name', 'like', "%{$v}%")),
            ])
            ->allowedSorts(...['sort_order', 'slug', 'products_count'])
            ->defaultSort('sort_order')
            ->get();

        return BrandResource::collection($brands);
    }

    /** GET /api/v1/brands/{slug} */
    public function show(string $slug): BrandResource
    {
        $brand = Brand::active()->withCount('products')->where('slug', $slug)->firstOrFail();

        return new BrandResource($brand);
    }
}
