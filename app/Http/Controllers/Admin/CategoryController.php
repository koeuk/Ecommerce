<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'tree' => $this->buildTree($categories),
            'total' => $categories->count(),
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
        if ($category->children()->exists()) {
            return back()->with(
                'error',
                'This category has sub-categories. Delete or move them first.'
            );
        }

        if ($category->products()->exists()) {
            return back()->with(
                'error',
                'This category still has products. Reassign them before deleting.'
            );
        }

        $category->delete();

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
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->getTranslations('name'),
                'slug' => $c->slug,
                'is_active' => $c->is_active,
                'is_featured' => $c->is_featured,
                'sort_order' => $c->sort_order,
                'products_count' => $c->products_count,
                'children' => $this->buildTree($categories, $c->id),
            ])
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
