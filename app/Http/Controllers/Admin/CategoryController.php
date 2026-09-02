<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        /*
         * Not paginated, unlike the other admin lists: the tree has to be
         * assembled whole or a parent could arrive on a page without its
         * children. The taxonomy is 2-3 levels deep by design, so the whole
         * set is a cheap read.
         */
        $categories = QueryBuilder::for(Category::class)
            ->withCount('products')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($query, $value) => $query->where(
                    fn ($q) => $q->whereRaw('CAST(name AS CHAR) LIKE ?', ["%{$value}%"])
                        ->orWhere('slug', 'like', "%{$value}%")
                )),
                AllowedFilter::callback('status', fn ($query, $value) => $query->where(
                    'is_active',
                    $value === 'active'
                )),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::exact('parent_id'),
            ])
            ->allowedSorts(...['sort_order', 'slug', 'created_at', 'products_count'])
            ->defaultSort('sort_order')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'tree' => $this->buildTree($categories),
            'total' => $categories->count(),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => null,
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($this->payload($request));

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->getTranslations('name'),
                'description' => $category->getTranslations('description'),
                'slug' => $category->slug,
                'image_url' => $category->image ? Storage::url($category->image) : null,
                'icon' => $category->icon,
                'is_active' => $category->is_active,
                'is_featured' => $category->is_featured,
                'sort_order' => $category->sort_order,
            ],
            // Exclude self and descendants — moving under them would orphan the subtree.
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->payload($request, $category));

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        /*
         * The guards and the delete run in one transaction under a row lock.
         * Without it, a product could be assigned to this category between
         * the check passing and the delete landing, orphaning that product.
         */
        $error = DB::transaction(function () use ($category) {
            $locked = Category::lockForUpdate()->find($category->id);

            if (! $locked) {
                return 'That category no longer exists.';
            }

            if ($locked->children()->exists()) {
                return 'This category has sub-categories. Delete or move them first.';
            }

            if ($locked->products()->exists()) {
                return 'This category still has products. Reassign them before deleting.';
            }

            $locked->delete();

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted.');
    }

    /** Flat list with indent depth, for the parent <select>. */
    private function parentOptions(?Category $exclude = null): array
    {
        $excluded = $exclude
            ? [$exclude->id, ...$this->descendantIds($exclude)]
            : [];

        return Category::orderBy('sort_order')->get()
            ->whereNotIn('id', $excluded)
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->getTranslation('name', 'en'),
                'depth' => $this->depthOf($c),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function descendantIds(Category $category): array
    {
        $ids = [];

        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $ids = [...$ids, ...$this->descendantIds($child)];
        }

        return $ids;
    }

    private function depthOf(Category $category): int
    {
        $depth = 0;
        $current = $category;

        while ($current->parent_id) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    /** Nests the flat collection into a tree in a single pass. */
    private function buildTree($categories, ?int $parentId = null): array
    {
        return $categories
            ->where('parent_id', $parentId)
            ->map(function (Category $c) use ($categories) {
                $children = $this->buildTree($categories, $c->id);

                return [
                    'id' => $c->id,
                    'name' => $c->getTranslations('name'),
                    'slug' => $c->slug,
                    'is_active' => $c->is_active,
                    'is_featured' => $c->is_featured,
                    'sort_order' => $c->sort_order,
                    // Own products plus everything under this category's subtree.
                    'products_count' => $c->products_count
                        + collect($children)->sum('products_count'),
                    'children' => $children,
                ];
            })
            ->values()
            ->all();
    }

    private function payload(CategoryRequest $request, ?Category $category = null): array
    {
        $data = $request->safe()->except('image');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = $request->integer('sort_order');

        if ($request->hasFile('image')) {
            if ($category?->image) {
                Storage::disk('public')->delete($category->image);
            }

            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        return $data;
    }
}
