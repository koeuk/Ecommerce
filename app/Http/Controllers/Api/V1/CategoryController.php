<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    /** GET /api/v1/categories — full tree by default, flat with ?filter[flat]=1 */
    public function index(): AnonymousResourceCollection
    {
        $query = QueryBuilder::for(Category::active())
            ->withCount('products')
            ->allowedFilters(...[
                AllowedFilter::exact('parent_id'),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::callback('flat', fn ($q, $v) => $q),
            ])
            ->allowedIncludes(...['children', 'parent'])
            ->defaultSort('sort_order');

        // Without an explicit flat flag, return roots with their subtree.
        if (! request()->boolean('filter.flat') && ! request()->has('filter.parent_id')) {
            $query->roots()->with('descendants');
        }

        return CategoryResource::collection($query->get());
    }

    /** GET /api/v1/categories/{slug} */
    public function show(string $slug): CategoryResource
    {
        $category = Category::active()
            ->withCount('products')
            ->with(['children', 'parent'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new CategoryResource($category);
    }
}
