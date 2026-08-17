<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush(); // the category tree is cached for a day
    }

    // Products

    public function test_only_published_products_are_listed(): void
    {
        Product::factory()->count(2)->create();
        Product::factory()->draft()->create();
        Product::factory()->archived()->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_products_can_be_filtered_by_brand_and_category(): void
    {
        $brand = Brand::factory()->create(['name' => ['en' => 'Asus']]);
        $category = Category::factory()->create(['name' => ['en' => 'Laptops']]);

        $match = Product::factory()->forBrand($brand)->forCategory($category)->create();
        Product::factory()->create();

        $this->getJson("/api/v1/products?filter[brand]={$brand->slug}&filter[category]={$category->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_products_can_be_filtered_by_price_range(): void
    {
        Product::factory()->create(['price' => 100]);
        $mid = Product::factory()->create(['price' => 500]);
        Product::factory()->create(['price' => 2000]);

        $this->getJson('/api/v1/products?filter[price_min]=200&filter[price_max]=1000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mid->id);
    }

    public function test_products_can_be_filtered_to_in_stock_only(): void
    {
        Product::factory()->create(['stock_quantity' => 5]);
        Product::factory()->outOfStock()->create();

        $this->getJson('/api/v1/products?filter[in_stock]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_products_can_be_sorted_by_price(): void
    {
        $cheap = Product::factory()->create(['price' => 10]);
        $dear = Product::factory()->create(['price' => 900]);

        $this->getJson('/api/v1/products?sort=price')
            ->assertOk()
            ->assertJsonPath('data.0.id', $cheap->id);

        $this->getJson('/api/v1/products?sort=-price')
            ->assertOk()
            ->assertJsonPath('data.0.id', $dear->id);
    }

    public function test_an_unsupported_sort_is_rejected(): void
    {
        Product::factory()->create();

        // spatie/laravel-query-builder refuses anything not allow-listed,
        // so cost_price cannot be leaked through ordering.
        $this->getJson('/api/v1/products?sort=cost_price')->assertBadRequest();
    }

    public function test_pagination_is_capped(): void
    {
        Product::factory()->count(5)->create();

        $this->getJson('/api/v1/products?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5);

        // Asking for more than the cap yields the cap, not the request.
        $this->getJson('/api/v1/products?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_a_product_detail_includes_variants_specs_and_related(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->create();
        $sibling = Product::factory()->forCategory($category)->create();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('meta.related.0.id', $sibling->id);
    }

    public function test_viewing_a_product_increments_its_view_count(): void
    {
        $product = Product::factory()->create(['views_count' => 0]);

        $this->getJson("/api/v1/products/{$product->slug}")->assertOk();

        $this->assertSame(1, $product->fresh()->views_count);
    }

    public function test_an_unpublished_product_is_not_reachable(): void
    {
        $draft = Product::factory()->draft()->create();

        $this->getJson("/api/v1/products/{$draft->slug}")->assertNotFound();
        $this->getJson('/api/v1/products/no-such-slug')->assertNotFound();
    }

    // Locale negotiation

    public function test_the_response_language_follows_the_lang_parameter(): void
    {
        $product = Product::factory()->create([
            'title' => ['en' => 'Gaming Laptop', 'km' => 'កុំព្យូទ័រហ្គេម'],
        ]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertJsonPath('data.title', 'Gaming Laptop');

        $this->getJson("/api/v1/products/{$product->slug}?lang=km")
            ->assertJsonPath('data.title', 'កុំព្យូទ័រហ្គេម');
    }

    public function test_the_response_language_follows_the_accept_language_header(): void
    {
        $product = Product::factory()->create([
            'title' => ['en' => 'Gaming Laptop', 'km' => 'កុំព្យូទ័រហ្គេម'],
        ]);

        $this->withHeader('Accept-Language', 'km')
            ->getJson("/api/v1/products/{$product->slug}")
            ->assertJsonPath('data.title', 'កុំព្យូទ័រហ្គេម');
    }

    public function test_an_unsupported_locale_falls_back_to_english(): void
    {
        $product = Product::factory()->create([
            'title' => ['en' => 'Gaming Laptop', 'km' => 'កុំព្យូទ័រហ្គេម'],
        ]);

        $this->getJson("/api/v1/products/{$product->slug}?lang=fr")
            ->assertJsonPath('data.title', 'Gaming Laptop');
    }

    // Categories & brands

    public function test_the_category_tree_nests_children(): void
    {
        $root = Category::factory()->create(['name' => ['en' => 'Computers']]);
        $child = Category::factory()->childOf($root)->create(['name' => ['en' => 'Laptops']]);

        $this->getJson('/api/v1/categories-tree')
            ->assertOk()
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.children.0.id', $child->id);
    }

    public function test_inactive_categories_are_hidden(): void
    {
        Category::factory()->create();
        Category::factory()->inactive()->create();

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_brand_can_be_fetched_by_slug(): void
    {
        $brand = Brand::factory()->create(['name' => ['en' => 'Asus']]);

        $this->getJson("/api/v1/brands/{$brand->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $brand->slug);

        $this->getJson('/api/v1/brands/no-such-brand')->assertNotFound();
    }

    // Storefront chrome

    public function test_the_home_feed_returns_each_section(): void
    {
        Product::factory()->featured()->create();
        Product::factory()->count(2)->create();
        Category::factory()->featured()->create();

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['featured', 'new_arrivals', 'best_sellers', 'featured_categories'],
            ])
            ->assertJsonCount(1, 'data.featured')
            ->assertJsonCount(3, 'data.new_arrivals')
            ->assertJsonCount(1, 'data.featured_categories');
    }

    public function test_the_home_feed_excludes_unpublished_products(): void
    {
        Product::factory()->draft()->create();

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(0, 'data.new_arrivals');
    }

    public function test_settings_are_exposed(): void
    {
        Setting::put('store_name', ['en' => 'Boomer'], 'general');
        Setting::put('free_shipping_threshold', 100, 'shipping');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.free_shipping_threshold', 100);
    }

    public function test_filter_metadata_reflects_the_current_selection(): void
    {
        $laptops = Category::factory()->create(['name' => ['en' => 'Laptops']]);
        $asus = Brand::factory()->create(['name' => ['en' => 'Asus']]);
        $casio = Brand::factory()->create(['name' => ['en' => 'Casio']]);

        Product::factory()->forCategory($laptops)->forBrand($asus)->create(['price' => 900]);
        Product::factory()->forCategory($laptops)->forBrand($asus)->create(['price' => 1500]);
        Product::factory()->forBrand($casio)->create(['price' => 50]);   // different category

        // Unscoped: both brands, full price range.
        $this->getJson('/api/v1/filters')
            ->assertOk()
            ->assertJsonCount(2, 'data.brands')
            ->assertJsonPath('data.price.min', 50)
            ->assertJsonPath('data.price.max', 1500);

        // Scoped to a category: only the brands actually present in it.
        $this->getJson("/api/v1/filters?category={$laptops->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.brands')
            ->assertJsonPath('data.brands.0.slug', $asus->slug)
            ->assertJsonPath('data.price.min', 900);
    }
}
