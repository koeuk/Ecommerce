<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * SEO lives in the storefront frontend, which owns the URLs and renders the
 * tags. This repo owns the *inputs*: which slugs exist and when each last
 * changed. The frontend turns that into its own sitemap.xml.
 */
class SitemapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('storefront.sitemap', now()->addHour(), fn () => [
            'products' => Product::published()
                ->select('slug', 'updated_at')
                ->orderBy('slug')
                ->get()
                ->map(fn (Product $p) => [
                    'slug' => $p->slug,
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ]),
            'categories' => Category::active()
                ->select('slug', 'updated_at')
                ->get()
                ->map(fn (Category $c) => [
                    'slug' => $c->slug,
                    'updated_at' => $c->updated_at?->toIso8601String(),
                ]),
            'brands' => Brand::active()
                ->select('slug', 'updated_at')
                ->get()
                ->map(fn (Brand $b) => [
                    'slug' => $b->slug,
                    'updated_at' => $b->updated_at?->toIso8601String(),
                ]),
        ]);

        return response()->json(['data' => $data]);
    }
}
