<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ['en' => $name, 'km' => $name],
            'description' => ['en' => fake()->sentence(), 'km' => fake()->sentence()],
            'image' => null,
            'icon' => null,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    /** A child of the given category — the tree is only ever 2–3 levels deep. */
    public function childOf(Category $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
