<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     *
     * filter[search]=macbook  filter[brand]=apple  filter[category]=laptops
     * filter[price_min]=500   filter[price_max]=2000  filter[in_stock]=1
     * sort=-created_at        include=brand,category,images
     */
    public function index(): AnonymousResourceCollection
    {
        $products = QueryBuilder::for(Product::published())
            ->with(['brand', 'primaryImage'])
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($q, $v) => $q->search($v)),
                AllowedFilter::callback('brand', fn ($q, $v) => $q->whereHas(
                    'brand',
                    fn ($b) => $b->whereIn('slug', (array) $v)
                )),
                AllowedFilter::callback('category', fn ($q, $v) => $q->whereHas(
                    'category',
                    fn ($c) => $c->whereIn('slug', (array) $v)
                )),
                AllowedFilter::callback('price_min', fn ($q, $v) => $q->where('price', '>=', (float) $v)),
                AllowedFilter::callback('price_max', fn ($q, $v) => $q->where('price', '<=', (float) $v)),
                AllowedFilter::callback('in_stock', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOL)
                    ? $q->inStock()
                    : $q),
                AllowedFilter::callback('rating_min', fn ($q, $v) => $q->where('rating_avg', '>=', (float) $v)),
                AllowedFilter::exact('condition'),
                AllowedFilter::exact('release_year'),
                AllowedFilter::exact('is_featured'),
            ])
            ->allowedSorts(...[
                'price',
                'created_at',
                'rating_avg',
                'views_count',
                AllowedSort::field('name', 'slug'),
            ])
            ->allowedIncludes(...[
                AllowedInclude::relationship('brand'),
                AllowedInclude::relationship('category'),
                AllowedInclude::relationship('images'),
                AllowedInclude::relationship('variants'),
                AllowedInclude::relationship('specifications'),
            ])
            ->defaultSort('-created_at')
            ->paginate(min(request()->integer('per_page', 24), 100))
            ->appends(request()->query());

        return ProductResource::collection($products);
    }

    /**
     * GET /api/v1/products/{slug}
     */
    public function show(string $slug): ProductResource
    {
        $product = QueryBuilder::for(Product::published())
            ->with([
                'brand',
                'category',
                'images',
                'specifications',
                'variants' => fn ($q) => $q->active()->with('attributeValues'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $product->increment('views_count');

        return new ProductResource($product);
    }
}
