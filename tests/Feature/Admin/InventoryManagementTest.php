<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->manager = tap(User::factory()->create())->assignRole(Role::Manager->value);
    }

    public function test_index_is_reachable(): void
    {
        ProductVariant::factory()->count(3)->create();

        $this->actingAs($this->manager)
            ->get(route('admin.inventory.index'))
            ->assertOk();
    }

    public function test_adding_stock_records_an_inbound_movement(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 10]);

        $this->actingAs($this->manager)->put(
            route('admin.inventory.adjust', $variant),
            ['mode' => 'add', 'quantity' => 5, 'reason' => 'Restock']
        );

        $this->assertSame(15, $variant->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'in',
            'quantity' => 5,
            'stock_after' => 15,
            'reason' => 'Restock',
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_subtracting_stock_records_an_outbound_movement(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 10]);

        $this->actingAs($this->manager)->put(
            route('admin.inventory.adjust', $variant),
            ['mode' => 'subtract', 'quantity' => 4]
        );

        $this->assertSame(6, $variant->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'out',
            'quantity' => -4,
            'stock_after' => 6,
            'reason' => 'Manual adjustment',   // falls back when none given
        ]);
    }

    public function test_stock_never_goes_negative(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 3]);

        $this->actingAs($this->manager)->put(
            route('admin.inventory.adjust', $variant),
            ['mode' => 'subtract', 'quantity' => 10]
        );

        // Clamped at zero, and the movement records the real delta.
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'quantity' => -3,
            'stock_after' => 0,
        ]);
    }

    public function test_set_mode_overwrites_the_quantity(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 10]);

        $this->actingAs($this->manager)->put(
            route('admin.inventory.adjust', $variant),
            ['mode' => 'set', 'quantity' => 42, 'reason' => 'Stock count']
        );

        $this->assertSame(42, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'in',
            'quantity' => 32,
            'stock_after' => 42,
        ]);
    }

    public function test_adjusting_a_variant_resyncs_the_product_total(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $a = ProductVariant::factory()->forProduct($product)->create(['stock_quantity' => 4]);
        ProductVariant::factory()->forProduct($product)->create(['stock_quantity' => 6]);

        $this->actingAs($this->manager)->put(
            route('admin.inventory.adjust', $a),
            ['mode' => 'set', 'quantity' => 10]
        );

        // Denormalised product stock is the sum of its variants: 10 + 6.
        $this->assertSame(16, $product->fresh()->stock_quantity);
    }

    public function test_an_invalid_mode_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);

        $this->actingAs($this->manager)
            ->put(route('admin.inventory.adjust', $variant), ['mode' => 'multiply', 'quantity' => 2])
            ->assertSessionHasErrors('mode');

        $this->assertSame(5, $variant->fresh()->stock_quantity);
    }

    public function test_a_negative_quantity_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);

        $this->actingAs($this->manager)
            ->put(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => -3])
            ->assertSessionHasErrors('quantity');
    }

    public function test_history_lists_movements_for_the_variant(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 0]);

        $this->actingAs($this->manager)->put(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 5]);
        $this->actingAs($this->manager)->put(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 5]);

        $this->actingAs($this->manager)
            ->get(route('admin.inventory.history', $variant))
            ->assertOk();

        $this->assertSame(2, $variant->inventoryMovements()->count());
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_staff_can_view_but_not_adjust_inventory(): void
    {
        $staff = tap(User::factory()->create())->assignRole(Role::Staff->value);
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);

        $this->actingAs($staff)->get(route('admin.inventory.index'))->assertOk();

        $this->actingAs($staff)
            ->put(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 1])
            ->assertForbidden();

        $this->assertSame(5, $variant->fresh()->stock_quantity);
    }
}
