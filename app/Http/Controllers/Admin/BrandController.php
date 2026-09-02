<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $brands = QueryBuilder::for(Brand::class)
            ->withCount('products')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($query, $value) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$value}%")
                        ->orWhere('slug', 'like', "%{$value}%")
                )),
                AllowedFilter::callback('status', fn ($query, $value) => $query->where(
                    'is_active',
                    $value === 'active'
                )),
            ])
            ->allowedSorts(...['sort_order', 'slug', 'created_at', 'products_count'])
            ->defaultSort('sort_order')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->getTranslations('name'),
                'slug' => $brand->slug,
                'logo_url' => $brand->logo ? Storage::url($brand->logo) : null,
                'is_active' => $brand->is_active,
                'sort_order' => $brand->sort_order,
                'products_count' => $brand->products_count,
            ]);

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
            'filters' => $request->input('filter', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Brands/Form', [
            'brand' => null,
        ]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $data = $this->payload($request);

        Brand::create($data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand created.');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('Admin/Brands/Form', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->getTranslations('name'),
                'description' => $brand->getTranslations('description'),
                'slug' => $brand->slug,
                'logo_url' => $brand->logo ? Storage::url($brand->logo) : null,
                'is_active' => $brand->is_active,
                'sort_order' => $brand->sort_order,
            ],
        ]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $this->payload($request, $brand);

        $brand->update($data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        /*
         * Guard and delete share one transaction under a row lock. Otherwise a
         * product could be assigned to this brand between the check passing
         * and the delete landing, orphaning that product.
         */
        $error = DB::transaction(function () use ($brand) {
            $locked = Brand::lockForUpdate()->find($brand->id);

            if (! $locked) {
                return 'That brand no longer exists.';
            }

            if ($locked->products()->exists()) {
                return 'This brand still has products. Reassign them before deleting.';
            }

            $locked->delete();

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand deleted.');
    }

    /** Normalises the validated input and handles the logo upload. */
    private function payload(BrandRequest $request, ?Brand $brand = null): array
    {
        $data = $request->safe()->except('logo');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $request->integer('sort_order');

        if ($request->hasFile('logo')) {
            if ($brand?->logo) {
                Storage::disk('public')->delete($brand->logo);
            }

            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        // Let the model generate the slug when the field is left blank.
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        return $data;
    }
}
