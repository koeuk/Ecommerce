<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
    }

    /** A minimal payload that satisfies ProductRequest. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['en' => 'Gaming Laptop', 'km' => 'កុំព្យូទ័រ'],
            'sku' => 'LAP-001',
            'price' => 1499.00,
            'status' => ProductStatus::Published->value,
            'condition' => 'new',
        ], $overrides);
    }

    public function test_index_lists_products(): void
    {
        Product::factory()->count(3)->create();

        $this->actingAs($this->manager)
            ->get(route('admin.products.index'))
            ->assertOk();
    }

    public function test_manager_can_create_a_product(): void
    {
        $response = $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload());

        $product = Product::firstWhere('sku', 'LAP-001');

        $this->assertNotNull($product);
        $response->assertRedirect(route('admin.products.edit', $product));

        $this->assertSame('Gaming Laptop', $product->getTranslation('title', 'en'));
        $this->assertSame('កុំព្យូទ័រ', $product->getTranslation('title', 'km'));
        $this->assertSame('gaming-laptop', $product->slug);      // generated
        $this->assertSame($this->manager->id, $product->created_by);
    }

    public function test_a_product_without_variants_still_gets_a_default_one(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload());

        $product = Product::firstWhere('sku', 'LAP-001');

        // A product must stay sellable, so the service falls back to one variant.
        $this->assertCount(1, $product->variants);
        $this->assertTrue($product->variants->first()->is_default);
        $this->assertSame('LAP-001-DEFAULT', $product->variants->first()->sku);
    }

    public function test_variant_matrix_is_stored_with_its_attribute_combinations(): void
    {
        [$ram, $storage] = [
            Attribute::factory()->code('ram', 'RAM')->create(),
            Attribute::factory()->code('storage', 'Storage')->create(),
        ];

        $ram16 = AttributeValue::factory()->of($ram)->label('16GB')->create();
        $ram32 = AttributeValue::factory()->of($ram)->label('32GB')->create();
        $ssd512 = AttributeValue::factory()->of($storage)->label('512GB')->create();

        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'variants' => [
                [
                    'sku' => 'LAP-001-A', 'price' => 1499, 'stock_quantity' => 5,
                    'attribute_value_ids' => [$ram->id => $ram16->id, $storage->id => $ssd512->id],
                ],
                [
                    'sku' => 'LAP-001-B', 'price' => 1799, 'stock_quantity' => 3,
                    'attribute_value_ids' => [$ram->id => $ram32->id, $storage->id => $ssd512->id],
                ],
            ],
        ]));

        $product = Product::firstWhere('sku', 'LAP-001');

        $this->assertCount(2, $product->variants);

        // The first row is forced to be the default.
        $first = $product->variants->firstWhere('sku', 'LAP-001-A');
        $this->assertTrue($first->is_default);
        $this->assertEqualsCanonicalizing(
            [$ram16->id, $ssd512->id],
            $first->attributeValues->pluck('id')->all()
        );

        // Denormalised product stock is the sum of its variants.
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_two_variants_cannot_share_an_option_combination(): void
    {
        $ram = Attribute::factory()->code('ram', 'RAM')->create();
        $ram16 = AttributeValue::factory()->of($ram)->label('16GB')->create();

        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload([
                'variants' => [
                    ['sku' => 'A-1', 'price' => 10, 'stock_quantity' => 1, 'attribute_value_ids' => [$ram->id => $ram16->id]],
                    ['sku' => 'A-2', 'price' => 20, 'stock_quantity' => 1, 'attribute_value_ids' => [$ram->id => $ram16->id]],
                ],
            ]))
            ->assertSessionHasErrors('variants.1.attribute_value_ids');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_variant_skus_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload([
                'variants' => [
                    ['sku' => 'SAME', 'price' => 10, 'stock_quantity' => 1],
                    ['sku' => 'SAME', 'price' => 20, 'stock_quantity' => 1],
                ],
            ]))
            ->assertSessionHasErrors('variants.1.sku');
    }

    public function test_compare_at_price_must_exceed_price(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload([
                'price' => 100,
                'compare_at_price' => 80,
            ]))
            ->assertSessionHasErrors('compare_at_price');
    }

    public function test_sku_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'LAP-001']);

        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload())
            ->assertSessionHasErrors('sku');
    }

    public function test_specifications_round_trip_in_both_locales(): void
    {
        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'specifications' => [
                [
                    'group' => ['en' => 'Processor'],
                    'key' => ['en' => 'CPU'],
                    'value' => ['en' => 'i7-13700H', 'km' => 'i7-13700H'],
                ],
                [
                    'group' => ['en' => 'Display'],
                    'key' => ['en' => 'Screen'],
                    'value' => ['en' => '16-inch'],
                ],
            ],
        ]));

        $specs = Product::firstWhere('sku', 'LAP-001')->specifications;

        $this->assertCount(2, $specs);
        $this->assertSame('CPU', $specs[0]->getTranslation('key', 'en'));
        $this->assertSame('Processor', $specs[0]->getTranslation('group', 'en'));
        $this->assertSame(1, $specs[0]->sort_order);
        $this->assertSame(2, $specs[1]->sort_order);
    }

    public function test_a_specification_row_needs_an_english_key(): void
    {
        // ProductService also guards against a blank key, but validation
        // rejects the row first — the guard is unreachable over HTTP.
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload([
                'specifications' => [['key' => ['en' => '']]],
            ]))
            ->assertSessionHasErrors('specifications.0.key.en');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_update_replaces_variants_absent_from_the_payload(): void
    {
        $product = Product::factory()->create(['sku' => 'LAP-001']);
        $keep = ProductVariant::factory()->forProduct($product)->create(['sku' => 'KEEP']);
        $drop = ProductVariant::factory()->forProduct($product)->create(['sku' => 'DROP']);

        $this->actingAs($this->manager)->put(route('admin.products.update', $product), $this->payload([
            'variants' => [
                ['id' => $keep->id, 'sku' => 'KEEP', 'price' => 99, 'stock_quantity' => 7],
            ],
        ]));

        $this->assertDatabaseHas('product_variants', ['id' => $keep->id, 'stock_quantity' => 7]);
        $this->assertSoftDeleted('product_variants', ['id' => $drop->id]);
        $this->assertSame($this->manager->id, $product->fresh()->updated_by);
    }

    public function test_deleting_a_product_only_soft_deletes_it(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        // Soft delete keeps order history pointing at a real row.
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_duplicate_copies_the_product_as_a_draft(): void
    {
        $product = Product::factory()->create([
            'sku' => 'LAP-001',
            'title' => ['en' => 'Gaming Laptop', 'km' => 'កុំព្យូទ័រ'],
            'status' => ProductStatus::Published,
            'views_count' => 500,
        ]);
        ProductVariant::factory()->forProduct($product)->count(2)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.products.duplicate', $product))
            ->assertRedirect();

        $copy = Product::where('id', '!=', $product->id)->firstOrFail();

        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertSame('Gaming Laptop (copy)', $copy->getTranslation('title', 'en'));
        $this->assertSame('កុំព្យូទ័រ (copy)', $copy->getTranslation('title', 'km'));
        $this->assertNotSame($product->sku, $copy->sku);
        $this->assertNotSame($product->slug, $copy->slug);
        $this->assertSame(0, $copy->views_count);
        $this->assertCount(2, $copy->variants);
    }

    public function test_staff_cannot_create_or_delete_products(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);
        $product = Product::factory()->create();

        $this->actingAs($staff)->get(route('admin.products.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.products.store'), $this->payload())->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.products.destroy', $product))->assertForbidden();

        // Staff is read-mostly, so viewing is still allowed.
        $this->actingAs($staff)->get(route('admin.products.index'))->assertOk();
    }

    public function test_customers_cannot_reach_the_product_admin(): void
    {
        $customer = tap(User::factory()->create())->assignRole(Role::Customer->value);

        $this->actingAs($customer)
            ->get(route('admin.products.index'))
            ->assertRedirect();
    }

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.products.index'))->assertRedirect();
    }
}
