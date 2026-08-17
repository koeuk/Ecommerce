<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 *
 * Defaults to **published** — most tests want a product the storefront can
 * actually see. Use ->draft() or ->archived() for the other cases.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 20, 3000);

        return [
            // Translatable — slug is generated from the English value on create.
            'title' => ['en' => $title, 'km' => $title],
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'short_description' => ['en' => fake()->sentence(), 'km' => fake()->sentence()],
            'description' => ['en' => fake()->paragraph(), 'km' => fake()->paragraph()],

            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),

            // Display price — mirrors the default variant for listing and sort.
            'price' => $price,
            'compare_at_price' => null,
            'cost_price' => round($price * 0.7, 2),

            'status' => ProductStatus::Published,
            'condition' => 'new',
            'is_featured' => false,

            'warranty_months' => fake()->randomElement([6, 12, 24]),
            'release_year' => fake()->numberBetween(2020, 2026),

            'weight' => fake()->randomFloat(3, 0.1, 5),
            'length' => fake()->randomFloat(2, 5, 50),
            'width' => fake()->randomFloat(2, 5, 40),
            'height' => fake()->randomFloat(2, 1, 20),

            'stock_quantity' => fake()->numberBetween(1, 100),
            'views_count' => 0,
            'rating_avg' => 0,
            'rating_count' => 0,

            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Archived]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }

    /** Strike-through pricing — compare_at_price above the real price. */
    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'compare_at_price' => round(((float) $attributes['price']) * 1.3, 2),
        ]);
    }

    public function condition(string $condition): static
    {
        return $this->state(fn () => ['condition' => $condition]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['brand_id' => $brand->id]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }
}
