<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Payments\GatewayRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

/**
 * The endpoints a storefront needs on first paint, which don't belong to a
 * single resource: the home feed, the navigation tree, shop settings, and
 * the filter metadata a listing page needs to render its own controls.
 */
class StorefrontController extends Controller
{
    /**
     * GET /api/v1/home
     *
     * One call rather than three round-trips before anything renders.
     */
    public function home(): JsonResponse
    {
        $limit = min(request()->integer('limit', 8), 24);

        return response()->json([
            'data' => [
                'featured' => ProductResource::collection(
                    $this->feed(fn ($q) => $q->featured(), $limit)
                ),
                'new_arrivals' => ProductResource::collection(
                    $this->feed(fn ($q) => $q->latest('created_at'), $limit)
                ),
                'best_sellers' => ProductResource::collection(
                    $this->feed(fn ($q) => $q->orderByDesc('views_count'), $limit)
                ),
                'featured_categories' => CategoryResource::collection(
                    Category::active()->where('is_featured', true)
                        ->withCount('products')
                        ->orderBy('sort_order')
                        ->limit(8)
                        ->get()
                ),
            ],
        ]);
    }

    /** GET /api/v1/categories-tree — the nested navigation tree, cached. */
    public function categoryTree(): AnonymousResourceCollection
    {
        // The tree changes maybe twice a year; every visitor loads it.
        $tree = Cache::remember('storefront.category_tree', now()->addDay(), function () {
            return Category::active()
                ->roots()
                ->withCount('products')
                ->with('descendants')
                ->orderBy('sort_order')
                ->get();
        });

        return CategoryResource::collection($tree);
    }

    /**
     * GET /api/v1/settings
     *
     * Shop name, contact details, currency and the free-shipping threshold —
     * everything the frontend chrome needs.
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => Setting::all_cached() + [
                // Only gateways that are actually configured.
                'payment_methods' => app(GatewayRegistry::class)->options(),
            ],
        ]);
    }

    /**
     * GET /api/v1/filters
     *
     * The brands and price range actually present in the current selection,
     * so the frontend renders filters from data instead of hardcoding them.
     * Honours ?category=slug so a category page narrows its own controls.
     */
    public function filters(): JsonResponse
    {
        $categorySlug = request()->string('category')->toString();

        $scope = fn () => Product::published()->when(
            $categorySlug !== '',
            fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug))
        );

        $brandIds = $scope()->distinct()->pluck('brand_id')->filter();

        $range = $scope()->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();

        return response()->json([
            'data' => [
                'brands' => Brand::active()->whereIn('id', $brandIds)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (Brand $brand) => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                    ]),
                'price' => [
                    'min' => (float) ($range->min_price ?? 0),
                    'max' => (float) ($range->max_price ?? 0),
                ],
                'conditions' => ['new', 'refurbished', 'used'],
            ],
        ]);
    }

    /** @param  callable(Builder): mixed  $apply */
    private function feed(callable $apply, int $limit)
    {
        $query = Product::published()->with(['brand', 'primaryImage']);

        $apply($query);

        return $query->limit($limit)->get();
    }
}
