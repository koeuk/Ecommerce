<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 10 concerns: SEO inputs, cache correctness and N+1 avoidance.
 */
class PolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // SEO

    public function test_the_sitemap_lists_published_slugs_only(): void
    {
        // Products bring their own brand and category, so pin them to shared
        // ones to keep the counts meaningful.
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $published = Product::factory()->forCategory($category)->forBrand($brand)->create();
        Product::factory()->forCategory($category)->forBrand($brand)->draft()->create();
        Category::factory()->inactive()->create();

        $this->getJson('/api/v1/sitemap')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.slug', $published->slug)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.slug', $category->slug)
            ->assertJsonCount(1, 'data.brands')
            ->assertJsonPath('data.brands.0.slug', $brand->slug);
    }

    public function test_products_expose_their_meta_fields(): void
    {
        $product = Product::factory()->create([
            'meta_title' => ['en' => 'Buy a Gaming Laptop'],
            'meta_description' => ['en' => 'The best laptop in Phnom Penh'],
        ]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.meta_title', 'Buy a Gaming Laptop')
            ->assertJsonPath('data.meta_description', 'The best laptop in Phnom Penh');
    }

    // Cache correctness

    public function test_editing_a_category_clears_the_cached_tree(): void
    {
        $category = Category::factory()->create(['name' => ['en' => 'Laptops']]);

        $this->getJson('/api/v1/categories-tree')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Laptops');

        $category->update(['name' => ['en' => 'Notebooks']]);

        // Cached for a day, so without invalidation this would still say Laptops.
        $this->getJson('/api/v1/categories-tree')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Notebooks');
    }

    public function test_adding_a_product_clears_the_cached_sitemap(): void
    {
        Product::factory()->create();

        $this->getJson('/api/v1/sitemap')->assertJsonCount(1, 'data.products');

        Product::factory()->create();

        $this->getJson('/api/v1/sitemap')->assertJsonCount(2, 'data.products');
    }

    // N+1

    public function test_the_product_listing_does_not_issue_a_query_per_row(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->count(12)->forBrand($brand)->create();

        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(12, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Count + page + eager loads. A per-row lookup would push this far higher.
        $this->assertLessThan(10, $queries, "Listing 12 products took {$queries} queries — likely an N+1.");
    }

    public function test_the_home_feed_does_not_issue_a_query_per_row(): void
    {
        Product::factory()->count(10)->create();
        Product::factory()->count(3)->featured()->create();

        DB::enableQueryLog();
        $this->getJson('/api/v1/home')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Four sections, each with its own eager loads.
        $this->assertLessThan(15, $queries, "The home feed took {$queries} queries — likely an N+1.");
    }

    // Pagination is bounded everywhere that returns a list

    public function test_listing_endpoints_are_paginated(): void
    {
        Product::factory()->count(30)->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 24)
            ->assertJsonCount(24, 'data');
    }
}
