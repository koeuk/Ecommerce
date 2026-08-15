<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tag;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): Response
    {
        $products = QueryBuilder::for(Product::class)
            ->with(['brand', 'category', 'primaryImage'])
            ->withCount('variants')
            ->allowedFilters(...[
                AllowedFilter::callback('search', fn ($q, $v) => $q->search($v)),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::callback('low_stock', fn ($q, $v) => filter_var($v, FILTER_VALIDATE_BOOL)
                    ? $q->whereHas('variants', fn ($b) => $b->lowStock())
                    : $q),
            ])
            ->allowedSorts(...['price', 'stock_quantity', 'created_at', 'sku'])
            ->defaultSort('-created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->getTranslation('title', 'en'),
                'sku' => $product->sku,
                'slug' => $product->slug,
                'brand' => $product->brand?->getTranslation('name', 'en'),
                'category' => $product->category?->getTranslation('name', 'en'),
                'price' => (float) $product->price,
                'stock_quantity' => $product->stock_quantity,
                'variants_count' => $product->variants_count,
                'status' => $product->status->value,
                'is_featured' => $product->is_featured,
                'image_url' => $product->primaryImage?->url,
            ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $request->input('filter', []),
            'options' => [
                'brands' => $this->brandOptions(),
                'categories' => $this->categoryOptions(),
                'statuses' => $this->statusOptions(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->validated());

        if ($request->hasFile('images')) {
            $this->products->addImages($product, $request->file('images'));
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        $product->load([
            'variants.attributeValues',
            'specifications',
            'images',
            'tags',
        ]);

        return Inertia::render('Admin/Products/Form', [
            'product' => $this->serialise($product),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->validated());

        if ($request->hasFile('images')) {
            $this->products->addImages($product, $request->file('images'));
        }

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete(); // soft delete — order history keeps its reference

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted.');
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $copy = $this->products->duplicate($product->load(['variants.attributeValues', 'specifications', 'images', 'tags']));

        return redirect()->route('admin.products.edit', $copy)
            ->with('success', 'Product duplicated as a draft.');
    }

    // Image sub-resources

    public function deleteImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $this->products->deleteImage($image);

        return back()->with('success', 'Image removed.');
    }

    public function reorderImages(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:product_images,id'],
        ]);

        $this->products->reorderImages($product, $request->input('ids'));

        return back();
    }

    public function setPrimaryImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $this->products->setPrimaryImage($product, $image);

        return back()->with('success', 'Primary image updated.');
    }

    // Helpers

    private function serialise(Product $product): array
    {
        return [
            'id' => $product->id,
            'title' => $product->getTranslations('title'),
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->getTranslations('short_description'),
            'description' => $product->getTranslations('description'),
            'meta_title' => $product->getTranslations('meta_title'),
            'meta_description' => $product->getTranslations('meta_description'),
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'cost_price' => $product->cost_price ? (float) $product->cost_price : null,
            'status' => $product->status->value,
            'condition' => $product->condition,
            'is_featured' => $product->is_featured,
            'warranty_months' => $product->warranty_months,
            'release_year' => $product->release_year,
            'weight' => $product->weight ? (float) $product->weight : null,
            'length' => $product->length ? (float) $product->length : null,
            'width' => $product->width ? (float) $product->width : null,
            'height' => $product->height ? (float) $product->height : null,
            'tags' => $product->tags->pluck('id'),
            'stock_quantity' => $product->stock_quantity,

            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->label,
                'price' => (float) $variant->price,
                'compare_at_price' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
                'cost_price' => $variant->cost_price ? (float) $variant->cost_price : null,
                'stock_quantity' => $variant->stock_quantity,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'allow_backorder' => $variant->allow_backorder,
                'is_active' => $variant->is_active,
                // Keyed by attribute id so the form can render one select per attribute
                'attribute_value_ids' => $variant->attributeValues
                    ->mapWithKeys(fn ($v) => [$v->pivot->attribute_id => $v->id]),
            ]),

            'specifications' => $product->specifications->map(fn ($spec) => [
                'group' => $spec->getTranslations('group'),
                'key' => $spec->getTranslations('key'),
                'value' => $spec->getTranslations('value'),
            ]),

            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ]),
        ];
    }

    private function formOptions(): array
    {
        return [
            'brands' => $this->brandOptions(),
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
            'conditions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'refurbished', 'label' => 'Refurbished'],
                ['value' => 'used', 'label' => 'Used'],
            ],
            'tags' => Tag::orderBy('id')->get()
                ->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->getTranslation('name', 'en')])
                ->all(),
            'attributes' => Attribute::active()->with('values')->orderBy('sort_order')->get()
                ->map(fn (Attribute $attribute) => [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->getTranslation('name', 'en'),
                    'input_type' => $attribute->input_type,
                    'values' => $attribute->values->map(fn ($value) => [
                        'id' => $value->id,
                        'label' => $value->getTranslation('label', 'en'),
                        'colour_hex' => $value->colour_hex,
                    ]),
                ])
                ->all(),
        ];
    }

    private function brandOptions(): array
    {
        return Brand::active()->orderBy('sort_order')->get()
            ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->getTranslation('name', 'en')])
            ->all();
    }

    private function categoryOptions(): array
    {
        return Category::active()->orderBy('sort_order')->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->getTranslation('name', 'en')])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function statusOptions(): array
    {
        return collect(ProductStatus::cases())
            ->map(fn (ProductStatus $s) => ['value' => $s->value, 'label' => $s->label()])
            ->all();
    }
}
