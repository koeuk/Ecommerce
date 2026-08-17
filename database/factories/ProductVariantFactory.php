<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 *
 * The variant is the real sellable unit — it owns price and stock.
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'VAR-'.fake()->unique()->numerify('######'),
            'label' => fake()->randomElement(['16GB / 512GB', '32GB / 1TB', '41mm / Black']),

            'price' => fake()->randomFloat(2, 20, 3000),
            'compare_at_price' => null,
            'cost_price' => null,

            'stock_quantity' => fake()->numberBetween(5, 100),
            'low_stock_threshold' => 5,
            'allow_backorder' => false,

            'weight' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /** Every product has exactly one default variant. */
    public function isDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /** At or below the threshold, so `lowStock()` picks it up. */
    public function lowStock(): static
    {
        return $this->state(fn () => [
            'stock_quantity' => 2,
            'low_stock_threshold' => 5,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function backorderable(): static
    {
        return $this->state(fn () => ['allow_backorder' => true]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }
}
