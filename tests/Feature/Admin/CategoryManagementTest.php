<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
    }

    public function test_manager_can_create_a_root_category(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.categories.store'), [
                'name' => ['en' => 'Laptops', 'km' => 'កុំព្យូទ័រយួរដៃ'],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::firstWhere('slug', 'laptops');

        $this->assertNotNull($category);
        $this->assertNull($category->parent_id);
        $this->assertSame('Laptops', $category->getTranslation('name', 'en'));
        $this->assertSame('កុំព្យូទ័រយួរដៃ', $category->getTranslation('name', 'km'));
    }

    public function test_a_category_can_be_nested_under_a_parent(): void
    {
        $parent = Category::factory()->create(['name' => ['en' => 'Computers']]);

        $this->actingAs($this->manager)->post(route('admin.categories.store'), [
            'name' => ['en' => 'Gaming Laptops'],
            'parent_id' => $parent->id,
        ]);

        $child = Category::firstWhere('slug', 'gaming-laptops');

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->manager)
            ->put(route('admin.categories.update', $category), [
                'name' => ['en' => 'Renamed'],
                'parent_id' => $category->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_a_category_cannot_move_beneath_its_own_descendant(): void
    {
        $root = Category::factory()->create(['name' => ['en' => 'Computers']]);
        $child = Category::factory()->childOf($root)->create(['name' => ['en' => 'Laptops']]);
        $grandchild = Category::factory()->childOf($child)->create(['name' => ['en' => 'Gaming']]);

        // Moving the root under its own grandchild would orphan the subtree.
        $this->actingAs($this->manager)
            ->put(route('admin.categories.update', $root), [
                'name' => ['en' => 'Computers'],
                'parent_id' => $grandchild->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $parent->id, 'deleted_at' => null]);
    }

    public function test_a_category_still_holding_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->forCategory($category)->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_an_empty_leaf_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_the_english_name_is_required(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.categories.store'), ['name' => ['km' => 'ខ្មែរ']])
            ->assertSessionHasErrors('name.en');
    }

    public function test_slugs_are_unique(): void
    {
        Category::factory()->create(['slug' => 'laptops']);

        $this->actingAs($this->manager)
            ->post(route('admin.categories.store'), [
                'name' => ['en' => 'Laptops'],
                'slug' => 'laptops',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_the_tree_rolls_descendant_product_counts_upward(): void
    {
        $root = Category::factory()->create(['name' => ['en' => 'Computers']]);
        $child = Category::factory()->childOf($root)->create(['name' => ['en' => 'Laptops']]);

        Product::factory()->forCategory($root)->create();
        Product::factory()->count(2)->forCategory($child)->create();

        $this->actingAs($this->manager)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // A parent reports its own products plus everything beneath it.
                ->where('tree.0.products_count', 3)
                ->where('tree.0.children.0.products_count', 2)
            );
    }

    public function test_the_list_can_be_filtered_and_sorted(): void
    {
        Category::factory()->create(['name' => ['en' => 'Laptops'], 'sort_order' => 2]);
        Category::factory()->inactive()->create(['name' => ['en' => 'Retired'], 'sort_order' => 1]);

        // spatie/laravel-query-builder drives these, same as the other lists.
        $this->actingAs($this->manager)
            ->get(route('admin.categories.index', ['filter' => ['status' => 'active']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('tree', 1));

        $this->actingAs($this->manager)
            ->get(route('admin.categories.index', ['filter' => ['search' => 'Lap']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('tree', 1));
    }

    public function test_an_unsupported_sort_is_rejected(): void
    {
        Category::factory()->create();

        // The allow-list stops an internal column being exposed via ordering.
        $this->actingAs($this->manager)
            ->get(route('admin.categories.index', ['sort' => 'image']))
            ->assertBadRequest();
    }

    public function test_staff_cannot_create_categories(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);

        $this->actingAs($staff)
            ->post(route('admin.categories.store'), ['name' => ['en' => 'Nope']])
            ->assertForbidden();
    }
}
